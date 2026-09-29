<?php
/**
 * API key handling functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if WordPress 7.0+ Connectors API is available.
 *
 * @since 3.6.0
 * @return bool
 */
function abcc_wp_ai_client_available() {
	return function_exists( 'wp_is_connector_registered' );
}

/**
 * Resolve a provider slug from a model identifier.
 *
 * @since 3.6.0
 * @param string $model Model identifier.
 * @return string
 */
function abcc_get_model_provider( $model = '' ) {
	return abcc_get_provider_for_model( $model );
}

/**
 * Retrieve a credential from WordPress 7.0 Connectors if available.
 *
 * Uses the Connectors API public functions and follows WP's documented
 * key resolution order: env var → PHP constant → database option.
 * WP constant/env-var convention: {PROVIDER_ID}_API_KEY (uppercase).
 * WP database option convention: connectors_ai_{$id}_api_key.
 *
 * @since 3.6.0
 * @param string $provider Our internal provider slug (openai, claude, gemini, etc.).
 * @return string|null The API key, or null if not found or not managed by WP.
 */
function abcc_get_wp_ai_credential( $provider ) {
	if ( ! abcc_wp_ai_client_available() ) {
		return null;
	}

	$connector_id = abcc_get_provider_wp_connector_id( $provider );
	if ( empty( $connector_id ) ) {
		return null;
	}

	// Only proceed if WP has this connector registered.
	if ( ! wp_is_connector_registered( $connector_id ) ) {
		return null;
	}

	// WP 7.0 key resolution: env var → PHP constant → database.
	// Convention: {PROVIDER_ID}_API_KEY  e.g. OPENAI_API_KEY, ANTHROPIC_API_KEY.
	$wp_const = strtoupper( $connector_id ) . '_API_KEY';

	$env_val = getenv( $wp_const );
	if ( ! empty( $env_val ) ) {
		return $env_val;
	}

	if ( defined( $wp_const ) ) {
		return constant( $wp_const );
	}

	$db_val = get_option( 'connectors_ai_' . $connector_id . '_api_key', '' );
	return ! empty( $db_val ) ? $db_val : null;
}

/**
 * Retrieve the configured API key for a provider.
 *
 * Order: WP 7.0 Connectors, wp-config constant, wp_options.
 *
 * @since 3.6.0
 * @param string $provider Provider slug.
 * @return string
 */
function abcc_get_provider_api_key( $provider ) {
	if ( abcc_wp_ai_client_available() ) {
		$wp_key = abcc_get_wp_ai_credential( $provider );
		if ( ! empty( $wp_key ) ) {
			return $wp_key;
		}
	}

	$constant_name = abcc_get_provider_constant_name( $provider );
	if ( ! empty( $constant_name ) && defined( $constant_name ) ) {
		return constant( $constant_name );
	}

	return abcc_get_provider_saved_api_key( $provider );
}

/**
 * Check if the current user has permission to prompt AI.
 *
 * @since 3.6.0
 * @return bool
 */
function abcc_current_user_can_prompt() {
	// WP 7.0+ site-level AI toggle. If the site admin has disabled AI at
	// the WordPress level, plugin features are inactive. On WP 6.9 the
	// function does not exist, so this check is a no-op.
	if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
		return false;
	}

	// Priority 1: The plugin-defined 'prompt_ai' capability. The plugin keeps
	// it in sync with the Permissions tab for the built-in roles (see
	// abcc_sync_prompt_ai_capability); third-party role managers can grant it
	// to custom roles.
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Plugin-defined capability registered at activation.
	if ( current_user_can( 'prompt_ai' ) ) {
		return true;
	}

	// Priority 2: Check the configured allowed roles.
	$allowed_roles = abcc_get_setting( 'abcc_allowed_roles', array( 'administrator', 'editor' ) );
	$user          = wp_get_current_user();

	if ( ! $user->exists() ) {
		return false;
	}

	return ! empty( array_intersect( $user->roles, $allowed_roles ) );
}

/**
 * Built-in roles the Permissions tab manages the prompt_ai capability for.
 *
 * @since 4.5.0
 * @return string[]
 */
function abcc_get_managed_prompt_roles() {
	return array( 'administrator', 'editor', 'author', 'contributor' );
}

/**
 * Grant or revoke prompt_ai on the built-in roles to match the allowed list.
 *
 * Custom roles are left alone so grants made by role managers survive.
 *
 * @since 4.5.0
 * @param array $allowed_roles Role slugs allowed to use AI tools.
 */
