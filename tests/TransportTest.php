<?php
/**
 * Data-driven provider transport (v4.6 §2.2).
 *
 * Requests branch on the registry's transport and per-model flags, never on
 * the provider ID; 402 and reasoning-only replies have their own codes; a
 * provider added through the registry filter generates text.
 */

/**
 * Canned OpenAI-dialect body with optional reasoning fields.
 *
 * @param string|null $content   Message content.
 * @param array       $extra     Extra message keys.
 * @param array       $usage     Usage block.
 * @return string
 */
function abcc_test_chat_body( $content, $extra = array(), $usage = array() ) {
	$message = array_merge( array( 'content' => $content ), $extra );

	return wp_json_encode(
		array(
			'choices' => array(
				array(
					'message'       => $message,
					'finish_reason' => 'stop',
				),
			),
			'usage'   => $usage ? $usage : array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
			),
		)
	);
}

/**
 * Last request body, decoded.
 *
 * @return array
 */
function abcc_test_last_body() {
	return json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
}

abcc_test(
	'each transport sends its own endpoint, auth header and body shape',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', array( 'api_key' => 'sk' ) );
		$req = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( 'https://api.openai.com/v1/chat/completions', $req['url'] );
		abcc_assert_same( 'Bearer sk', $req['args']['headers']['Authorization'] );
		abcc_assert_same( 'user', abcc_test_last_body()['messages'][0]['role'] );

		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'ok',
						),
					),
				)
			)
		);
		abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'p', array( 'api_key' => 'ak' ) );
		$req = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( 'https://api.anthropic.com/v1/messages', $req['url'] );
		abcc_assert_same( 'ak', $req['args']['headers']['x-api-key'] );
		abcc_assert_same( '2023-06-01', $req['args']['headers']['anthropic-version'] );
		abcc_assert_false( isset( $req['args']['headers']['Authorization'] ) );

		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'p', array( 'api_key' => 'gk' ) );
		$req = $GLOBALS['abcc_http_last_request'];
		abcc_assert_true( false !== strpos( $req['url'], '/models/gemini-2.5-flash:generateContent' ) );
		abcc_assert_same( 'gk', $req['args']['headers']['x-goog-api-key'] );
		abcc_assert_true( isset( abcc_test_last_body()['contents'][0]['parts'][0]['text'] ) );
	}
);

abcc_test(
	'a qualified selection passed as the model is sent as the bare ID',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		$result = abcc_call_provider_api( 'openai', 'openai::gpt-5.4-mini', 'p', array( 'api_key' => 'sk' ) );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'gpt-5.4-mini', abcc_test_last_body()['model'] );
	}
);

abcc_test(
	'a system block goes to the system slot of each transport, or into the user turn when the model has no system role',
	function () {
		$opts = array(
			'api_key' => 'k',
			'system'  => 'You write for a site.',
		);

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', $opts );
		$body = abcc_test_last_body();
		abcc_assert_same( 'system', $body['messages'][0]['role'] );
		abcc_assert_same( 'You write for a site.', $body['messages'][0]['content'] );
		abcc_assert_same( 'p', $body['messages'][1]['content'] );

		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) ) );
		abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'p', $opts );
		$body = abcc_test_last_body();
		abcc_assert_same( 'You write for a site.', $body['system'] );
		abcc_assert_same( 1, count( $body['messages'] ) );

		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'p', $opts );
		$body = abcc_test_last_body();
		abcc_assert_same( 'You write for a site.', $body['systemInstruction']['parts'][0]['text'] );

		add_filter(
			'abcc_model_flags',
			function ( $flags ) {
				$flags['system_role'] = false;
				return $flags;
			}
		);
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', $opts );
		$body = abcc_test_last_body();
		abcc_assert_same( 1, count( $body['messages'] ) );
		abcc_assert_same( "You write for a site.\n\np", $body['messages'][0]['content'] );
	}
);

