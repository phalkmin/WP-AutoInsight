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

	if ( null !== $registry && empty( $GLOBALS['abcc_provider_registry_dirty'] ) ) {
		return $registry;
	}
	unset( $GLOBALS['abcc_provider_registry_dirty'] );

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
			'connection_test_model'    => 'gpt-5.4-mini',
			'transport'                => 'openai_compatible',
			'endpoint'                 => 'https://api.openai.com/v1/chat/completions',
			'auth'                     => 'bearer',
			'extra_headers'            => array(),
			'default_temperature'      => 0.8,
			'timeout'                  => 60,
			'pricing_date'             => '2026-11-05',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => true,
				'citations'           => false,
				'audio_transcription' => true,
			),
			'text_models'              => array(
				// GPT-5.x and GPT-6 take max_completion_tokens and no sampling
				// parameters; the floor guards tiny budgets. Prices are USD per 1M tokens.
				'gpt-5.4-mini'            => array(
					'name'              => 'GPT-5.4 mini',
					'description'       => 'Fast and cost-effective — current default tier',
					'cost_tier'         => '1',
					'cost_per_post'     => 0.0002,
					'context_window'    => 400000,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => false,
					'reasoning_effort'  => 'none',
					'min_output_tokens' => 1000,
					'price_input'       => 0.75,
					'price_output'      => 4.5,
				),
				'gpt-6-luna'              => array(
					'name'              => 'GPT-6 Luna',
					'description'       => 'Newest economy tier — fast and very cheap',
					'cost_tier'         => '1',
					'cost_per_post'     => 0.0001,
					'context_window'    => 1047576,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => false,
					'reasoning_effort'  => 'none',
					'min_output_tokens' => 1000,
					'price_input'       => 0.10,
					'price_output'      => 0.50,
				),
				'gpt-6.1-terra'           => array(
					'name'              => 'GPT-6.1 Terra',
					'description'       => 'Balanced — strong writing at a mid price',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.002,
					'context_window'    => 1047576,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => false,
					'reasoning_effort'  => 'none',
					'min_output_tokens' => 1000,
					'price_input'       => 1.0,
					'price_output'      => 5.0,
				),
				'gpt-5.4'                 => array(
					'name'              => 'GPT-5.4',
					'description'       => 'Balanced performance — best coding and instruction following',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.003,
					'context_window'    => 1047576,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => false,
					'reasoning_effort'  => 'none',
					'min_output_tokens' => 1000,
					'price_input'       => 2.5,
					'price_output'      => 15.0,
				),
				// No reasoning_effort 'none': hidden reasoning bills against the budget.
				'gpt-6.1-sol'             => array(
					'name'              => 'GPT-6.1 Sol',
					'description'       => 'Most capable mid-tier model — always reasons',
					'cost_tier'         => '3',
					'cost_per_post'     => 0.006,
					'context_window'    => 1047576,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => true,
					'reasoning_effort'  => 'low',
					'min_output_tokens' => 4000,
					'price_input'       => 2.0,
					'price_output'      => 10.0,
				),
				'gpt-5.5'                 => array(
					'name'              => 'GPT-5.5',
					'description'       => 'Most capable OpenAI model for complex reasoning',
					'cost_tier'         => '3',
					'cost_per_post'     => 0.015,
					'context_window'    => 1047576,
					'max_output'        => 128000,
					'token_param'       => 'max_completion_tokens',
					'temperature'       => false,
					'reasoning'         => false,
					'reasoning_effort'  => 'none',
					'min_output_tokens' => 1000,
					'price_input'       => 5.0,
					'price_output'      => 30.0,
				),
				'gpt-4.1-mini-2025-04-14' => array(
					'name'           => 'GPT-4.1 Mini',
					'description'    => 'Retires January 14, 2027',
					'cost_tier'      => '1',
					'cost_per_post'  => 0.0002,
					'context_window' => 1047576,
					'max_output'     => 32768,
					'legacy'         => true,
					'retires'        => '2027-01-14',
					'successor'      => 'gpt-6-luna',
					'price_input'    => 0.40,
					'price_output'   => 1.60,
				),
				'gpt-4.1-2025-04-14'      => array(
					'name'           => 'GPT-4.1',
					'description'    => 'Retires January 14, 2027',
					'cost_tier'      => '2',
					'cost_per_post'  => 0.003,
					'context_window' => 1047576,
					'max_output'     => 32768,
					'legacy'         => true,
					'retires'        => '2027-01-14',
					'successor'      => 'gpt-6-luna',
					'price_input'    => 2.0,
					'price_output'   => 8.0,
				),
				'o4-mini'                 => array(
					'name'           => 'o4-mini',
					'description'    => 'Shut down October 23, 2026',
					'context_window' => 200000,
					'legacy'         => true,
					'retires'        => '2026-10-23',
					'successor'      => 'gpt-6-luna',
				),
				'o4-mini-2025-04-16'      => array(
					'name'           => 'o4-mini',
					'description'    => 'Shut down October 23, 2026',
					'context_window' => 200000,
					'legacy'         => true,
					'retires'        => '2026-10-23',
					'successor'      => 'gpt-6-luna',
				),
				'gpt-4.1-nano'            => array(
					'name'           => 'GPT-4.1 Nano',
					'description'    => 'Shut down October 23, 2026',
					'context_window' => 1047576,
					'legacy'         => true,
					'retires'        => '2026-10-23',
					'successor'      => 'gpt-6-luna',
				),
				'gpt-4.1-nano-2025-04-14' => array(
					'name'           => 'GPT-4.1 Nano',
					'description'    => 'Shut down October 23, 2026',
					'context_window' => 1047576,
					'legacy'         => true,
					'retires'        => '2026-10-23',
					'successor'      => 'gpt-6-luna',
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
			'connection_test_model'    => 'claude-haiku-4-5-20251001',
			'transport'                => 'anthropic',
			'endpoint'                 => 'https://api.anthropic.com/v1/messages',
			'auth'                     => 'x-api-key',
			'extra_headers'            => array( 'anthropic-version' => '2023-06-01' ),
			'timeout'                  => 60,
			'pricing_date'             => '2026-11-05',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				// Claude 5.5 thinks adaptively, billed against max_tokens (hence the low effort), and rejects sampling parameters.
				'claude-haiku-5-5'           => array(
					'name'              => 'Claude Haiku 5.5',
					'description'       => 'Fastest and cheapest Claude',
					'cost_tier'         => '1',
					'cost_per_post'     => 0.0002,
					'context_window'    => 1000000,
					'max_output'        => 128000,
					'temperature'       => false,
					'reasoning'         => true,
					'reasoning_effort'  => 'low',
					'min_output_tokens' => 4000,
					'price_input'       => 0.10,
					'price_output'      => 0.50,
				),
				'claude-sonnet-5-5'          => array(
					'name'              => 'Claude Sonnet 5.5',
					'description'       => 'Best combination of speed and intelligence',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.004,
					'context_window'    => 1000000,
					'max_output'        => 128000,
					'temperature'       => false,
					'reasoning'         => true,
					'reasoning_effort'  => 'low',
					'min_output_tokens' => 4000,
					'price_input'       => 2.0,
					'price_output'      => 10.0,
				),
				'claude-opus-5-5'            => array(
					'name'              => 'Claude Opus 5.5',
					'description'       => 'Most capable Claude for complex writing',
					'cost_tier'         => '3',
					'cost_per_post'     => 0.01,
					'context_window'    => 1000000,
					'max_output'        => 128000,
					'temperature'       => false,
					'reasoning'         => true,
					'reasoning_effort'  => 'low',
					'min_output_tokens' => 4000,
					'price_input'       => 4.0,
					'price_output'      => 20.0,
				),
				'claude-haiku-4-5-20251001'  => array(
					'name'           => 'Claude Haiku 4.5',
					'description'    => 'Previous generation; may be retired on 60 days notice',
					'cost_tier'      => '1',
					'cost_per_post'  => 0.0003,
					'context_window' => 200000,
					'max_output'     => 64000,
					'legacy'         => true,
					'successor'      => 'claude-haiku-5-5',
					'price_input'    => 1.0,
					'price_output'   => 5.0,
				),
				'claude-sonnet-4-6'          => array(
					'name'           => 'Claude Sonnet 4.6',
					'description'    => 'Previous generation',
					'cost_tier'      => '2',
					'cost_per_post'  => 0.004,
					'context_window' => 1000000,
					'max_output'     => 128000,
					'legacy'         => true,
					'successor'      => 'claude-sonnet-5-5',
					'price_input'    => 3.0,
					'price_output'   => 15.0,
				),
				'claude-opus-4-8'            => array(
					'name'           => 'Claude Opus 4.8',
					'description'    => 'Previous generation',
					'cost_tier'      => '3',
					'cost_per_post'  => 0.015,
					'context_window' => 1000000,
					'max_output'     => 128000,
					'legacy'         => true,
					'successor'      => 'claude-opus-5-5',
					'price_input'    => 5.0,
					'price_output'   => 25.0,
				),
				'claude-sonnet-4-5'          => array(
					'name'           => 'Claude Sonnet 4.5',
					'description'    => 'Retires November 30, 2026',
					'context_window' => 200000,
					'max_output'     => 64000,
					'legacy'         => true,
					'retires'        => '2026-11-30',
					'successor'      => 'claude-sonnet-5-5',
					'price_input'    => 3.0,
					'price_output'   => 15.0,
				),
				'claude-sonnet-4-5-20250929' => array(
					'name'           => 'Claude Sonnet 4.5 (2025-09-29)',
					'description'    => 'Retires November 30, 2026',
					'context_window' => 200000,
					'max_output'     => 64000,
					'legacy'         => true,
					'retires'        => '2026-11-30',
					'successor'      => 'claude-sonnet-5-5',
					'price_input'    => 3.0,
					'price_output'   => 15.0,
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
			'connection_test_model'    => 'gemini-2.5-flash-lite',
			'default_model'            => 'gemini-3.5-flash-lite',
			'transport'                => 'gemini',
			'endpoint'                 => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
			'auth'                     => 'x-goog-api-key',
			'extra_headers'            => array(),
			'timeout'                  => 60,
			'pricing_date'             => '2026-11-05',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => true,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				// Gemini 2.5 takes thinking_budget (0 = off). Gemini 3+ rejects it,
				// takes thinking_level and cannot turn thinking off.
				'gemini-2.5-flash-lite' => array(
					'name'            => 'Gemini 2.5 Flash-Lite',
					'description'     => 'Cheapest model — still served, no retirement announced',
					'cost_tier'       => '1',
					'cost_per_post'   => 0.0001,
					'context_window'  => 1048576,
					'max_output'      => 65536,
					'thinking_budget' => 0,
					'price_input'     => 0.10,
					'price_output'    => 0.40,
				),
				'gemini-3.5-flash-lite' => array(
					'name'              => 'Gemini 3.5 Flash-Lite',
					'description'       => 'Current budget tier with light reasoning',
					'cost_tier'         => '1',
					'cost_per_post'     => 0.0003,
					'context_window'    => 1048576,
					'max_output'        => 65536,
					'thinking_level'    => 'minimal',
					'min_output_tokens' => 1024,
					'price_input'       => 0.30,
					'price_output'      => 2.50,
				),
				'gemini-3.8-flash'      => array(
					'name'              => 'Gemini 3.8 Flash',
					'description'       => 'Current Flash — best price-performance',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.0008,
					'context_window'    => 1048576,
					'max_output'        => 65536,
					'thinking_level'    => 'low',
					'min_output_tokens' => 2048,
					'price_input'       => 0.75,
					'price_output'      => 3.75,
				),
				'gemini-3.6-flash'      => array(
					'name'              => 'Gemini 3.6 Flash',
					'description'       => 'Previous Flash generation',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.0008,
					'context_window'    => 1048576,
					'max_output'        => 65536,
					'thinking_level'    => 'minimal',
					'min_output_tokens' => 1024,
					'price_input'       => 0.75,
					'price_output'      => 3.75,
				),
				'gemini-2.5-flash'      => array(
					'name'            => 'Gemini 2.5 Flash',
					'description'     => 'Google routes it to 3.5 Flash-Lite from December 1, 2026',
					'cost_tier'       => '2',
					'cost_per_post'   => 0.0002,
					'context_window'  => 1048576,
					'max_output'      => 65536,
					'thinking_budget' => 0,
					'legacy'          => true,
					'retires'         => '2026-12-01',
					'successor'       => 'gemini-3.5-flash-lite',
					'price_input'     => 0.30,
					'price_output'    => 2.50,
				),
				'gemini-2.5-pro'        => array(
					'name'            => 'Gemini 2.5 Pro',
					'description'     => 'Google routes it to 3.8 Flash from December 1, 2026',
					'cost_tier'       => '3',
					'cost_per_post'   => 0.002,
					'context_window'  => 1048576,
					'max_output'      => 65536,
					'thinking_budget' => 128,
					'legacy'          => true,
					'retires'         => '2026-12-01',
					'successor'       => 'gemini-3.8-flash',
					'price_input'     => 1.25,
					'price_output'    => 10.0,
				),
				// Google already routes this ID to 3.6 Flash; the swap just makes it explicit.
				'gemini-3.5-flash'      => array(
					'name'           => 'Gemini 3.5 Flash',
					'description'    => 'Routed by Google to Gemini 3.6 Flash',
					'cost_tier'      => '2',
					'cost_per_post'  => 0.0008,
					'context_window' => 1048576,
					'max_output'     => 65536,
					'legacy'         => true,
					'retires'        => '2026-11-05',
					'successor'      => 'gemini-3.6-flash',
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
			'connection_test_model'    => 'sonar',
			'transport'                => 'openai_compatible',
			'endpoint'                 => 'https://api.perplexity.ai/chat/completions',
			'auth'                     => 'bearer',
			'extra_headers'            => array( 'X-Pplx-Integration' => 'wp-autoinsight' ),
			'request_body_callback'    => 'abcc_perplexity_request_body',
			'timeout'                  => 60,
			'pricing_date'             => '2026-11-05',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => true,
				'audio_transcription' => false,
			),
			'text_models'              => array(
				// Sonar chat completions ended 2026-09-27; Perplexity reformulates
				// these requests server-side. Search fees are not in the token prices.
				'sonar'               => array(
					'name'              => 'Sonar',
					'description'       => 'Fast web-grounded search with citations',
					'cost_tier'         => '1',
					'cost_per_post'     => 0.001,
					'context_window'    => 128000,
					'min_output_tokens' => 800,
					'price_input'       => 0.25,
					'price_output'      => 2.50,
				),
				'sonar-pro'           => array(
					'name'              => 'Sonar Pro',
					'description'       => 'Deeper context with 2x more search results',
					'cost_tier'         => '2',
					'cost_per_post'     => 0.005,
					'context_window'    => 200000,
					'min_output_tokens' => 800,
					'legacy'            => true,
				),
				'sonar-reasoning-pro' => array(
					'name'              => 'Sonar Reasoning Pro',
					'description'       => 'Advanced multi-step reasoning with citations',
					'cost_tier'         => '3',
					'cost_per_post'     => 0.01,
					'context_window'    => 128000,
					'reasoning'         => true,
					'min_output_tokens' => 800,
					'legacy'            => true,
				),
				'sonar-deep-research' => array(
					'name'              => 'Sonar Deep Research',
					'description'       => 'In-depth multi-source synthesis — charges per citation + reasoning token',
					'cost_tier'         => '3',
					'cost_per_post'     => 0.82,
					'cost_warning'      => true,
					'context_window'    => 128000,
					'reasoning'         => true,
					'min_output_tokens' => 800,
					'timeout'           => 300,
					'legacy'            => true,
				),
			),
		),
		// A gateway: any catalog model may be sent, so unlisted IDs are expected.
		'openrouter' => array(
			'id'                       => 'openrouter',
			'name'                     => 'OpenRouter',
			'group_label'              => 'OpenRouter Models',
			'help_url'                 => 'https://openrouter.ai/settings/keys',
			'constant'                 => 'OPENROUTER_API',
			'option_name'              => 'openrouter_api_key',
			'wp_connector_id'          => '',
			'text_generation_callback' => '',
			'connection_test_callback' => '',
			'connection_test_model'    => 'openai/gpt-6-luna',
			'transport'                => 'openai_compatible',
			'endpoint'                 => 'https://openrouter.ai/api/v1/chat/completions',
			'auth'                     => 'bearer',
			'extra_headers'            => abcc_get_openrouter_headers(),
			'request_body_callback'    => 'abcc_openrouter_request_body',
			'cost_note_callback'       => 'abcc_get_openrouter_cost_note',
			'gateway'                  => true,
			'default_context_window'   => 16384,
			'timeout'                  => 120,
			'pricing_date'             => '2026-11-10',
			'capabilities'             => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'              => abcc_get_openrouter_registry_models(),
		),
		'custom'     => abcc_get_custom_endpoint_provider_entry(),
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

	$registry = abcc_merge_custom_models( $registry );
	$registry = abcc_openrouter_overlay_live_models( $registry );

	$registry = apply_filters( 'abcc_provider_registry', $registry );

	return $registry;
}

/**
 * Rebuild the registry on its next read.
 *
 * Needed when something that feeds the abcc_provider_registry filter changes
 * within a request (a saved custom model, a refreshed catalog).
 *
 * @since 4.6.0
 * @return void
 */
function abcc_flush_provider_registry_cache() {
	$GLOBALS['abcc_provider_registry_dirty'] = true;
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
 * The model a provider starts new users on.
 *
 * The registry's `default_model` when it names a registered text model,
 * otherwise the first model listed.
 *
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_default_model( $provider ) {
	$provider_config = abcc_get_provider( $provider );

	if ( empty( $provider_config['text_models'] ) ) {
		return '';
	}

	$default = isset( $provider_config['default_model'] ) ? (string) $provider_config['default_model'] : '';
	if ( '' !== $default && isset( $provider_config['text_models'][ $default ] ) ) {
		return $default;
	}

	$models = array_keys( $provider_config['text_models'] );

	return (string) $models[0];
}

/**
 * Hidden-reasoning budget a model needs, or null when it has no such parameter.
 *
 * 0 turns thinking off; a positive value is the smallest the model accepts
 * and must be added to the output ceiling or it eats the visible budget.
 *
 * @since 4.5.2
 * @param string $provider Provider ID.
 * @param string $model    Model ID.
 * @return int|null
 */
function abcc_get_model_thinking_budget( $provider, $model ) {
	$config = abcc_get_provider( $provider );
	if ( isset( $config['text_models'][ $model ]['thinking_budget'] ) ) {
		return (int) $config['text_models'][ $model ]['thinking_budget'];
	}

	return null;
}

/**
 * Cheapest registered model to use for a 1-token connection test.
 *
 * @since 4.5.2
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_connection_test_model( $provider ) {
	$config = abcc_get_provider( $provider );
	if ( ! empty( $config['connection_test_model'] ) ) {
		return (string) $config['connection_test_model'];
	}

	return abcc_get_provider_default_model( $provider );
}

/**
 * The model used when no model is stored or passed.
 *
 * @since 4.5.2
 * @return string
 */
function abcc_get_default_text_model() {
	return (string) abcc_get_setting_default( 'prompt_select', 'openai::gpt-5.4-mini' );
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

		if ( ! abcc_provider_is_ready( $provider_id ) ) {
			continue;
		}

		// Retired models are hidden here and swapped for the successor at call time.
		$models = array();
		foreach ( $provider['text_models'] as $model_id => $model ) {
			if ( abcc_resolve_retired_model( $provider_id, $model_id ) === $model_id ) {
				$models[ $model_id ] = $model;
			}
		}

		if ( empty( $models ) ) {
			continue;
		}

		$options[ $provider_id ] = array(
			'group'   => $provider['group_label'],
			'options' => $models,
		);
	}

	return apply_filters( 'abcc_model_cost_estimate', $options );
}

/**
 * Split a stored model selection into provider and model.
 *
 * 'provider::model' resolves through the registry only; the model may be
 * unlisted (a catalog pick). A bare ID resolves by registry membership, then
 * by the legacy substring guess, never for IDs containing '/'.
 *
 * @since 4.6.0
 * @param string $selection Stored selection or bare model ID.
 * @return array{provider: string, model: string, qualified: bool, known: bool}
 *               'provider' is '' when nothing resolves; 'known' is true only
 *               when the model is listed under the resolved provider.
 */
function abcc_parse_model_selection( $selection ) {
	$selection = trim( (string) $selection );
	$result    = array(
		'provider'  => '',
		'model'     => $selection,
		'qualified' => false,
		'known'     => false,
	);

	if ( '' === $selection ) {
		return $result;
	}

	if ( false !== strpos( $selection, '::' ) ) {
		list( $provider, $model ) = explode( '::', $selection, 2 );
		$provider                 = sanitize_key( $provider );
		$model                    = trim( $model );

		$result['qualified'] = true;
		$result['model']     = $model;

		if ( '' === $model || ! abcc_provider_supports_text_generation( $provider ) ) {
			return $result;
		}

		$config             = abcc_get_provider( $provider );
		$result['provider'] = $provider;
		$result['known']    = isset( $config['text_models'][ $model ] );

		return $result;
	}

	foreach ( abcc_get_provider_registry() as $provider_id => $provider ) {
		if ( isset( $provider['text_models'][ $selection ] ) && abcc_provider_supports_text_generation( $provider_id ) ) {
			$result['provider'] = $provider_id;
			$result['known']    = true;
			return $result;
		}
	}

	// A '/' marks a gateway catalog ID; guessing would route it to the native key.
	if ( false !== strpos( $selection, '/' ) ) {
		return $result;
	}

	$result['provider'] = abcc_guess_provider_from_model_id( $selection );

	return $result;
}

/**
 * Legacy substring guess for bare model IDs saved before the registry listed them.
 *
 * @since 4.6.0
 * @param string $model Bare model ID.
 * @return string Provider ID or ''.
 */
function abcc_guess_provider_from_model_id( $model ) {
	$model = (string) $model;

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
 * Build the stored form of a model selection.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model    Bare model ID.
 * @return string 'provider::model', or '' when either part is empty.
 */
function abcc_build_model_selection( $provider, $model ) {
	$provider = sanitize_key( (string) $provider );
	$model    = trim( (string) $model );

	if ( '' === $provider || '' === $model ) {
		return '';
	}

	return $provider . '::' . $model;
}

/**
 * Rewrite a selection to its qualified form when the provider resolves.
 *
 * Unknown strings pass through unchanged so they fail downstream exactly as
 * they do today instead of being silently dropped.
 *
 * @since 4.6.0
 * @param string $selection Stored selection or bare model ID.
 * @return string
 */
function abcc_normalize_model_selection( $selection ) {
	$parsed = abcc_parse_model_selection( $selection );

	if ( '' === $parsed['provider'] ) {
		return trim( (string) $selection );
	}

	return abcc_build_model_selection( $parsed['provider'], $parsed['model'] );
}

/**
 * Bare model ID of a selection, for request bodies and registry lookups.
 *
 * @since 4.6.0
 * @param string $selection Stored selection or bare model ID.
 * @return string
 */
function abcc_get_model_id_from_selection( $selection ) {
	$parsed = abcc_parse_model_selection( $selection );

	return $parsed['model'];
}

/**
 * Registry definition of a text model, or null when it is not listed.
 *
 * @since 4.6.0
 * @param string $selection Stored selection or bare model ID.
 * @param string $provider  Optional provider ID when the caller already knows it.
 * @return array|null
 */
function abcc_get_model_definition( $selection, $provider = '' ) {
	$parsed = abcc_parse_model_selection( $selection );
	$pid    = '' !== $provider ? sanitize_key( (string) $provider ) : $parsed['provider'];
	$config = abcc_get_provider( $pid );

	if ( ! $config || ! isset( $config['text_models'][ $parsed['model'] ] ) || ! is_array( $config['text_models'][ $parsed['model'] ] ) ) {
		return null;
	}

	return $config['text_models'][ $parsed['model'] ];
}

/**
 * The clock retirement checks use.
 *
 * @since 4.5.3
 * @return int Unix time.
 */
function abcc_get_retirement_now() {
	/**
	 * Filter the time retirement dates are compared against.
	 *
	 * @since 4.5.3
	 * @param int $now Unix time.
	 */
	return (int) apply_filters( 'abcc_retirement_now', time() );
}

/**
 * The model the provider still serves for a stored selection.
 *
 * Past its 'retires' date (Y-m-d) a model is swapped for its 'successor', so
 * saved settings and scheduled topics survive a provider shutdown.
 *
 * @since 4.6.0
 * @param string   $provider Provider ID.
 * @param string   $model    Model ID or selection.
 * @param int|null $now      Unix time; null means now.
 * @return string Bare model ID.
 */
function abcc_resolve_retired_model( $provider, $model, $now = null ) {
	$model = abcc_get_model_id_from_selection( $model );
	$now   = null === $now ? abcc_get_retirement_now() : (int) $now;

	for ( $hops = 0; $hops < 3; $hops++ ) {
		$definition = abcc_get_model_definition( $model, $provider );
		if ( empty( $definition['retires'] ) || empty( $definition['successor'] ) ) {
			return $model;
		}

		$retires = strtotime( (string) $definition['retires'] . ' UTC' );
		if ( false === $retires || $now < $retires ) {
			return $model;
		}

		$model = (string) $definition['successor'];
	}

	return $model;
}

/**
 * A stored selection with a retired model replaced by its successor, qualified.
 *
 * Pickers and the validator compare against this, so a retired saved model
 * is never replaced with another provider's first model.
 *
 * @since 4.6.0
 * @param string $selection Stored selection or bare model ID.
 * @return string 'provider::model', or the trimmed input when it does not resolve.
 */
function abcc_get_live_model_selection( $selection ) {
	$parsed = abcc_parse_model_selection( $selection );

	if ( '' === $parsed['provider'] ) {
		return trim( (string) $selection );
	}

	return abcc_build_model_selection( $parsed['provider'], abcc_resolve_retired_model( $parsed['provider'], $parsed['model'] ) );
}

/**
 * Selection to start on from a grouped options array (as abcc_get_available_text_model_options() returns).
 *
 * The first provider's default model when it is offered, otherwise its first option.
 *
 * @since 4.6.0
 * @param array $grouped_options provider_id => array( 'group' => string, 'options' => array ).
 * @return string 'provider::model', or '' when nothing is available.
 */
function abcc_get_first_available_model_selection( array $grouped_options ) {
	foreach ( $grouped_options as $provider_id => $group ) {
		if ( ! empty( $group['options'] ) && is_array( $group['options'] ) ) {
			$default = abcc_get_provider_default_model( $provider_id );
			if ( '' !== $default && isset( $group['options'][ $default ] ) ) {
				return abcc_build_model_selection( $provider_id, $default );
			}

			reset( $group['options'] );
			return abcc_build_model_selection( $provider_id, key( $group['options'] ) );
		}
	}

	return '';
}

/**
 * Get the provider for a text model.
 *
 * @param string $model Model identifier or 'provider::model' selection.
 * @return string
 */
function abcc_get_provider_for_model( $model ) {
	$model  = ! empty( $model ) ? $model : abcc_get_setting( 'prompt_select' );
	$parsed = abcc_parse_model_selection( $model );

	return $parsed['provider'];
}

/**
 * Whether a selection names a listed text model of a text-capable provider.
 *
 * Membership only: no substring guessing, so a made-up ID that contains a
 * provider word is rejected.
 *
 * @since 4.5.2
 * @param string $model Model ID or 'provider::model' selection.
 * @return bool
 */
function abcc_is_valid_text_model( $model ) {
	$parsed = abcc_parse_model_selection( $model );

	return '' !== $parsed['provider'] && $parsed['known'];
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
 * @param string     $provider    Provider ID.
 * @param array|null $health_rows Optional. Pre-fetched abcc_provider_health option so
 *                                per-provider loops read it once instead of per card.
 * @return array
 */
function abcc_get_provider_health_snapshot( $provider, $health_rows = null ) {
	$source = abcc_get_provider_credential_source( $provider );
	// A keyless local endpoint has no credential but is still usable.
	if ( 'none' === $source && abcc_provider_is_ready( $provider ) ) {
		$source = 'keyless';
	}
	$last_check = get_transient( 'abcc_last_validation_' . $provider );

	if ( ! is_array( $health_rows ) ) {
		$health_rows = get_option( 'abcc_provider_health', array() );
	}

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
				'message'   => ! empty( $row['message'] ) ? (string) $row['message'] : __( 'Connection failed', 'automated-blog-content-creator' ),
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
 * Record a provider validation outcome in both health stores.
 *
 * The transient drives the badges and the dashboard; the option row is what
 * the daily check reads back. Writing both here keeps them from disagreeing.
 *
 * @since 4.5.2
 * @param string $provider Provider ID: a lowercase registry key (sanitize_key form), as for every health writer.
 * @param bool   $ok       Whether the key worked.
 * @param string $message  Optional user-facing detail; secrets are redacted.
 * @param int    $ttl      Transient lifetime in seconds.
 * @return void
 */
function abcc_record_provider_validation( $provider, $ok, $message = '', $ttl = HOUR_IN_SECONDS ) {
	$provider = sanitize_key( (string) $provider );
	if ( ! in_array( $provider, abcc_get_provider_ids(), true ) ) {
		return;
	}

	if ( '' === $message ) {
		$message = $ok
			? __( 'Verified just now', 'automated-blog-content-creator' )
			: __( 'Connection failed', 'automated-blog-content-creator' );
	} elseif ( function_exists( 'abcc_redact_secrets' ) ) {
		// Provider error text can echo the key; the badge shows this message.
		$message = abcc_redact_secrets( $message );
	}

	$now = time();

	set_transient(
		'abcc_last_validation_' . $provider,
		array(
			'status'    => $ok ? 'verified' : 'failed',
			'message'   => sanitize_text_field( $message ),
			'timestamp' => $now,
		),
		(int) $ttl
	);

	$rows = get_option( 'abcc_provider_health', array() );
	$rows = is_array( $rows ) ? $rows : array();

	// The failure reason outlives the transient so the dashboard can still say why.
	$rows[ $provider ] = array(
		'status'    => $ok ? 'connected' : 'failed',
		'timestamp' => $now,
		'message'   => $ok ? '' : sanitize_text_field( $message ),
	);
	update_option( 'abcc_provider_health', $rows );
}

/**
 * Record that a provider has no key: a no_key health row, no cached validation.
 *
 * @since 4.5.2
 * @param string $provider Provider ID.
 * @return void
 */
function abcc_record_provider_no_key( $provider ) {
	$provider = sanitize_key( (string) $provider );
	if ( ! in_array( $provider, abcc_get_provider_ids(), true ) ) {
		return;
	}

	delete_transient( 'abcc_last_validation_' . $provider );

	$rows = get_option( 'abcc_provider_health', array() );
	$rows = is_array( $rows ) ? $rows : array();

	$rows[ $provider ] = array(
		'status'    => 'no_key',
		'timestamp' => time(),
	);
	update_option( 'abcc_provider_health', $rows );
}

/**
 * Feed a generation outcome into provider health.
 *
 * Only an auth error proves the key is bad; outages and rate limits are
 * ignored. A success writes only when the stored state isn't already
 * "verified" within the hour, so scheduled sites don't pay a DB write per call.
 *
 * @since 4.5.2
 * @param string        $provider Provider that served (or refused) the call.
 * @param WP_Error|null $error    Normalized error from the wrapper, or null.
 * @return void
 */
function abcc_note_provider_outcome( $provider, $error ) {
	$provider = sanitize_key( (string) $provider );
	if ( '' === $provider ) {
		return;
	}

	if ( is_wp_error( $error ) ) {
		// A 403 means no access to that model, not a bad key; only a 401 rejects the key.
		$data   = (array) $error->get_error_data();
		$status = isset( $data['status'] ) ? (int) $data['status'] : 401;
		if ( 'abcc_provider_auth_error' === $error->get_error_code() && 401 === $status ) {
			abcc_record_provider_validation( $provider, false, __( 'The provider rejected the API key during generation.', 'automated-blog-content-creator' ) );
		}
		return;
	}

	$current = get_transient( 'abcc_last_validation_' . $provider );
	if ( is_array( $current )
		&& 'verified' === ( $current['status'] ?? '' )
		&& ! empty( $current['timestamp'] )
		&& ( time() - (int) $current['timestamp'] ) < HOUR_IN_SECONDS ) {
		return;
	}

	abcc_record_provider_validation( $provider, true, __( 'Verified by a successful generation', 'automated-blog-content-creator' ) );
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
 * Delete a provider API key stored in wp_options and forget its validation.
 *
 * Only wp_options keys are removable here; wp-config constants and WP
 * Connectors credentials are managed outside the plugin.
 *
 * @since 4.5.0
 * @param string $provider Provider ID.
 * @return bool True when the option was cleared.
 */
function abcc_delete_provider_saved_api_key( $provider ) {
	$cleared = abcc_set_provider_saved_api_key( $provider, '' );
	delete_transient( 'abcc_last_validation_' . $provider );

	return $cleared;
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
		// Providers on a transport need no callback of their own.
		if ( '' !== abcc_get_provider_transport( $provider ) ) {
			return abcc_test_transport_connection( $provider, $api_key );
		}

		return new WP_Error(
			'abcc_invalid_provider',
			__( 'Invalid provider', 'automated-blog-content-creator' )
		);
	}

	return call_user_func( $callback, $api_key );
}

/**
 * Connection test for any provider on a transport: reachable, key accepted, model answers.
 *
 * A key with no credits is still a valid key, so 402 passes with a warning.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $api_key  API key ('' for keyless local endpoints).
 * @return array{success: bool, error?: string, message?: string, probe: array}
 */
function abcc_test_transport_connection( $provider, $api_key ) {
	$probe = abcc_test_provider_generation( $provider, $api_key );
	$name  = abcc_get_fallback_provider_label( $provider );

	if ( $probe['success'] ) {
		return array(
			'success' => true,
			'probe'   => $probe,
		);
	}

	if ( 'abcc_provider_payment_required' === $probe['code'] ) {
		return array(
			'success' => true,
			'message' => __( 'The key is valid but has no credits: paid models will fail until you add some.', 'automated-blog-content-creator' ),
			'probe'   => $probe,
		);
	}

	if ( ! $probe['reachable'] ) {
		/* translators: 1: provider name, 2: error detail */
		$error = sprintf( __( 'Could not reach %1$s: %2$s', 'automated-blog-content-creator' ), $name, $probe['error'] );
	} elseif ( ! $probe['authenticated'] ) {
		/* translators: %s: provider name */
		$error = sprintf( __( '%s rejected the key. Check that it was copied in full and is still active.', 'automated-blog-content-creator' ), $name );
	} else {
		/* translators: 1: provider name, 2: error detail */
		$error = sprintf( __( '%1$s accepted the key, but the test model did not answer: %2$s', 'automated-blog-content-creator' ), $name, $probe['error'] );
	}

	return array(
		'success' => false,
		'error'   => function_exists( 'abcc_redact_secrets' ) ? abcc_redact_secrets( $error ) : $error,
		'probe'   => $probe,
	);
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

	$definition = abcc_get_model_definition( $model_id );
	if ( ! empty( $definition['name'] ) ) {
		return $definition['name'];
	}

	return abcc_get_model_id_from_selection( $model_id );
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
	if ( is_array( $model ) && ! empty( $model['free'] ) ) {
		$label = abcc_get_free_model_label( $label );
	}
	return $label;
}

/**
 * Label for a free model: its name plus "(free, experimental)".
 *
 * Catalog names of free variants already end in "(free)"; that copy is dropped.
 *
 * @since 4.6.0
 * @param string $name Model name.
 * @return string Unescaped label (caller must escape).
 */
function abcc_get_free_model_label( $name ) {
	$name = (string) preg_replace( '/\s*\(free\)\s*$/i', '', (string) $name );

	return $name . ' ' . __( '(free, experimental)', 'automated-blog-content-creator' );
}
