<?php
/**
 * OpenRouter: curated shortlist, cached catalog, request shaping.
 *
 * The provider entry itself lives in the registry (includes/providers.php).
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The fixed shortlist offered without a catalog lookup.
 *
 * Reviewed at each release's model audit; rankings never change it silently.
 * Values are the catalog's on 2026-11-10; a cached catalog overrides them.
 *
 * @since 4.6.0
 * @return array Model ID => definition.
 */
function abcc_get_openrouter_shortlist() {
	return array(
		'openai/gpt-6-luna'            => array(
			'name'                     => 'OpenAI: GPT-6 Luna',
			'context_window'           => 1050000,
			'max_output'               => 128000,
			'structured_output'        => true,
			'temperature'              => false,
			'reasoning'                => false,
			'min_output_tokens'        => 1000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'none',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.1,
			'price_output'             => 0.5,
		),
		'anthropic/claude-haiku-5.5'   => array(
			'name'                     => 'Anthropic: Claude Haiku 5.5',
			'context_window'           => 1000000,
			'max_output'               => 128000,
			'structured_output'        => true,
			'temperature'              => false,
			'reasoning'                => false,
			'min_output_tokens'        => 1000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'none',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.1,
			'price_output'             => 0.5,
		),
		'google/gemini-3.5-flash-lite' => array(
			'name'                     => 'Google: Gemini 3.5 Flash Lite',
			'context_window'           => 1048576,
			'max_output'               => 65536,
			'structured_output'        => true,
			'temperature'              => true,
			'reasoning'                => true,
			'min_output_tokens'        => 4000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'minimal',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.3,
			'price_output'             => 2.5,
		),
		'deepseek/deepseek-v4-flash'   => array(
			'name'                     => 'DeepSeek: DeepSeek V4 Flash 0423',
			'context_window'           => 1048576,
			'max_output'               => 943717,
			'structured_output'        => true,
			'temperature'              => true,
			'reasoning'                => false,
			'min_output_tokens'        => 1000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'none',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.091,
			'price_output'             => 0.182,
		),
		'meta-llama/llama-4-maverick'  => array(
			'name'              => 'Meta: Llama 4 Maverick',
			'context_window'    => 524288,
			'max_output'        => 32768,
			'structured_output' => true,
			'temperature'       => true,
			'reasoning'         => false,
			'min_output_tokens' => 0,
			'pricing_source'    => 'registry',
			'pricing_date'      => '2026-11-10',
			'price_input'       => 0.35,
			'price_output'      => 1,
		),
		'mistralai/mistral-small-2603' => array(
			'name'                     => 'Mistral: Mistral Small 4',
			'context_window'           => 262144,
			'max_output'               => 209715,
			'structured_output'        => true,
			'temperature'              => true,
			'reasoning'                => false,
			'min_output_tokens'        => 1000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'none',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.15,
			'price_output'             => 0.6,
		),
		'qwen/qwen3.8-flash'           => array(
			'name'              => 'Qwen: Qwen3.8 Flash',
			'context_window'    => 1000000,
			'max_output'        => 131072,
			'structured_output' => true,
			'temperature'       => true,
			'reasoning'         => true,
			'min_output_tokens' => 4000,
			'pricing_source'    => 'registry',
			'pricing_date'      => '2026-11-10',
			'price_input'       => 0.15,
			'price_output'      => 0.47,
		),
		'openai/gpt-6.1-terra'         => array(
			'name'                     => 'OpenAI: GPT-6.1 Terra',
			'context_window'           => 1050000,
			'max_output'               => 128000,
			'structured_output'        => true,
			'temperature'              => false,
			'reasoning'                => false,
			'min_output_tokens'        => 1000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'none',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 1,
			'price_output'             => 5,
		),
		'google/gemini-3.8-flash'      => array(
			'name'                     => 'Google: Gemini 3.8 Flash',
			'context_window'           => 1048576,
			'max_output'               => 65536,
			'structured_output'        => true,
			'temperature'              => true,
			'reasoning'                => true,
			'min_output_tokens'        => 4000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'low',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 0.825,
			'price_output'             => 4.125,
		),
		'anthropic/claude-sonnet-5.5'  => array(
			'name'                     => 'Anthropic: Claude Sonnet 5.5',
			'context_window'           => 1000000,
			'max_output'               => 128000,
			'structured_output'        => true,
			'temperature'              => false,
			'reasoning'                => true,
			'min_output_tokens'        => 4000,
			'pricing_source'           => 'registry',
			'gateway_reasoning_effort' => 'low',
			'pricing_date'             => '2026-11-10',
			'price_input'              => 2,
			'price_output'             => 10,
		),
	);
}