function abcc_sync_prompt_ai_capability( $allowed_roles ) {
	$allowed_roles   = array_map( 'sanitize_key', (array) $allowed_roles );
	$allowed_roles[] = 'administrator';

	foreach ( abcc_get_managed_prompt_roles() as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role ) {
			continue;
		}

		if ( in_array( $role_name, $allowed_roles, true ) ) {
			if ( ! $role->has_cap( 'prompt_ai' ) ) {
				$role->add_cap( 'prompt_ai' );
			}
		} elseif ( $role->has_cap( 'prompt_ai' ) ) {
			$role->remove_cap( 'prompt_ai' );
		}
	}
}

/**
 * Resolve the capability a post type maps to for one of its cap slots.
 *
 * @since 4.5.0
 * @param string $post_type Post type slug.
 * @param string $slot      Cap slot, e.g. 'publish_posts' or 'create_posts'.
 * @return string
 */
function abcc_get_post_type_cap( $post_type, $slot ) {
	$pto = get_post_type_object( (string) $post_type );
	if ( $pto && isset( $pto->cap->{$slot} ) && '' !== $pto->cap->{$slot} ) {
		return (string) $pto->cap->{$slot};
	}
	return $slot;
}

/**
 * Whether a user may create posts of the given type.
 *
 * @since 4.5.0
 * @param string $post_type Post type slug.
 * @param int    $user_id   Optional. 0 means the current user.
 * @return bool
 */
function abcc_user_can_create_post_type( $post_type, $user_id = 0 ) {
	$cap     = abcc_get_post_type_cap( $post_type, 'create_posts' );
	$user_id = (int) $user_id;
	return $user_id > 0 ? user_can( $user_id, $cap ) : current_user_can( $cap ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Mapped from the post type object.
}

/**
 * Whether a user may publish posts of the given type.
 *
 * @since 4.5.0
 * @param string $post_type Post type slug.
 * @param int    $user_id   Optional. 0 means the current user.
 * @return bool
 */
function abcc_user_can_publish_post_type( $post_type, $user_id = 0 ) {
	$cap     = abcc_get_post_type_cap( $post_type, 'publish_posts' );
	$user_id = (int) $user_id;
	return $user_id > 0 ? user_can( $user_id, $cap ) : current_user_can( $cap ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Mapped from the post type object.
}

/**
 * Check and retrieve the appropriate API key based on the selected AI model.
...
 * @return string The API key if found, empty string otherwise.
 */
function abcc_check_api_key( $model = '' ) {
	$provider = abcc_get_model_provider( $model );

	return abcc_get_provider_api_key( $provider );
}

/**
 * Determines which image generation service to use based on settings and availability.
 *
 * @param string $text_model The selected text generation model.
 * @return array Array containing 'service' and 'api_key'.
 */
function abcc_determine_image_service( $text_model ) {
	$openai_key    = abcc_get_provider_api_key( 'openai' );
	$stability_key = abcc_get_provider_api_key( 'stability' );
	$gemini_key    = abcc_get_provider_api_key( 'gemini' );

	$preferred_image_service = abcc_get_setting( 'preferred_image_service', 'auto' );

	// If user has explicitly selected a service, use it if available.
	if ( 'auto' !== $preferred_image_service ) {
		if ( 'stability' === $preferred_image_service && ! empty( $stability_key ) ) {
			return array(
				'service' => 'stability',
				'api_key' => $stability_key,
			);
		}
		if ( 'openai' === $preferred_image_service && ! empty( $openai_key ) ) {
			return array(
				'service' => 'openai',
				'api_key' => $openai_key,
			);
		}
		if ( 'gemini' === $preferred_image_service && ! empty( $gemini_key ) ) {
			return array(
				'service' => 'gemini',
				'api_key' => $gemini_key,
			);
		}
	}

	// Determine provider based on text model.
	$model_provider = abcc_get_model_provider( $text_model );

	// Auto-select based on text model provider.
	if ( 'openai' === $model_provider && ! empty( $openai_key ) ) {
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( 'gemini' === $model_provider && ! empty( $gemini_key ) ) {
		return array(
			'service' => 'gemini',
			'api_key' => $gemini_key,
		);
	} elseif ( ! empty( $stability_key ) ) {
		// Fallback to Stability for Claude or if no matching provider.
		return array(
			'service' => 'stability',
			'api_key' => $stability_key,
		);
	} elseif ( ! empty( $openai_key ) ) {
		// Fallback to OpenAI DALL-E.
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( ! empty( $gemini_key ) ) {
		// Fallback to Gemini Nano Banana.
		return array(
			'service' => 'gemini',
			'api_key' => $gemini_key,
		);
	}

	return array(
		'service' => null,
		'api_key' => null,
	);
}