abcc_test(
	'temperature follows the model flag and the provider default',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 0.8, abcc_test_last_body()['temperature'] );

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api(
			'openai',
			'gpt-4.1-2025-04-14',
			'p',
			array(
				'api_key'     => 'k',
				'temperature' => 0.3,
			)
		);
		abcc_assert_same( 0.3, abcc_test_last_body()['temperature'] );

		// Perplexity has no provider default and Claude gets none unless asked.
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'perplexity', 'sonar', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_false( isset( abcc_test_last_body()['temperature'] ) );

		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) ) );
		abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_false( isset( abcc_test_last_body()['temperature'] ) );
	}
);

abcc_test(
	'the model floor is the one source of minimum output and the max-output flag clamps it',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'A title' ) );
		abcc_call_provider_api(
			'perplexity',
			'sonar',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 50,
			)
		);
		abcc_assert_same( 800, abcc_test_last_body()['max_tokens'], 'Sonar floor comes from the registry now.' );

		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) ) );
		abcc_call_provider_api(
			'claude',
			'claude-sonnet-4-6',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 150,
			)
		);
		abcc_assert_same( 150, abcc_test_last_body()['max_tokens'], 'No floor on Claude.' );

		add_filter(
			'abcc_model_flags',
			function ( $flags, $provider, $model ) {
				if ( 'claude-sonnet-4-6' === $model ) {
					$flags['max_output'] = 100;
				}
				return $flags;
			},
			10,
			3
		);
		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) ) );
		abcc_call_provider_api(
			'claude',
			'claude-sonnet-4-6',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 5000,
			)
		);
		abcc_assert_same( 100, abcc_test_last_body()['max_tokens'], 'max_output caps the request.' );
	}
);

abcc_test(
	'per-model timeout reaches the HTTP call and an explicit option overrides it',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'perplexity', 'sonar-deep-research', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 300, $GLOBALS['abcc_http_last_request']['args']['timeout'] );

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'perplexity', 'sonar', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 60, $GLOBALS['abcc_http_last_request']['args']['timeout'] );

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api(
			'perplexity',
			'sonar',
			'p',
			array(
				'api_key' => 'k',
				'timeout' => 15,
			)
		);
		abcc_assert_same( 15, $GLOBALS['abcc_http_last_request']['args']['timeout'] );
	}
);

abcc_test(
	'the recency filter rides in through the registry callback, not a provider branch',
	function () {
		$GLOBALS['abcc_test_options']['abcc_perplexity_recency_filter'] = 'week';

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'perplexity', 'sonar', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'week', abcc_test_last_body()['search_recency_filter'] );

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_false( isset( abcc_test_last_body()['search_recency_filter'] ) );
	}
);

abcc_test(
	'think blocks are stripped on every OpenAI-compatible reply',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( "<think>plan</think>\n<p>Body</p>" ) );
		$result = abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( '<p>Body</p>', trim( $result['content'][0] ) );
	}
);

abcc_test(
	'HTTP 402 is its own code, carries the provider detail, and may fall through to a backup',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Insufficient credits. Add more credits.' ) ) ),
		);
		$result                       = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_provider_payment_required', $result['error']->get_error_code() );
		abcc_assert_same( 'Insufficient credits. Add more credits.', $result['error']->get_error_data()['detail'] );
		abcc_assert_true( abcc_is_retryable_generation_error( $result['error'] ) );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_provider_payment_required' ) );
		abcc_assert_true( false !== strpos( abcc_format_generation_error( $result['error'], array( 'provider' => 'openai' ) ), 'credits' ) );

		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options']['abcc_fallback_chain'] = array(
			array(
				'provider' => 'claude',
				'model'    => 'claude-sonnet-4-6',
			),
		);
		$GLOBALS['abcc_test_options']['claude_api_key']      = 'ak';

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => '{}',
		);
		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => '<p>From Claude</p>' ) ) ) ) );

		$chain = abcc_execute_fallback_chain( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ), array() );
		abcc_assert_same( null, $chain['error'] );
		abcc_assert_same( 'claude', $chain['served_provider'] );
		abcc_assert_same( 2, $chain['attempt'] );
	}
);

