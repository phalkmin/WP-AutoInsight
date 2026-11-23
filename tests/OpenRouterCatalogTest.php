<?php
/**
 * OpenRouter (v4.6 §3): catalog parsing and cache, shortlist, request shaping,
 * free-model labelling, cost handling.
 */

/**
 * A small catalog shaped like GET /api/v1/models.
 *
 * @return string JSON body.
 */
function abcc_test_openrouter_catalog_body() {
	return wp_json_encode(
		array(
			'data' => array(
				array(
					'id'                   => 'openai/gpt-6-luna',
					'name'                 => 'OpenAI: GPT-6 Luna',
					'context_length'       => 1050000,
					'architecture'         => array( 'output_modalities' => array( 'text' ) ),
					'pricing'              => array(
						'prompt'     => '0.0000002',
						'completion' => '0.0000009',
					),
					'top_provider'         => array( 'max_completion_tokens' => 128000 ),
					'supported_parameters' => array( 'max_tokens', 'response_format', 'structured_outputs', 'reasoning' ),
					'reasoning'            => array(
						'mandatory'         => false,
						'default_enabled'   => true,
						'supported_efforts' => array( 'high', 'medium', 'low', 'none' ),
					),
				),
				array(
					'id'                   => 'acme/thinker-1',
					'name'                 => 'Acme: Thinker 1',
					'context_length'       => 200000,
					'architecture'         => array( 'output_modalities' => array( 'text' ) ),
					'pricing'              => array(
						'prompt'     => '0.000003',
						'completion' => '0.000015',
					),
					'top_provider'         => array( 'max_completion_tokens' => 64000 ),
					'supported_parameters' => array( 'max_tokens', 'temperature', 'reasoning' ),
					'reasoning'            => array(
						'mandatory'         => true,
						'supported_efforts' => array( 'high', 'medium', 'low' ),
					),
				),
				array(
					'id'                   => 'acme/plain-7b:free',
					'name'                 => 'Acme: Plain 7B (free)',
					'context_length'       => 32768,
					'architecture'         => array( 'output_modalities' => array( 'text' ) ),
					'pricing'              => array(
						'prompt'     => '0',
						'completion' => '0',
					),
					'top_provider'         => array( 'max_completion_tokens' => 8192 ),
					'supported_parameters' => array( 'max_tokens', 'temperature' ),
					'reasoning'            => null,
				),
				array(
					'id'                   => 'openrouter/auto',
					'name'                 => 'Auto Router',
					'context_length'       => 2000000,
					'architecture'         => array( 'output_modalities' => array( 'text' ) ),
					'pricing'              => array(
						'prompt'     => '-1',
						'completion' => '-1',
					),
					'top_provider'         => array( 'max_completion_tokens' => null ),
					'supported_parameters' => array( 'max_tokens', 'structured_outputs' ),
				),
				array(
					'id'             => 'acme/painter-xl',
					'name'           => 'Acme: Painter XL',
					'context_length' => 65536,
					'architecture'   => array( 'output_modalities' => array( 'image', 'text' ) ),
					'pricing'        => array(
						'prompt'     => '0.000001',
						'completion' => '0.000002',
					),
				),
				array(
					'id'             => 'acme/thinker-1:batch',
					'name'           => 'Acme: Thinker 1 (batch)',
					'context_length' => 200000,
					'architecture'   => array( 'output_modalities' => array( 'text' ) ),
					'pricing'        => array(
						'prompt'     => '0.0000015',
						'completion' => '0.0000075',
					),
				),
			),
		)
	);
}

