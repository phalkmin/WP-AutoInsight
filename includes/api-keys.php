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
	$model = ! empty( $model ) ? $model : get_option( 'prompt_select', 'gpt-4.1-mini' );

	$model_options = abcc_get_ai_model_options();

	foreach ( $model_options as $provider_key => $group ) {
		if ( isset( $group['options'][ $model ] ) ) {
			return $provider_key;
		}
	}

	if ( false !== strpos( $model, 'claude' ) ) {
		return 'claude';
	}

	if ( false !== strpos( $model, 'gemini' ) ) {
		return 'gemini';
	}

	if ( 0 === strpos( $model, 'sonar' ) ) {
		return 'perplexity';
	}

	if ( false !== strpos( $model, 'gpt' ) || preg_match( '/^o[0-9]/', $model ) ) {
		return 'openai';
	}

	return '';
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

	// Map our internal slugs to WP 7.0 Connectors IDs.
	$connector_id = $provider;
	if ( 'claude' === $provider ) {
		$connector_id = 'anthropic';
	} elseif ( 'gemini' === $provider ) {
		$connector_id = 'google';
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
	$api_key = '';

	if ( abcc_wp_ai_client_available() ) {
		$wp_key = abcc_get_wp_ai_credential( $provider );
		if ( ! empty( $wp_key ) ) {
			return $wp_key;
		}
	}

	switch ( $provider ) {
		case 'openai':
			$api_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
			break;

		case 'claude':
			$api_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
			break;

		case 'gemini':
			$api_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
			break;

		case 'perplexity':
			$api_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : get_option( 'perplexity_api_key', '' );
			break;

		case 'stability':
			$api_key = defined( 'STABILITY_API' ) ? STABILITY_API : get_option( 'stability_api_key', '' );
			break;
	}

	return $api_key;
}

/**
 * Check if the current user has permission to prompt AI.
 *
 * @since 3.6.0
 * @return bool
 */
function abcc_current_user_can_prompt() {
	// Priority 1: Native WP 7.0 capability.
	// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Added during plugin activation for WP 7.0 compatibility.
	if ( current_user_can( 'prompt_ai' ) ) {
		return true;
	}

	// Priority 2: Standard admin/editor permissions for backward compatibility.
	return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
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

	$preferred_image_service = get_option( 'preferred_image_service', 'auto' );

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
	$model_provider = '';
	if ( false !== strpos( $text_model, 'gpt' ) || 'openai' === $text_model || preg_match( '/^o[0-9]/', $text_model ) ) {
		$model_provider = 'openai';
	} elseif ( false !== strpos( $text_model, 'claude' ) ) {
		$model_provider = 'claude';
	} elseif ( false !== strpos( $text_model, 'gemini' ) ) {
		$model_provider = 'gemini';
	} elseif ( false !== strpos( $text_model, 'sonar' ) ) {
		$model_provider = 'perplexity';
	}

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