/**
 * Lowest reasoning effort a catalog row lets us ask for, and whether the model still reasons.
 *
 * 'none' turns hidden reasoning off; without it the lowest available effort
 * keeps cost down. A model that reasons by default with no effort control is
 * budgeted as a reasoning model and sent no effort.
 *
 * @since 4.6.0
 * @param mixed $reasoning The row's 'reasoning' object, or null.
 * @return array{effort: string, reasoning: bool, capable: bool}
 */
function abcc_openrouter_resolve_reasoning( $reasoning ) {
	$result = array(
		'effort'    => '',
		'reasoning' => false,
		'capable'   => false,
	);

	if ( ! is_array( $reasoning ) ) {
		return $result;
	}

	$result['capable'] = true;
	$efforts           = isset( $reasoning['supported_efforts'] ) && is_array( $reasoning['supported_efforts'] ) ? $reasoning['supported_efforts'] : array();
	$active            = ! empty( $reasoning['mandatory'] ) || ! empty( $reasoning['default_enabled'] );

	if ( in_array( 'none', $efforts, true ) && empty( $reasoning['mandatory'] ) ) {
		$result['effort'] = 'none';
		return $result;
	}

	if ( ! $active ) {
		return $result;
	}

	$result['reasoning'] = true;
	foreach ( array( 'minimal', 'low' ) as $effort ) {
		if ( in_array( $effort, $efforts, true ) ) {
			$result['effort'] = $effort;
			break;
		}
	}

	return $result;
}

/**
 * Reduce one catalog row to a registry model definition.
 *
 * Only text-out models are kept. A negative price means "varies" (routers),
 * so no price is stored and estimates read "cost unknown".
 *
 * @since 4.6.0
 * @param mixed  $row          Decoded catalog row.
 * @param string $pricing_date Y-m-d the catalog was fetched.
 * @return array|null Definition, or null when the row is not a usable text model.
 */
function abcc_openrouter_model_from_catalog_row( $row, $pricing_date = '' ) {
	if ( ! is_array( $row ) || empty( $row['id'] ) || ! is_string( $row['id'] ) || ! abcc_is_valid_custom_model_id( $row['id'] ) ) {
		return null;
	}

	$outputs = isset( $row['architecture']['output_modalities'] ) && is_array( $row['architecture']['output_modalities'] ) ? $row['architecture']['output_modalities'] : array( 'text' );
	if ( array( 'text' ) !== array_values( $outputs ) ) {
		return null;
	}

	$id        = $row['id'];
	$supported = isset( $row['supported_parameters'] ) && is_array( $row['supported_parameters'] ) ? $row['supported_parameters'] : array();
	$reasoning = abcc_openrouter_resolve_reasoning( isset( $row['reasoning'] ) ? $row['reasoning'] : null );

	$definition = array(
		'name'              => isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : $id,
		'context_window'    => isset( $row['context_length'] ) ? max( 1024, (int) $row['context_length'] ) : 8192,
		'max_output'        => isset( $row['top_provider']['max_completion_tokens'] ) ? max( 0, (int) $row['top_provider']['max_completion_tokens'] ) : 0,
		'structured_output' => in_array( 'structured_outputs', $supported, true ),
		'temperature'       => in_array( 'temperature', $supported, true ),
		'reasoning'         => $reasoning['reasoning'],
		// A floor guards title-sized budgets on anything that can reason.
		'min_output_tokens' => $reasoning['reasoning'] ? 4000 : ( $reasoning['capable'] ? 1000 : 0 ),
		'pricing_source'    => 'catalog',
	);

	if ( '' !== $reasoning['effort'] ) {
		$definition['gateway_reasoning_effort'] = $reasoning['effort'];
	}
	if ( '' !== (string) $pricing_date ) {
		$definition['pricing_date'] = (string) $pricing_date;
	}

	$prompt_price     = isset( $row['pricing']['prompt'] ) && is_numeric( $row['pricing']['prompt'] ) ? (float) $row['pricing']['prompt'] : -1.0;
	$completion_price = isset( $row['pricing']['completion'] ) && is_numeric( $row['pricing']['completion'] ) ? (float) $row['pricing']['completion'] : -1.0;
	if ( $prompt_price >= 0 && $completion_price >= 0 ) {
		// The catalog prices one token; the registry prices a million.
		$definition['price_input']  = round( $prompt_price * 1000000, 6 );
		$definition['price_output'] = round( $completion_price * 1000000, 6 );
	}

	if ( abcc_is_free_openrouter_model( $id ) ) {
		$definition['free'] = true;
	}
	if ( ! empty( $row['expiration_date'] ) && is_string( $row['expiration_date'] ) ) {
		$definition['expires'] = sanitize_text_field( $row['expiration_date'] );
	}

	return $definition;
}