abcc_test(
	'catalog rows become registry definitions; images, batch variants and router prices are handled',
	function () {
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		$catalog = abcc_get_openrouter_catalog();

		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( 'https://openrouter.ai/api/v1/models', $request['url'] );
		abcc_assert_false( isset( $request['args']['headers']['Authorization'] ), 'The public catalog is fetched without the key.' );
		abcc_assert_same( 0, $request['args']['redirection'] );
		abcc_assert_true( $request['args']['reject_unsafe_urls'] );

		abcc_assert_same( array( 'openai/gpt-6-luna', 'acme/thinker-1', 'acme/plain-7b:free', 'openrouter/auto' ), array_keys( $catalog['models'] ) );

		$luna = $catalog['models']['openai/gpt-6-luna'];
		abcc_assert_same( 1050000, $luna['context_window'] );
		abcc_assert_same( 128000, $luna['max_output'] );
		abcc_assert_same( 0.2, $luna['price_input'] );
		abcc_assert_same( 0.9, $luna['price_output'] );
		abcc_assert_true( $luna['structured_output'] );
		abcc_assert_false( $luna['temperature'] );
		abcc_assert_false( $luna['reasoning'], 'Reasoning is switched off when the model allows it.' );
		abcc_assert_same( 'none', $luna['gateway_reasoning_effort'] );
		abcc_assert_same( 'catalog', $luna['pricing_source'] );
		abcc_assert_same( gmdate( 'Y-m-d' ), $luna['pricing_date'] );

		$thinker = $catalog['models']['acme/thinker-1'];
		abcc_assert_true( $thinker['reasoning'] );
		abcc_assert_same( 'low', $thinker['gateway_reasoning_effort'] );
		abcc_assert_same( 4000, $thinker['min_output_tokens'] );
		abcc_assert_false( $thinker['structured_output'] );

		$free = $catalog['models']['acme/plain-7b:free'];
		abcc_assert_true( $free['free'] );
		abcc_assert_same( 0, $free['min_output_tokens'] );

		$auto = $catalog['models']['openrouter/auto'];
		abcc_assert_false( isset( $auto['price_input'] ), 'A router has no fixed price.' );
		abcc_assert_same( 0, $auto['max_output'] );
	}
);

abcc_test(
	'catalog is cached for a day and a failed fetch is not retried at once',
	function () {
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		abcc_get_openrouter_catalog();

		$GLOBALS['abcc_http_last_request'] = null;
		$again                             = abcc_get_openrouter_catalog();
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'A cached catalog needs no request.' );
		abcc_assert_true( isset( $again['models']['acme/thinker-1'] ) );

		// Failure: empty catalog, remembered briefly.
		abcc_test_reset_state();
		$failed = abcc_get_openrouter_catalog();
		abcc_assert_same( array(), $failed['models'] );

		$GLOBALS['abcc_http_last_request'] = null;
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		abcc_assert_same( array(), abcc_get_openrouter_catalog()['models'] );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'No second attempt inside the back-off window.' );

		// An explicit refresh still goes through.
		abcc_assert_true( isset( abcc_get_openrouter_catalog( true )['models']['openai/gpt-6-luna'] ) );
	}
);

abcc_test(
	'registry lists the shortlist without any request and lays cached catalog prices over it',
	function () {
		abcc_flush_provider_registry_cache();
		$GLOBALS['abcc_http_last_request'] = null;

		$config = abcc_get_provider( 'openrouter' );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'Building the registry never fetches.' );
		abcc_assert_same( 'openai_compatible', $config['transport'] );
		abcc_assert_same( '', $config['wp_connector_id'] );
		abcc_assert_true( count( $config['text_models'] ) >= 8 );
		abcc_assert_same( 0.1, $config['text_models']['openai/gpt-6-luna']['price_input'] );
		abcc_assert_same( 'registry', $config['text_models']['openai/gpt-6-luna']['pricing_source'] );

		foreach ( array_keys( $config['text_models'] ) as $model_id ) {
			abcc_assert_false( abcc_is_free_openrouter_model( $model_id ), 'No free model on the shortlist.' );
			abcc_assert_false( 0 === strpos( $model_id, 'openrouter/' ), 'No router on the shortlist.' );
		}

		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		abcc_get_openrouter_catalog();

		$live = abcc_get_provider( 'openrouter' );
		abcc_assert_same( 0.2, $live['text_models']['openai/gpt-6-luna']['price_input'], 'Live catalog price wins.' );
		abcc_assert_same( 'catalog', $live['text_models']['openai/gpt-6-luna']['pricing_source'] );
		abcc_assert_false( isset( $live['text_models']['acme/thinker-1'] ), 'The catalog does not flood the pickers.' );

		abcc_flush_provider_registry_cache();
	}
);

abcc_test(
	'a saved catalog selection is sent to OpenRouter with attribution headers, never to a native provider',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Hello.' ) );
		$result = abcc_call_provider_api(
			'openrouter',
			'openrouter::anthropic/claude-something-not-listed',
			'Prompt.',
			array(
				'api_key'    => 'sk-or-test',
				'max_tokens' => 300,
			)
		);

		$request = $GLOBALS['abcc_http_last_request'];
		$body    = json_decode( $request['args']['body'], true );

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'https://openrouter.ai/api/v1/chat/completions', $request['url'] );
		abcc_assert_same( 'Bearer sk-or-test', $request['args']['headers']['Authorization'] );
		abcc_assert_same( 'https://example.test', $request['args']['headers']['HTTP-Referer'] );
		abcc_assert_same( 'WP-AutoInsight', $request['args']['headers']['X-OpenRouter-Title'] );
		abcc_assert_same( 'anthropic/claude-something-not-listed', $body['model'] );
		abcc_assert_same( 300, $body['max_tokens'] );
		abcc_assert_false( isset( $body['temperature'] ) );
		abcc_assert_false( isset( $body['reasoning_effort'] ), 'OpenRouter takes the reasoning object, not the OpenAI parameter.' );
	}
);