abcc_test(
	'a reply with reasoning and no text fails as reasoning-only, keeps usage and never logs the reasoning',
	function () {
		abcc_test_queue_http_response(
			abcc_test_chat_body(
				'',
				array( 'reasoning_content' => 'secret chain of thought' ),
				array(
					'prompt_tokens'             => 10,
					'completion_tokens'         => 900,
					'completion_tokens_details' => array( 'reasoning_tokens' => 900 ),
				)
			)
		);
		$result = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ) );

		abcc_assert_same( 'abcc_reasoning_only', $result['error']->get_error_code() );
		abcc_assert_same( 900, $result['usage']['completion_tokens'] );
		abcc_assert_same( 900, $result['tokens']['reasoning'] );
		abcc_assert_same( 10, $result['tokens']['input'] );
		abcc_assert_false( strpos( $result['error']->get_error_message(), 'secret' ) );
		abcc_assert_false( strpos( wp_json_encode( $result['error']->get_error_data() ), 'secret' ) );
		abcc_assert_true( false !== strpos( abcc_format_generation_error( $result['error'], array( 'provider' => 'openai' ) ), 'thinking' ) );

		// A reply that is merely empty, with no reasoning field, stays an empty completion.
		abcc_test_queue_http_response( abcc_test_chat_body( '' ) );
		$empty = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_provider_empty_completion', $empty['error']->get_error_code() );
	}
);

abcc_test(
	'usage is normalized to input, output and reasoning tokens per transport',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content' => array( array( 'text' => 'ok' ) ),
					'usage'   => array(
						'input_tokens'  => 7,
						'output_tokens' => 3,
					),
				)
			)
		);
		$claude = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same(
			array(
				'input'     => 7,
				'output'    => 3,
				'reasoning' => null,
			),
			$claude['tokens']
		);

		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates'    => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => 'ok' ) ) ),
							'finishReason' => 'STOP',
						),
					),
					'usageMetadata' => array(
						'promptTokenCount'     => 5,
						'candidatesTokenCount' => 9,
						'thoughtsTokenCount'   => 4,
					),
				)
			)
		);
		$gemini = abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same(
			array(
				'input'     => 5,
				'output'    => 9,
				'reasoning' => 4,
			),
			$gemini['tokens']
		);

		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => 'ok' ),
							'finish_reason' => 'stop',
						),
					),
				)
			)
		);
		$no_usage = abcc_call_provider_api( 'openai', 'gpt-4.1-2025-04-14', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same(
			array(
				'input'     => null,
				'output'    => null,
				'reasoning' => null,
			),
			$no_usage['tokens']
		);
	}
);

abcc_test(
	'a JSON requirement is checked before any call, and a backup that cannot comply is skipped',
	function () {
		abcc_assert_true( abcc_provider_can_serve_request( 'claude', 'claude-sonnet-4-6', array( 'require_json' => true ) ) );

		add_filter(
			'abcc_model_flags',
			function ( $flags, $provider ) {
				if ( 'openai' === $provider ) {
					$flags['structured_output'] = false;
				}
				return $flags;
			},
			10,
			2
		);

		$check = abcc_provider_can_serve_request( 'openai', 'gpt-5.4-mini', array( 'require_json' => true ) );
		abcc_assert_same( 'abcc_provider_cannot_comply', $check->get_error_code() );

		$queue_before = count( $GLOBALS['abcc_http_queue'] );
		$result       = abcc_call_provider_api(
			'openai',
			'gpt-5.4-mini',
			'p',
			array(
				'api_key'      => 'k',
				'require_json' => true,
			)
		);
		abcc_assert_same( 'abcc_provider_cannot_comply', $result['error']->get_error_code() );
		abcc_assert_same( $queue_before, count( $GLOBALS['abcc_http_queue'] ), 'No HTTP for a target that cannot comply.' );

		// Primary cannot comply, first backup cannot either, second can.
		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options']['abcc_fallback_chain'] = array(
			array(
				'provider' => 'openai',
				'model'    => 'gpt-5.4',
			),
			array(
				'provider' => 'claude',
				'model'    => 'claude-sonnet-4-6',
			),
		);
		$GLOBALS['abcc_test_options']['openai_api_key']      = 'k';
		$GLOBALS['abcc_test_options']['claude_api_key']      = 'ak';
		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => '{"ok":true}' ) ) ) ) );

		$chain = abcc_execute_fallback_chain(
			'openai',
			'gpt-5.4-mini',
			'p',
			array(
				'api_key'      => 'k',
				'require_json' => true,
			),
			array()
		);
		abcc_assert_same( null, $chain['error'] );
		abcc_assert_same( 'claude', $chain['served_provider'] );
	}
);