/**
 * Whether an OpenRouter model ID is a free variant.
 *
 * @since 4.6.0
 * @param string $model_id Model ID.
 * @return bool
 */
function abcc_is_free_openrouter_model( $model_id ) {
	return ':free' === substr( (string) $model_id, -5 );
}

/**
 * An empty catalog result.
 *
 * @since 4.6.0
 * @return array{fetched_at: int, models: array}
 */
function abcc_get_empty_openrouter_catalog() {
	return array(
		'fetched_at' => 0,
		'models'     => array(),
	);
}

/**
 * The OpenRouter catalog as registry definitions, cached for 24 hours.
 *
 * The models endpoint is public, so no key is sent. A failed fetch is
 * remembered for ten minutes and yields an empty catalog; the shortlist and
 * saved selections do not depend on it.
 *
 * @since 4.6.0
 * @param bool $force_refresh Skip the caches.
 * @return array{fetched_at: int, models: array} 'models' maps ID => definition.
 */
function abcc_get_openrouter_catalog( $force_refresh = false ) {
	if ( ! $force_refresh ) {
		$cached = get_transient( 'abcc_openrouter_catalog' );
		if ( is_array( $cached ) && isset( $cached['models'] ) && is_array( $cached['models'] ) ) {
			return $cached;
		}
		if ( get_transient( 'abcc_openrouter_catalog_failed' ) ) {
			return abcc_get_empty_openrouter_catalog();
		}
	}

	$response = wp_remote_get(
		'https://openrouter.ai/api/v1/models',
		array(
			'timeout'            => 15,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
			'headers'            => array( 'Accept' => 'application/json' ),
		)
	);

	$data = null;
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	}

	if ( ! is_array( $data ) || empty( $data['data'] ) || ! is_array( $data['data'] ) ) {
		abcc_debug_log( 'OpenRouter catalog fetch failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'unexpected response' ) );
		set_transient( 'abcc_openrouter_catalog_failed', 1, 10 * MINUTE_IN_SECONDS );
		return abcc_get_empty_openrouter_catalog();
	}

	$now    = time();
	$date   = gmdate( 'Y-m-d', $now );
	$models = array();

	foreach ( $data['data'] as $row ) {
		$definition = abcc_openrouter_model_from_catalog_row( $row, $date );
		// ':batch' variants are asynchronous; a post needs an answer in this request.
		if ( null === $definition || ':batch' === substr( (string) $row['id'], -6 ) ) {
			continue;
		}
		$models[ $row['id'] ] = $definition;
	}

	if ( empty( $models ) ) {
		set_transient( 'abcc_openrouter_catalog_failed', 1, 10 * MINUTE_IN_SECONDS );
		return abcc_get_empty_openrouter_catalog();
	}

	$catalog = array(
		'fetched_at' => $now,
		'models'     => $models,
	);

	set_transient( 'abcc_openrouter_catalog', $catalog, DAY_IN_SECONDS );
	delete_transient( 'abcc_openrouter_catalog_failed' );

	// The registry reads only this small slice, so a page load never unserializes the whole catalog.
	$wanted = array_merge( array_keys( abcc_get_openrouter_shortlist() ), array_keys( abcc_get_custom_models( 'openrouter' ) ) );
	set_transient(
		'abcc_openrouter_live',
		array(
			'fetched_at' => $now,
			'models'     => array_intersect_key( $models, array_flip( $wanted ) ),
		),
		DAY_IN_SECONDS
	);

	abcc_flush_provider_registry_cache();

	return $catalog;
}

/**
 * Lay a live catalog definition over a stored or dated one.
 *
 * Price, reasoning effort, the free label and the expiry describe the model as
 * it is served today, so a value the catalog no longer reports must not survive
 * from the older definition.
 *
 * @since 4.6.0
 * @param array $base Stored or shortlist definition.
 * @param array $live Definition built from the current catalog.
 * @return array
 */
function abcc_openrouter_apply_live_definition( $base, $live ) {
	unset( $base['price_input'], $base['price_output'], $base['gateway_reasoning_effort'], $base['free'], $base['expires'] );

	return array_merge( $base, $live );
}