abcc_test(
	'a JSON call sends the schema with require_parameters, and the reasoning effort comes from the catalog',
	function () {
		$schema = array(
			'type'                 => 'object',
			'properties'           => array( 'title' => array( 'type' => 'string' ) ),
			'required'             => array( 'title' ),
			'additionalProperties' => false,
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( '{"title":"T"}' ) );
		abcc_call_provider_api(
			'openrouter',
			'openai/gpt-6-luna',
			'Prompt.',
			array(
				'api_key'         => 'k',
				'max_tokens'      => 300,
				'require_json'    => true,
				'response_format' => array(
					'name'   => 'seo',
					'schema' => $schema,
				),
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );

		abcc_assert_same( 'json_schema', $body['response_format']['type'] );
		abcc_assert_same( $schema, $body['response_format']['json_schema']['schema'] );
		abcc_assert_same( array( 'require_parameters' => true ), $body['provider'] );
		abcc_assert_same( array( 'effort' => 'none' ), $body['reasoning'] );

		// A plain call carries no provider preference.
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Hi.' ) );
		abcc_call_provider_api( 'openrouter', 'meta-llama/llama-4-maverick', 'Prompt.', array( 'api_key' => 'k' ) );
		$plain = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_false( isset( $plain['provider'] ) );
		abcc_assert_false( isset( $plain['reasoning'] ), 'A model that cannot reason is sent no reasoning object.' );
	}
);

abcc_test(
	'a JSON-required call is refused before sending for a model without structured outputs',
	function () {
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		$catalog = abcc_get_openrouter_catalog();
		abcc_add_custom_model( 'openrouter', 'acme/thinker-1', $catalog['models']['acme/thinker-1'] );

		$GLOBALS['abcc_http_last_request'] = null;
		$result                            = abcc_call_provider_api(
			'openrouter',
			'acme/thinker-1',
			'Prompt.',
			array(
				'api_key'      => 'k',
				'require_json' => true,
			)
		);

		abcc_assert_same( 'abcc_provider_cannot_comply', $result['error']->get_error_code() );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'Nothing is sent to a model that cannot comply.' );

		abcc_flush_provider_registry_cache();
	}
);

abcc_test(
	'a catalog pick joins the registry, validates everywhere, and can be removed',
	function () {
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		$catalog = abcc_get_openrouter_catalog();

		abcc_assert_false( abcc_is_valid_text_model( 'openrouter::acme/plain-7b:free' ) );
		abcc_assert_true( true === abcc_add_custom_model( 'openrouter', 'acme/plain-7b:free', $catalog['models']['acme/plain-7b:free'] ) );

		abcc_assert_true( abcc_is_valid_text_model( 'openrouter::acme/plain-7b:free' ) );
		$definition = abcc_get_model_definition( 'openrouter::acme/plain-7b:free' );
		abcc_assert_same( 32768, $definition['context_window'] );
		abcc_assert_same( 8192, $definition['max_output'] );
		abcc_assert_true( $definition['free'] );
		abcc_assert_true( false !== strpos( abcc_format_model_option_label( 'acme/plain-7b:free', $definition ), 'free, experimental' ) );

		// Free picks sit after the shortlist, so nothing defaults to them.
		abcc_assert_false( abcc_is_free_openrouter_model( abcc_get_provider_default_model( 'openrouter' ) ) );

		abcc_assert_true( abcc_remove_custom_model( 'openrouter', 'acme/plain-7b:free' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'openrouter::acme/plain-7b:free' ) );
		abcc_assert_false( abcc_remove_custom_model( 'openrouter', 'acme/plain-7b:free' ) );
	}
);

