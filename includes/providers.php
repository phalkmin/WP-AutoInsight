<?php
/**
 * Provider registry and capability helpers.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get the provider registry.
 *
 * @return array
 */
function abcc_get_provider_registry() {
	static $registry = null;

	if ( null !== $registry ) {
		return $registry;
	}

	$registry = array(
		'openai'     => array(
			'id'                       => 'openai',
			'name'                     => 'OpenAI',
			'group_label'              => 'OpenAI Models',
			'help_url'                 => 'https://platform.openai.com/api-keys',
			'constant'                 => 'OPENAI_API',
			'option_name'              => 'openai_api_key',
			'wp_connector_id'          => 'openai',
			'text_generation_callback' => 'abcc_openai_generate_text',
			'connection_test_callback' => 'abcc_test_openai_connection',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => true,
				'citations'           => false,
				'audio_transcription' => true,
			),
			'text_models'              => array(
				'gpt-5.4-mini'            => array(
					'name'          => 'GPT-5.4 mini',
					'description'   => 'Fast and cost-effective — current default tier',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0002,
				),
				'gpt-5.4'                 => array(
					'name'          => 'GPT-5.4',
					'description'   => 'Balanced performance — best coding and instruction following',
					'cost_tier'     => '2',
					'cost_per_post' => 0.003,
				),
				'gpt-5.5'                 => array(
					'name'          => 'GPT-5.5',
					'description'   => 'Most capable OpenAI model for complex reasoning',
					'cost_tier'     => '3',
					'cost_per_post' => 0.015,
				),
				'gpt-4.1-mini-2025-04-14' => array(
					'name'          => 'GPT-4.1 Mini',
					'description'   => 'Fast and affordable with 1M context window',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0002,
					'legacy'        => true,
				),
				'gpt-4.1-2025-04-14'      => array(
					'name'          => 'GPT-4.1',
					'description'   => 'Excellent coding and instruction following',
					'cost_tier'     => '2',
					'cost_per_post' => 0.003,
					'legacy'        => true,
				),
			),
		),
		'claude'     => array(
			'id'                       => 'claude',
			'name'                     => 'Claude',
			'group_label'              => 'Claude Models',
			'help_url'                 => 'https://console.anthropic.com/',
			'constant'                 => 'CLAUDE_API',
			'option_name'              => 'claude_api_key',
			'wp_connector_id'          => 'anthropic',
			'text_generation_callback' => 'abcc_claude_generate_text',
			'connection_test_callback' => 'abcc_test_claude_connection',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				'claude-haiku-4-5-20251001' => array(
					'name'          => 'Claude Haiku 4.5',
					'description'   => 'Fastest model with near-frontier intelligence',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0003,
				),
				'claude-sonnet-4-6'         => array(
					'name'          => 'Claude Sonnet 4.6',
					'description'   => 'Best combination of speed and intelligence',
					'cost_tier'     => '2',
					'cost_per_post' => 0.004,
				),
				'claude-opus-4-8'           => array(
					'name'          => 'Claude Opus 4.8',
					'description'   => 'Most capable model for complex reasoning',
					'cost_tier'     => '3',
					'cost_per_post' => 0.015,
				),
			),
		),
		'gemini'     => array(
			'id'                       => 'gemini',
			'name'                     => 'Google Gemini',
			'group_label'              => 'Google Gemini Models',
			'help_url'                 => 'https://aistudio.google.com/',
			'constant'                 => 'GEMINI_API',
			'option_name'              => 'gemini_api_key',
			'wp_connector_id'          => 'google',
			'text_generation_callback' => 'abcc_gemini_generate_text',
			'connection_test_callback' => 'abcc_test_gemini_connection',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => true,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				'gemini-2.5-flash-lite' => array(
					'name'          => 'Gemini 2.5 Flash-Lite',
					'description'   => 'Fastest and most budget-friendly model',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0001,
				),
				'gemini-2.5-flash'      => array(
					'name'          => 'Gemini 2.5 Flash',
					'description'   => 'Best price-performance with thinking capabilities',
					'cost_tier'     => '2',
					'cost_per_post' => 0.0002,
				),
				'gemini-2.5-pro'        => array(
					'name'          => 'Gemini 2.5 Pro',
					'description'   => 'Most advanced reasoning model for complex problems',
					'cost_tier'     => '3',
					'cost_per_post' => 0.002,
				),
				'gemini-3.5-flash'      => array(
					'name'          => 'Gemini 3.5 Flash',
					'description'   => 'Latest generation — best for agentic and complex tasks',
					'cost_tier'     => '2',
					'cost_per_post' => 0.0003,
				),
			),
		),
		'perplexity' => array(
			'id'                       => 'perplexity',
			'name'                     => 'Perplexity',
			'group_label'              => 'Perplexity Models',
			'help_url'                 => 'https://www.perplexity.ai/settings/api',
			'constant'                 => 'PERPLEXITY_API',
			'option_name'              => 'perplexity_api_key',
			'wp_connector_id'          => 'perplexity',
			'text_generation_callback' => 'abcc_perplexity_generate_text',
			'connection_test_callback' => 'abcc_test_perplexity_connection',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => true,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				'sonar'               => array(
					'name'          => 'Sonar',
					'description'   => 'Fast web-grounded search with citations',
					'cost_tier'     => '1',
					'cost_per_post' => 0.001,
				),
				'sonar-pro'           => array(
					'name'          => 'Sonar Pro',
					'description'   => 'Deeper context with 2x more search results',
					'cost_tier'     => '2',
					'cost_per_post' => 0.005,
				),
				'sonar-reasoning-pro' => array(
					'name'          => 'Sonar Reasoning Pro',
					'description'   => 'Advanced multi-step reasoning with citations',
					'cost_tier'     => '3',
					'cost_per_post' => 0.01,
				),
				'sonar-deep-research' => array(
					'name'          => 'Sonar Deep Research',
					'description'   => 'In-depth multi-source synthesis — charges per citation + reasoning token',
					'cost_tier'     => '3',
					'cost_per_post' => 0.82,
					'cost_warning'  => true,
				),
			),
		),
		'stability'  => array(
			'id'                       => 'stability',
			'name'                     => 'Stability AI',
			'group_label'              => '',
			'help_url'                 => 'https://platform.stability.ai',
			'constant'                 => 'STABILITY_API',
			'option_name'              => 'stability_api_key',
			'wp_connector_id'          => '',
			'text_generation_callback' => '',
			'connection_test_callback' => 'abcc_test_stability_connection',
			'capabilities'             => array(
				'text_generation'     => false,
				'image_generation'    => true,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => array(),
		),
	);

	$registry = apply_filters( 'abcc_provider_registry', $registry );

	return $registry;
}