/**
 * OpenRouter text models for the registry: the shortlist with live catalog values laid over it.
 *
 * Never fetches. Without a cached catalog the dated shortlist values stand.
 *
 * @since 4.6.0
 * @return array Model ID => definition.
 */
function abcc_get_openrouter_registry_models() {
	$models = abcc_get_openrouter_shortlist();
	$live   = get_transient( 'abcc_openrouter_live' );

	if ( is_array( $live ) && ! empty( $live['models'] ) && is_array( $live['models'] ) ) {
		foreach ( $models as $model_id => $definition ) {
			if ( isset( $live['models'][ $model_id ] ) && is_array( $live['models'][ $model_id ] ) ) {
				$models[ $model_id ] = abcc_openrouter_apply_live_definition( $definition, $live['models'][ $model_id ] );
			}
		}
	}

	return $models;
}

/**
 * Lay live catalog values over the user's OpenRouter picks after custom models were merged.
 *
 * @since 4.6.0
 * @param array $registry Provider registry.
 * @return array
 */
function abcc_openrouter_overlay_live_models( $registry ) {
	if ( empty( $registry['openrouter']['text_models'] ) ) {
		return $registry;
	}

	$live = get_transient( 'abcc_openrouter_live' );
	if ( ! is_array( $live ) || empty( $live['models'] ) || ! is_array( $live['models'] ) ) {
		return $registry;
	}

	foreach ( $registry['openrouter']['text_models'] as $model_id => $definition ) {
		if ( ! empty( $definition['custom'] ) && isset( $live['models'][ $model_id ] ) && is_array( $live['models'][ $model_id ] ) ) {
			$registry['openrouter']['text_models'][ $model_id ] = array_merge( abcc_openrouter_apply_live_definition( $definition, $live['models'][ $model_id ] ), array( 'custom' => true ) );
		}
	}

	return $registry;
}

/**
 * Search the catalog by ID or name.
 *
 * @since 4.6.0
 * @param string $query Search text; every word must match.
 * @param int    $limit Maximum results.
 * @return array<int, array{id: string, name: string, free: bool, price_input: float|null, price_output: float|null, context_window: int, listed: bool}>
 */
function abcc_search_openrouter_catalog( $query, $limit = 20 ) {
	$catalog = abcc_get_openrouter_catalog();
	$words   = array_filter( preg_split( '/\s+/', strtolower( trim( (string) $query ) ) ), 'strlen' );
	$listed  = abcc_get_provider( 'openrouter' );
	$listed  = isset( $listed['text_models'] ) ? $listed['text_models'] : array();
	$results = array();

	if ( empty( $words ) ) {
		return $results;
	}

	foreach ( $catalog['models'] as $model_id => $definition ) {
		$haystack = strtolower( $model_id . ' ' . $definition['name'] );
		foreach ( $words as $word ) {
			if ( false === strpos( $haystack, $word ) ) {
				continue 2;
			}
		}

		$results[] = array(
			'id'             => (string) $model_id,
			'name'           => (string) $definition['name'],
			'free'           => ! empty( $definition['free'] ),
			'price_input'    => isset( $definition['price_input'] ) ? (float) $definition['price_input'] : null,
			'price_output'   => isset( $definition['price_output'] ) ? (float) $definition['price_output'] : null,
			'context_window' => (int) $definition['context_window'],
			'listed'         => isset( $listed[ $model_id ] ),
		);

		if ( count( $results ) >= (int) $limit ) {
			break;
		}
	}

	return $results;
}

/**
 * Attribution headers OpenRouter asks integrations to send.
 *
 * @since 4.6.0
 * @return array
 */
function abcc_get_openrouter_headers() {
	return array(
		'HTTP-Referer'       => home_url(),
		'X-OpenRouter-Title' => 'WP-AutoInsight',
		'X-Title'            => 'WP-AutoInsight',
	);
}

/**
 * Shape an OpenRouter request body.
 *
 * A JSON call must reach an endpoint that honors response_format, so
 * require_parameters travels with it. The reasoning effort comes from the
 * model's catalog data.
 *
 * @since 4.6.0
 * @param array  $body  Request body.
 * @param string $model Bare model ID.
 * @param array  $opts  Call options.
 * @return array
 */
function abcc_openrouter_request_body( $body, $model, $opts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- registry callback signature.
	if ( isset( $body['response_format'] ) ) {
		$body['provider'] = array( 'require_parameters' => true );
	}

	$definition = abcc_get_model_definition( $model, 'openrouter' );
	if ( ! empty( $definition['gateway_reasoning_effort'] ) ) {
		$body['reasoning'] = array( 'effort' => (string) $definition['gateway_reasoning_effort'] );
	}

	return $body;
}