abcc_test(
	'a provider added through the registry filter generates text with no code in gpt.php',
	function () {
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['acme'] = array(
					'id'                       => 'acme',
					'name'                     => 'Acme',
					'group_label'              => 'Acme Models',
					'help_url'                 => '',
					'constant'                 => 'ACME_API',
					'option_name'              => 'acme_api_key',
					'wp_connector_id'          => '',
					'text_generation_callback' => '',
					'connection_test_callback' => '',
					'connection_test_model'    => 'acme-1',
					'transport'                => 'openai_compatible',
					'endpoint'                 => 'https://api.acme.test/v1/chat/completions',
					'auth'                     => 'bearer',
					'extra_headers'            => array( 'X-Acme' => 'yes' ),
					'capabilities'             => array(
						'text_generation'     => true,
						'image_generation'    => false,
						'citations'           => false,
						'audio_transcription' => false,
					),
					'text_models'              => array(
						'acme-1' => array(
							'name'           => 'Acme One',
							'context_window' => 32000,
							'max_output'     => 4096,
						),
					),
				);
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();

		abcc_assert_same( 'openai_compatible', abcc_get_provider_transport( 'acme' ) );
		abcc_assert_same( 'acme', abcc_get_provider_for_model( 'acme::acme-1' ) );
		abcc_assert_true( abcc_is_valid_text_model( 'acme::acme-1' ) );

		abcc_test_queue_http_response( abcc_test_chat_body( '<p>Hello from Acme</p>' ) );
		$result = abcc_call_provider_api( 'acme', 'acme-1', 'p', array( 'api_key' => 'ac' ) );
		$req    = $GLOBALS['abcc_http_last_request'];

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( '<p>Hello from Acme</p>', trim( $result['content'][0] ) );
		abcc_assert_same( 'https://api.acme.test/v1/chat/completions', $req['url'] );
		abcc_assert_same( 'Bearer ac', $req['args']['headers']['Authorization'] );
		abcc_assert_same( 'yes', $req['args']['headers']['X-Acme'] );
		abcc_assert_same( 32000, abcc_get_model_context_window( 'acme::acme-1' ) );

		// A registered provider with no transport (image-only) is still refused without HTTP.
		$queue_before = count( $GLOBALS['abcc_http_queue'] );
		$stability    = abcc_call_provider_api( 'stability', 'sd3', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_unknown_provider', $stability['error']->get_error_code() );
		abcc_assert_same( $queue_before, count( $GLOBALS['abcc_http_queue'] ) );

		$GLOBALS['abcc_test_filters'] = array();
		abcc_flush_provider_registry_cache();
		abcc_assert_same( null, abcc_get_provider( 'acme' ) );
	}
);

abcc_test(
	'an empty model is refused as unknown model before any HTTP',
	function () {
		$queue_before = count( $GLOBALS['abcc_http_queue'] );
		$result       = abcc_call_provider_api( 'openai', 'openai::', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_unknown_model', $result['error']->get_error_code() );
		abcc_assert_same( $queue_before, count( $GLOBALS['abcc_http_queue'] ) );
	}
);

abcc_test(
	'the generation probe reports reachable, authenticated and generates separately',
	function () {
		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		$ok = abcc_test_provider_generation( 'openai', 'k' );
		abcc_assert_true( $ok['reachable'] && $ok['authenticated'] && $ok['generates'] && $ok['success'] );
		abcc_assert_same( 'gpt-5.4-mini', abcc_test_last_body()['model'] );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => '{"error":{"message":"bad key"}}',
		);
		$auth                         = abcc_test_provider_generation( 'openai', 'k' );
		abcc_assert_true( $auth['reachable'] );
		abcc_assert_false( $auth['authenticated'] );
		abcc_assert_false( $auth['generates'] );

		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'cURL error 6' );
		$down                         = abcc_test_provider_generation( 'openai', 'k' );
		abcc_assert_false( $down['reachable'] );
		abcc_assert_same( 'http_request_failed', $down['code'] );
	}
);