/**
 * Get a provider configuration.
 *
 * @param string $provider Provider ID.
 * @return array|null
 */
function abcc_get_provider( $provider ) {
	$registry = abcc_get_provider_registry();

	return $registry[ $provider ] ?? null;
}

/**
 * Get provider ids.
 *
 * @return string[]
 */
function abcc_get_provider_ids() {
	return array_keys( abcc_get_provider_registry() );
}

/**
 * Check whether a provider supports a capability.
 *
 * @param string $provider   Provider ID.
 * @param string $capability Capability slug.
 * @return bool
 */
function abcc_provider_supports( $provider, $capability ) {
	$provider_config = abcc_get_provider( $provider );

	if ( empty( $provider_config['capabilities'] ) ) {
		return false;
	}

	return ! empty( $provider_config['capabilities'][ $capability ] );
}

/**
 * Check whether a provider supports text generation.
 *
 * @param string $provider Provider ID.
 * @return bool
 */
function abcc_provider_supports_text_generation( $provider ) {
	return abcc_provider_supports( $provider, 'text_generation' );
}

/**
 * Check whether a provider supports image generation.
 *
 * @param string $provider Provider ID.
 * @return bool
 */
function abcc_provider_supports_image_generation( $provider ) {
	return abcc_provider_supports( $provider, 'image_generation' );
}

/**
 * Check whether a provider supports citations.
 *
 * @param string $provider Provider ID.
 * @return bool
 */
function abcc_provider_supports_citations( $provider ) {
	return abcc_provider_supports( $provider, 'citations' );
}