/**
 * Note shown under an estimate for an OpenRouter model.
 *
 * The fee is charged when credits are bought, so it is a note and never a multiplier.
 *
 * @since 4.6.0
 * @return string
 */
function abcc_get_openrouter_cost_note() {
	return __( 'OpenRouter also charges a fee when you buy credits (5.5%, $0.80 minimum by card, as of 2026-10-01). It is not part of this estimate.', 'automated-blog-content-creator' );
}

/**
 * What the free tier allows, for the provider card.
 *
 * @since 4.6.0
 * @return string
 */
function abcc_get_openrouter_free_note() {
	return __( 'Free models are experimental: 50 requests a day shared across all free models (1,000 a day after buying $10 of credits), and one post uses 2 to 3 requests. They are never picked for you.', 'automated-blog-content-creator' );
}

/**
 * A cheap shortlist model from a different vendor than the primary, for the backup slot.
 *
 * Never a router ID: openrouter/auto bills whatever it routes to, with no ceiling.
 *
 * @since 4.6.0
 * @param string $primary_provider Provider ID of the primary model.
 * @return string Bare model ID, or '' when the shortlist is empty.
 */
function abcc_get_openrouter_fallback_suggestion( $primary_provider = '' ) {
	$vendors = array(
		'openai' => 'openai/',
		'claude' => 'anthropic/',
		'gemini' => 'google/',
	);
	$avoid   = isset( $vendors[ $primary_provider ] ) ? $vendors[ $primary_provider ] : '';
	$first   = '';

	foreach ( array_keys( abcc_get_openrouter_shortlist() ) as $model_id ) {
		if ( '' === $first ) {
			$first = $model_id;
		}
		if ( '' === $avoid || 0 !== strpos( $model_id, $avoid ) ) {
			return $model_id;
		}
	}

	return $first;
}

/**
 * Daily: refresh the catalog when an OpenRouter key is configured.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_refresh_openrouter_catalog_daily() {
	if ( '' === (string) abcc_get_provider_api_key( 'openrouter' ) ) {
		return;
	}

	abcc_get_openrouter_catalog( true );
}
add_action( 'abcc_daily_provider_health_check', 'abcc_refresh_openrouter_catalog_daily', 20 );

/**
 * AJAX: search the OpenRouter catalog for the model picker.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_handle_search_openrouter_models() {
	check_ajax_referer( 'abcc_custom_model_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
	if ( strlen( $query ) < 2 ) {
		wp_send_json_success(
			array(
				'results'   => array(),
				'available' => true,
			)
		);
		return;
	}

	try {
		$catalog = abcc_get_openrouter_catalog();
		$results = abcc_search_openrouter_catalog( $query, 20 );
	} catch ( Throwable $e ) {
		error_log( 'WP-AutoInsight OpenRouter search failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		wp_send_json_error( array( 'message' => __( 'Could not search the catalog.', 'automated-blog-content-creator' ) ) );
		return;
	}

	foreach ( $results as $index => $result ) {
		$results[ $index ]['label'] = abcc_format_openrouter_result_label( $result );
	}

	wp_send_json_success(
		array(
			'results'   => $results,
			'available' => ! empty( $catalog['models'] ),
			'message'   => empty( $catalog['models'] ) ? __( 'The OpenRouter catalog is not reachable right now. The shortlist above still works.', 'automated-blog-content-creator' ) : '',
		)
	);
}
add_action( 'wp_ajax_abcc_search_openrouter_models', 'abcc_handle_search_openrouter_models' );

/**
 * One-line description of a search result: name, price per million tokens, free label.
 *
 * @since 4.6.0
 * @param array $result Row from abcc_search_openrouter_catalog().
 * @return string
 */
function abcc_format_openrouter_result_label( $result ) {
	$label = (string) $result['name'];

	if ( ! empty( $result['free'] ) ) {
		$label = abcc_get_free_model_label( $label );
	} elseif ( null === $result['price_input'] || null === $result['price_output'] ) {
		$label .= ' ' . __( '(price varies)', 'automated-blog-content-creator' );
	} else {
		$label .= ' ' . sprintf(
			/* translators: 1: input price, 2: output price, both in US dollars per million tokens */
			__( '($%1$s in / $%2$s out per 1M tokens)', 'automated-blog-content-creator' ),
			number_format_i18n( (float) $result['price_input'], 2 ),
			number_format_i18n( (float) $result['price_output'], 2 )
		);
	}

	return $label;
}