abcc_test(
	'catalog search matches every word against ID and name and marks models already listed',
	function () {
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );
		abcc_get_openrouter_catalog();

		$results = abcc_search_openrouter_catalog( 'acme thinker' );
		abcc_assert_same( array( 'acme/thinker-1' ), wp_list_pluck_ids( $results ) );
		abcc_assert_false( $results[0]['listed'] );

		$luna = abcc_search_openrouter_catalog( 'LUNA' );
		abcc_assert_true( $luna[0]['listed'], 'Shortlist models are already offered.' );

		abcc_assert_same( array(), abcc_search_openrouter_catalog( '' ) );
		abcc_assert_same( array(), abcc_search_openrouter_catalog( 'nothing-like-this' ) );

		$free = abcc_search_openrouter_catalog( 'plain' );
		abcc_assert_true( false !== strpos( abcc_format_openrouter_result_label( $free[0] ), 'free, experimental' ) );
		$auto = abcc_search_openrouter_catalog( 'auto router' );
		abcc_assert_true( false !== strpos( abcc_format_openrouter_result_label( $auto[0] ), 'price varies' ) );

		abcc_flush_provider_registry_cache();
	}
);

/**
 * IDs of search results.
 *
 * @param array $results Search results.
 * @return array
 */
function wp_list_pluck_ids( $results ) {
	$ids = array();
	foreach ( $results as $result ) {
		$ids[] = $result['id'];
	}
	return $ids;
}

abcc_test(
	'search and add endpoints are admin-only and take definitions from the catalog, not the request',
	function () {
		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$_POST                                  = array(
			'nonce' => 'x',
			'query' => 'acme',
		);
		abcc_handle_search_openrouter_models();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );

		$_POST = array(
			'nonce'    => 'x',
			'provider' => 'openrouter',
			'model_id' => 'acme/thinker-1',
		);
		abcc_handle_add_custom_model();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );

		$GLOBALS['abcc_test_current_user_caps'] = array();
		abcc_test_queue_http_response( abcc_test_openrouter_catalog_body() );

		$_POST = array(
			'nonce' => 'x',
			'query' => 'acme',
		);
		abcc_handle_search_openrouter_models();
		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( $json['success'] && $json['data']['available'] );
		abcc_assert_same( 2, count( $json['data']['results'] ) );
		abcc_assert_true( '' !== $json['data']['results'][0]['label'] );

		// A made-up ID is refused; a claimed price in the request is ignored.
		$_POST = array(
			'nonce'          => 'x',
			'provider'       => 'openrouter',
			'model_id'       => 'acme/not-in-catalog',
			'context_window' => '99999999',
		);
		abcc_handle_add_custom_model();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );

		$_POST = array(
			'nonce'          => 'x',
			'provider'       => 'openrouter',
			'model_id'       => 'acme/thinker-1',
			'context_window' => '99999999',
		);
		abcc_handle_add_custom_model();
		$_POST = array();

		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'openrouter::acme/thinker-1', $GLOBALS['abcc_test_last_json']['data']['selection'] );
		abcc_assert_same( 200000, abcc_get_model_definition( 'openrouter::acme/thinker-1' )['context_window'] );

		abcc_flush_provider_registry_cache();
	}
);

abcc_test(
	'estimates use catalog prices and carry the credit-fee note without multiplying by it',
	function () {
		$flags = array(
			'seo'           => false,
			'locale'        => 'en_US',
			'prompt_tokens' => 400,
		);

		$estimate   = abcc_estimate_post_cost( 'openrouter::meta-llama/llama-4-maverick', 900, $flags );
		$definition = abcc_get_model_definition( 'openrouter::meta-llama/llama-4-maverick' );
		$expected   = round( ( 530 * $definition['price_input'] + ( (int) ceil( 900 * 1.35 ) + 25 ) * $definition['price_output'] ) / 1000000, 6 );

		abcc_assert_same( $expected, $estimate['cost'], 'The fee is not folded into the estimate.' );
		abcc_assert_same( 1, count( $estimate['notes'] ) );

		$text = abcc_format_post_cost_estimate( $estimate );
		abcc_assert_true( false !== strpos( $text['detail'], 'fee when you buy credits' ) );

		// A native provider has no such note.
		abcc_assert_same( array(), abcc_estimate_post_cost( 'openai::gpt-5.4-mini', 900, $flags )['notes'] );

		// A router has no price: unknown, never $0.
		abcc_assert_same( null, abcc_estimate_post_cost( 'openrouter::openrouter/auto', 900, $flags )['cost'] );
	}
);