abcc_test(
	'the OpenAI client reads the same flags as the transport',
	function () {
		$client = new ABCC_OpenAI_Client( 'k' );

		abcc_test_queue_http_response( abcc_test_chat_body( 'ok' ) );
		$client->create_chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => 'hi',
				),
			),
			array(
				'model'      => 'openai::gpt-5.4-mini',
				'max_tokens' => 5,
			)
		);
		$body = abcc_test_last_body();
		abcc_assert_same( 'gpt-5.4-mini', $body['model'] );
		abcc_assert_same( 1000, $body['max_completion_tokens'] );
		abcc_assert_same( 'none', $body['reasoning_effort'] );
		abcc_assert_false( isset( $body['temperature'] ) );
		abcc_assert_false( isset( $body['max_tokens'] ) );
	}
);


abcc_test(
	'a qualified model selection cannot be stripped and rerouted to another provider',
	function () {
		$result = abcc_call_provider_api(
			'openai',
			'openrouter::gpt-5.4-mini',
			'Prompt.',
			array( 'api_key' => 'openai-secret' )
		);

		abcc_assert_same( 'abcc_unknown_model', $result['error']->get_error_code() );
		abcc_assert_same( '', $result['served_model'] );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'A mismatched selection must fail before any credential or prompt is sent.' );
	}
);

abcc_test(
	'authenticated provider requests disable redirects by default',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 302 ),
			'body'     => '',
		);

		abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'Prompt.', array( 'api_key' => 'redirect-secret' ) );

		abcc_assert_same( 0, $GLOBALS['abcc_http_last_request']['args']['redirection'] );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'A redirect must not produce another provider request.' );
	}
);

abcc_test(
	'provider failures redact an unsaved key and never log raw response payloads',
	function () {
		$secret                       = 'plainCustomSecret987654';
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode(
				array(
					'error'  => array( 'message' => 'Rejected ' . $secret ),
					'prompt' => 'raw prompt sentinel',
				)
			),
		);

		$result = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'Prompt.', array( 'api_key' => $secret ) );
		$logged = implode( "\n", $GLOBALS['abcc_test_debug_log'] );

		abcc_assert_same( 'Rejected [redacted]', $result['error']->get_error_data()['detail'] );
		abcc_assert_false( false !== strpos( $logged, $secret ) );
		abcc_assert_false( false !== strpos( $logged, 'raw prompt sentinel' ) );

		$GLOBALS['abcc_test_debug_log'] = array();
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'unexpected' => 'raw response sentinel',
					'credential' => $secret,
				)
			)
		);
		$malformed = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'Prompt.', array( 'api_key' => $secret ) );
		$logged    = implode( "\n", $GLOBALS['abcc_test_debug_log'] );

		abcc_assert_same( 'abcc_provider_parse_error', $malformed['error']->get_error_code() );
		abcc_assert_false( false !== strpos( $logged, $secret ) );
		abcc_assert_false( false !== strpos( $logged, 'raw response sentinel' ) );
	}
);


abcc_test(
	'the legacy OpenAI client enforces model identity, redirect policy and key redaction',
	function () {
		$secret = 'legacyOpenAISecret987654';
		$client = new ABCC_OpenAI_Client( $secret );

		$mismatch = $client->create_chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => 'Prompt.',
				),
			),
			array( 'model' => 'openrouter::gpt-5.4-mini' )
		);
		abcc_assert_same( 'abcc_unknown_model', $mismatch->get_error_code() );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'] );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 302 ),
			'body'     => '',
		);
		$client->create_image( 'Prompt.' );
		abcc_assert_same( 0, $GLOBALS['abcc_http_last_request']['args']['redirection'] );
		abcc_assert_true( $GLOBALS['abcc_http_last_request']['args']['reject_unsafe_urls'] );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Rejected ' . $secret ) ) ),
		);
		$failed                       = $client->create_image( 'Prompt.' );
		abcc_assert_same( 'Rejected [redacted]', $failed->get_error_message() );
	}
);

