<?php
/**
 * Fixes from the 2026-11-05 v4.6 adversarial review.
 */

abcc_test(
	'a thinking-only Claude reply is reasoning_only, not a parse error, and is never logged raw',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content'     => array(
						array(
							'type'     => 'thinking',
							'thinking' => 'PRIVATE THOUGHT',
						),
					),
					'stop_reason' => 'max_tokens',
					'usage'       => array(
						'input_tokens'  => 5,
						'output_tokens' => 100,
					),
				)
			)
		);
		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-5-5', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_reasoning_only', $result['error']->get_error_code() );
		abcc_assert_same( 100, $result['tokens']['output'] );
	}
);

abcc_test(
	'a thought-only Gemini reply is reasoning_only',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array(
								'parts' => array(
									array(
										'thought' => true,
										'text'    => 'hidden',
									),
								),
							),
							'finishReason' => 'MAX_TOKENS',
						),
					),
				)
			)
		);
		$result = abcc_call_provider_api( 'gemini', 'gemini-3.8-flash', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( 'abcc_reasoning_only', $result['error']->get_error_code() );
	}
);

abcc_test(
	'Gemini 2.5 thinking budget never pushes maxOutputTokens past the model ceiling',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-pro',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 65536,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 65536, $body['generationConfig']['maxOutputTokens'] );
	}
);

abcc_test(
	'Claude receives the JSON schema as a system instruction when structured output is requested',
	function () {
		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => '{}' ) ) ) ) );
		abcc_call_provider_api(
			'claude',
			'claude-sonnet-5-5',
			'hello',
			array(
				'api_key'         => 'k',
				'require_json'    => true,
				'response_format' => array( 'schema' => array( 'type' => 'object' ) ),
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_true( isset( $body['system'] ) && false !== strpos( $body['system'], 'JSON Schema' ), 'System prompt must carry the schema.' );
		abcc_assert_true( false !== strpos( $body['system'], '"type":"object"' ) );
	}
);

abcc_test(
	'usage totals do not double count OpenAI reasoning tokens and the note states its scope',
	function () {
		$GLOBALS['abcc_test_post_meta'][901]['_abcc_job_usage'] = array(
			array(
				'provider'         => 'openai',
				'input_tokens'     => 12,
				'output_tokens'    => 1000,
				'reasoning_tokens' => 1000,
				'estimated_cost'   => 0.0045,
				'pricing_date'     => '2026-11-05',
			),
			array(
				'provider'         => 'gemini',
				'input_tokens'     => 5,
				'output_tokens'    => 9,
				'reasoning_tokens' => 4,
				'estimated_cost'   => null,
				'pricing_date'     => '2026-11-05',
			),
		);
		$summary = abcc_summarize_job_usage( 901 );
		abcc_assert_same( 1013, $summary['output_tokens'] );

		$note = abcc_format_job_usage_note( 901 );
		abcc_assert_true( false !== strpos( $note, '2 calls' ), $note );
		abcc_assert_true( false !== strpos( $note, 'Text calls only' ), $note );

		$GLOBALS['abcc_test_post_meta'][902]['_abcc_job_usage'] = array(
			array(
				'provider'         => 'openai',
				'input_tokens'     => 1,
				'output_tokens'    => 2,
				'reasoning_tokens' => 0,
				'estimated_cost'   => 0.001,
				'pricing_date'     => '',
			),
		);
		abcc_assert_true( false !== strpos( abcc_format_job_usage_note( 902 ), '1 call,' ) );
	}
);

abcc_test(
	'the image picker keeps a still-served retired selection instead of silently switching it',
	function () {
		$options = abcc_get_image_model_picker_options( 'openai', 'gpt-image-1.5' );
		abcc_assert_array_has_key( 'gpt-image-1.5', $options );
		abcc_assert_same( 'gpt-image-1.5', array_keys( $options )[0] );

		abcc_assert_false( isset( abcc_get_image_model_picker_options( 'openai', 'gpt-image-2.5-flare' )['gpt-image-1.5'] ) );
		abcc_assert_false( isset( abcc_get_image_model_picker_options( 'openai', 'made-up' )['made-up'] ), 'Unknown IDs are never offered.' );
	}
);