abcc_test(
	'usage records the cost OpenRouter reports and the model a router actually used',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'model'   => 'acme/served-by-router',
					'choices' => array(
						array(
							'message'       => array( 'content' => 'Routed.' ),
							'finish_reason' => 'stop',
						),
					),
					'usage'   => array(
						'prompt_tokens'     => 100,
						'completion_tokens' => 200,
						'cost'              => 0.00123,
					),
				)
			)
		);

		$result = abcc_execute_fallback_chain(
			'openrouter',
			'openrouter::openrouter/auto',
			'Prompt.',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			),
			array( 'job_id' => 4321 )
		);

		abcc_assert_same( 'acme/served-by-router', $result['served_model'] );

		$usage = abcc_get_job_usage( 4321 );
		abcc_assert_same( 'acme/served-by-router', $usage[0]['model'] );
		abcc_assert_same( 0.00123, $usage[0]['estimated_cost'] );
		abcc_assert_same( 'provider', $usage[0]['pricing_source'] );
	}
);

abcc_test(
	'402 from OpenRouter is an out-of-credits error, and a key with no credits still validates',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => '{"error":{"message":"Insufficient credits","code":402}}',
		);
		$result                       = abcc_call_provider_api( 'openrouter', 'openai/gpt-6-luna', 'Prompt.', array( 'api_key' => 'k' ) );

		abcc_assert_same( 'abcc_provider_payment_required', $result['error']->get_error_code() );
		abcc_assert_true( false !== stripos( abcc_format_generation_error( $result['error'], array( 'provider' => 'openrouter' ) ), 'credits' ) );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => '{"error":{"message":"Insufficient credits"}}',
		);
		$test                         = abcc_test_provider_connection( 'openrouter', 'k' );
		abcc_assert_true( $test['success'] );
		abcc_assert_true( false !== strpos( $test['message'], 'no credits' ) );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => '{"error":{"message":"No auth credentials found"}}',
		);
		$bad                          = abcc_test_provider_connection( 'openrouter', 'k' );
		abcc_assert_false( $bad['success'] );
		abcc_assert_true( false !== strpos( $bad['error'], 'rejected the key' ) );

		// The request itself fails.
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
		$down                         = abcc_test_provider_connection( 'openrouter', 'k' );
		abcc_assert_false( $down['success'] );
		abcc_assert_true( false !== strpos( $down['error'], 'Could not reach OpenRouter' ) );
	}
);

abcc_test(
	'backup suggestion is a shortlist model from another vendor than the primary, never a router',
	function () {
		$for_openai = abcc_get_openrouter_fallback_suggestion( 'openai' );
		abcc_assert_true( '' !== $for_openai && 0 !== strpos( $for_openai, 'openai/' ) );
		abcc_assert_true( isset( abcc_get_openrouter_shortlist()[ $for_openai ] ) );

		$for_claude = abcc_get_openrouter_fallback_suggestion( 'claude' );
		abcc_assert_true( 0 !== strpos( $for_claude, 'anthropic/' ) );

		abcc_assert_false( 0 === strpos( abcc_get_openrouter_fallback_suggestion( 'perplexity' ), 'openrouter/' ) );

		// A saved backup on OpenRouter passes the chain sanitizer.
		$chain = abcc_sanitize_fallback_chain(
			array(
				array(
					'provider' => 'openrouter',
					'model'    => $for_openai,
				),
				array(
					'provider' => 'openrouter',
					'model'    => 'acme/not-listed',
				),
			)
		);
		abcc_assert_same( 1, count( $chain ) );
	}
);

abcc_test(
	'the OpenRouter key is a secret: never exported, redacted from messages',
	function () {
		abcc_update_setting( 'openrouter_api_key', 'sk-or-v1-abcdef1234567890' );

		abcc_assert_false( array_key_exists( 'openrouter_api_key', abcc_get_exportable_settings() ) );
		abcc_assert_false( false !== strpos( abcc_redact_secrets( 'bad key sk-or-v1-abcdef1234567890 sent' ), 'abcdef1234567890' ) );
		abcc_assert_same( 'sk-or-v1-abcdef1234567890', abcc_get_provider_api_key( 'openrouter' ) );
	}
);

abcc_test(
	'a free model is labelled once even when its catalog name already says "(free)"',
	function () {
		abcc_assert_same( 'Acme: Plain 7B (free, experimental)', abcc_get_free_model_label( 'Acme: Plain 7B (free)' ) );
		abcc_assert_same( 'Acme: Plain 7B (free, experimental)', abcc_get_free_model_label( 'Acme: Plain 7B' ) );
		abcc_assert_same(
			'Acme: Plain 7B (free, experimental)',
			abcc_format_model_option_label(
				'acme/plain-7b:free',
				array(
					'name' => 'Acme: Plain 7B (free)',
					'free' => true,
				)
			)
		);
	}
);