abcc_test(
	'image provider requests disable redirects and scrub echoed credentials from errors',
	function () {
		$gemini_secret                = 'geminiImageSecret987654';
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Rejected ' . $gemini_secret ) ) ),
		);
		abcc_gemini_generate_images( $gemini_secret, 'Prompt.' );
		abcc_assert_same( 0, $GLOBALS['abcc_http_last_request']['args']['redirection'] );
		abcc_assert_true( $GLOBALS['abcc_http_last_request']['args']['reject_unsafe_urls'] );
		abcc_assert_false( false !== strpos( abcc_get_last_image_error(), $gemini_secret ) );
		abcc_assert_false( false !== strpos( implode( "\n", $GLOBALS['abcc_test_debug_log'] ), $gemini_secret ) );

		$stability_secret             = 'stabilityImageSecret987654';
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'message' => 'Rejected ' . $stability_secret ) ),
		);
		abcc_stability_generate_images( 'Prompt.', 1, $stability_secret );
		abcc_assert_same( 0, $GLOBALS['abcc_http_last_request']['args']['redirection'] );
		abcc_assert_true( $GLOBALS['abcc_http_last_request']['args']['reject_unsafe_urls'] );
		abcc_assert_false( false !== strpos( abcc_get_last_image_error(), $stability_secret ) );
		abcc_assert_false( false !== strpos( implode( "\n", $GLOBALS['abcc_test_debug_log'] ), $stability_secret ) );
	}
);

abcc_test(
	'every non-empty request credential is redacted exactly, including short keys',
	function () {
		foreach ( array( 'k', 'ab', 'abc' ) as $secret ) {
			$body = wp_json_encode(
				array(
					'error' => array( 'message' => 'Rejected ' . $secret . ' and ' . $secret ),
				)
			);
			abcc_assert_same( 'Rejected [redacted] and [redacted]', abcc_extract_provider_error_detail( $body, $secret ) );
		}

		$prose = wp_json_encode( array( 'error' => array( 'message' => 'The request was rejected.' ) ) );
		abcc_assert_same( 'The request was rejected.', abcc_extract_provider_error_detail( $prose, '' ), 'No credential means no broad prose substitution.' );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Rejected k' ) ) ),
		);
		$result                       = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'Prompt.', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'Rejected [redacted]', $result['error']->get_error_data()['detail'] );
	}
);

abcc_test(
	'Claude 5 and later are sent a low effort; earlier Claude models get no output_config',
	function () {
		$fake = wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) );

		foreach ( array( 'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5', 'claude-newmodel-6' ) as $model ) {
			abcc_test_queue_http_response( $fake );
			abcc_call_provider_api( 'claude', $model, 'p', array( 'api_key' => 'k' ) );
			$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
			abcc_assert_same( array( 'effort' => 'low' ), $body['output_config'], $model );
			abcc_assert_false( isset( $body['thinking'] ), $model . ': thinking stays adaptive, effort only bounds it.' );
		}

		// Effort is rejected by Claude models before 4.6.
		abcc_test_queue_http_response( $fake );
		abcc_call_provider_api( 'claude', 'claude-haiku-4-5-20251001', 'p', array( 'api_key' => 'k' ) );
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_false( isset( $body['output_config'] ) );

		// The flag is filterable like every other request flag.
		add_filter(
			'abcc_model_flags',
			function ( $flags, $provider, $model ) {
				if ( 'claude' === $provider && 'claude-haiku-5-5' === $model ) {
					$flags['reasoning_effort'] = 'medium';
				}
				return $flags;
			},
			10,
			3
		);
		abcc_test_queue_http_response( $fake );
		abcc_call_provider_api( 'claude', 'claude-haiku-5-5', 'p', array( 'api_key' => 'k' ) );
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 'medium', $body['output_config']['effort'] );
	}
);