/**
 * Check whether a provider supports audio transcription.
 *
 * @param string $provider Provider ID.
 * @return bool
 */
function abcc_provider_supports_audio_transcription( $provider ) {
	return abcc_provider_supports( $provider, 'audio_transcription' );
}

/**
 * Get the first registered model for a provider.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_default_model( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	if ( empty( $provider_config['text_models'] ) ) {
		return '';
	}

	$models = array_keys( $provider_config['text_models'] );

	return $models[0];
}

/**
 * Get available model options grouped by provider.
 *
 * @return array
 */
function abcc_get_available_text_model_options() {
	$options = array();

	foreach ( abcc_get_provider_registry() as $provider_id => $provider ) {
		if ( empty( $provider['text_models'] ) || ! abcc_provider_supports_text_generation( $provider_id ) ) {
			continue;
		}

		if ( empty( abcc_get_provider_api_key( $provider_id ) ) ) {
			continue;
		}

		$options[ $provider_id ] = array(
			'group'   => $provider['group_label'],
			'options' => $provider['text_models'],
		);
	}

	return apply_filters( 'abcc_model_cost_estimate', $options );
}

/**
 * Get the provider for a text model.
 *
 * @param string $model Model identifier.
 * @return string
 */
function abcc_get_provider_for_model( $model ) {
	$model = ! empty( $model ) ? $model : abcc_get_setting( 'prompt_select' );

	foreach ( abcc_get_provider_registry() as $provider_id => $provider ) {
		if ( isset( $provider['text_models'][ $model ] ) ) {
			return $provider_id;
		}
	}

	// Backward-compatible fallback while older saved models still exist.
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
 * Get a provider credential option name.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_option_name( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	return $provider_config['option_name'] ?? '';
}

/**
 * Get the provider constant name.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_constant_name( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	return $provider_config['constant'] ?? '';
}

/**
 * Get the provider connector id.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_wp_connector_id( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	return $provider_config['wp_connector_id'] ?? '';
}

/**
 * Get a provider connection test callback.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_connection_test_callback( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	return $provider_config['connection_test_callback'] ?? '';
}

/**
 * Get a provider text generation callback.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_text_generation_callback( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	return $provider_config['text_generation_callback'] ?? '';
}

/**
 * Get the current credential source for a provider.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_credential_source( $provider ) {
	if ( ! empty( abcc_get_wp_ai_credential( $provider ) ) ) {
		return 'wp_connector';
	}

	$constant = abcc_get_provider_constant_name( $provider );
	if ( ! empty( $constant ) && defined( $constant ) && constant( $constant ) ) {
		return 'constant';
	}

	$option_name = abcc_get_provider_option_name( $provider );
	if ( ! empty( $option_name ) && get_option( $option_name, '' ) ) {
		return 'option';
	}

	return 'none';
}

/**
 * Get the current provider health snapshot used by admin UI screens.
 *
 * @param string $provider Provider ID.
 * @return array
 */
function abcc_get_provider_health_snapshot( $provider ) {
	$source      = abcc_get_provider_credential_source( $provider );
	$last_check  = get_transient( 'abcc_last_validation_' . $provider );
	$health_rows = get_option( 'abcc_provider_health', array() );

	if ( false === $last_check && ! empty( $health_rows[ $provider ] ) ) {
		$row       = $health_rows[ $provider ];
		$timestamp = isset( $row['timestamp'] ) ? (int) $row['timestamp'] : 0;

		if ( 'connected' === ( $row['status'] ?? '' ) ) {
			$last_check = array(
				'status'    => 'verified',
				'message'   => __( 'Validated automatically', 'automated-blog-content-creator' ),
				'timestamp' => $timestamp,
			);
		} elseif ( 'failed' === ( $row['status'] ?? '' ) ) {
			$last_check = array(
				'status'    => 'failed',
				'message'   => __( 'Connection failed', 'automated-blog-content-creator' ),
				'timestamp' => $timestamp,
			);
		}
	}

	if ( 'none' === $source ) {
		$health = 'no_key';
	} elseif ( empty( $last_check ) ) {
		$health = 'stale';
	} elseif ( 'verified' !== ( $last_check['status'] ?? '' ) ) {
		$health = 'failed';
	} elseif ( ! empty( $last_check['timestamp'] ) && ( time() - (int) $last_check['timestamp'] ) > DAY_IN_SECONDS ) {
		$health = 'stale';
	} else {
		$health = 'connected';
	}

	return array(
		'source'     => $source,
		'health'     => $health,
		'last_check' => $last_check,
	);
}

/**
 * Get a saved provider API key from wp_options only.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_saved_api_key( $provider ) {
	$option_name = abcc_get_provider_option_name( $provider );

	if ( empty( $option_name ) ) {
		return '';
	}

	return get_option( $option_name, '' );
}

/**
 * Save a provider API key to wp_options.
 *
 * @param string $provider Provider ID.
 * @param string $api_key  API key.
 * @return bool
 */
function abcc_set_provider_saved_api_key( $provider, $api_key ) {
	$option_name = abcc_get_provider_option_name( $provider );

	if ( empty( $option_name ) ) {
		return false;
	}

	return update_option( $option_name, $api_key );
}

/**
 * Apply an API-key field submission, treating blank as "leave unchanged".
 *
 * The settings form no longer pre-fills saved keys into the page source, so an
 * untouched field submits empty. Without this, every save would wipe the key.
 *
 * @since 4.4.0
 * @param string $provider    Provider ID.
 * @param string $submitted   Raw submitted value (already sanitized by caller).
 * @return bool True when a new key was written, false when the existing key was kept.
 */
function abcc_save_provider_api_key_submission( $provider, $submitted ) {
	$submitted = trim( (string) $submitted );

	if ( '' === $submitted ) {
		return false;
	}

	return (bool) abcc_set_provider_saved_api_key( $provider, $submitted );
}

/**
 * Run a provider connection test.
 *
 * @param string $provider Provider ID.
 * @param string $api_key  API key.
 * @return array|WP_Error
 */
function abcc_test_provider_connection( $provider, $api_key ) {
	$callback = abcc_get_provider_connection_test_callback( $provider );

	if ( empty( $callback ) || ! is_callable( $callback ) ) {
		return new WP_Error(
			'abcc_invalid_provider',
			__( 'Invalid provider', 'automated-blog-content-creator' )
		);
	}

	return call_user_func( $callback, $api_key );
}

/**
 * Minimal connection test for Stability AI.
 *
 * @param string $api_key API key.
 * @return array
 */
function abcc_test_stability_connection( $api_key ) {
	if ( empty( $api_key ) ) {
		return array(
			'success' => false,
			'error'   => __( 'API key is empty', 'automated-blog-content-creator' ),
		);
	}

	return array( 'success' => true );
}

/**
 * Resolve a model ID to its human-readable name for read-only display.
 *
 * Raw IDs like "gpt-4.1-mini-2025-04-14" mean nothing to most users; the
 * registry already knows the friendly name. Unknown IDs (removed models,
 * custom setups) fall back to the raw ID so nothing renders blank.
 *
 * @since 4.4.0
 * @param string $model_id Model identifier.
 * @return string Friendly name, or the raw ID when unknown.
 */
function abcc_get_model_display_name( $model_id ) {
	$model_id = (string) $model_id;

	if ( '' === $model_id ) {
		return '';
	}

	foreach ( abcc_get_provider_registry() as $provider ) {
		if ( ! empty( $provider['text_models'][ $model_id ]['name'] ) ) {
			return $provider['text_models'][ $model_id ]['name'];
		}
	}

	return $model_id;
}

/**
 * Format a model's display label, appending "(legacy)" when flagged.
 *
 * @since 4.3.0
 * @param string $model_id Model identifier.
 * @param array  $model    Model definition from the registry.
 * @return string Unescaped label (caller must escape).
 */
function abcc_format_model_option_label( $model_id, $model ) {
	if ( is_string( $model ) && '' !== $model ) {
		$label = $model;
	} elseif ( is_array( $model ) && isset( $model['name'] ) ) {
		$label = $model['name'];
	} else {
		$label = (string) $model_id;
	}
	if ( is_array( $model ) && ! empty( $model['legacy'] ) ) {
		$label .= ' ' . __( '(legacy)', 'automated-blog-content-creator' );
	}
	return $label;
}
