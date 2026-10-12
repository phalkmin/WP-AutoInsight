<?php
/**
 * Gemini 2.5 thinking tokens must not eat small output budgets (C-01).
 */

function abcc_test_gemini_body( $parts, $finish = 'STOP' ) {
	$candidate = array( 'finishReason' => $finish );
	if ( null !== $parts ) {
		$candidate['content'] = array( 'parts' => $parts );
	}
	return wp_json_encode(
		array(
			'candidates'    => array( $candidate ),
			'usageMetadata' => array( 'totalTokenCount' => 100 ),
		)
	);
}

abcc_test(
	'gemini flash requests disable thinking; pro sends the minimum and raises the ceiling',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 0, $body['generationConfig']['thinkingConfig']['thinkingBudget'] );
		abcc_assert_same( 300, $body['generationConfig']['maxOutputTokens'] );

		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-pro',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 128, $body['generationConfig']['thinkingConfig']['thinkingBudget'] );
		abcc_assert_same( 428, $body['generationConfig']['maxOutputTokens'] );
	}
);

abcc_test(
	'gemini 3.5 flash carries no thinkingConfig until the model audit confirms it',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-3.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_false( isset( $body['generationConfig']['thinkingConfig'] ) );
	}
);

abcc_test(
	'gemini MAX_TOKENS with no parts is an empty completion, not a parse error',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( null, 'MAX_TOKENS' ) );
		$result = abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 100,
			)
		);
		abcc_assert_same( 'abcc_provider_empty_completion', $result['error']->get_error_code() );
		abcc_assert_true( $result['truncated'] );
	}
);

abcc_test(
	'gemini thought parts are skipped and text parts are joined',
	function () {
		abcc_test_queue_http_response(
			abcc_test_gemini_body(
				array(
					array(
						'thought' => true,
						'text'    => 'planning',
					),
					array( 'text' => '<p>A</p>' ),
					array( 'text' => '<p>B</p>' ),
				)
			)
		);
		$result = abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		abcc_assert_same( array( '<p>A</p>', '<p>B</p>' ), array_values( array_filter( $result['content'], 'strlen' ) ) );
	}
);

abcc_test(
	'openai, claude and perplexity parsers are untouched',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>x</p>' ) );
		abcc_assert_same(
			null,
			abcc_call_provider_api(
				'openai',
				'gpt-5.4-mini',
				'p',
				array(
					'api_key'    => 'k',
					'max_tokens' => 100,
				)
			)['error']
		);

		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content'     => array( array( 'text' => '<p>x</p>' ) ),
					'stop_reason' => 'end_turn',
				)
			)
		);
		abcc_assert_same(
			null,
			abcc_call_provider_api(
				'claude',
				'claude-sonnet-4-6',
				'p',
				array(
					'api_key'    => 'k',
					'max_tokens' => 100,
				)
			)['error']
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>x</p>' ) );
		abcc_assert_same(
			null,
			abcc_call_provider_api(
				'perplexity',
				'sonar',
				'p',
				array(
					'api_key'    => 'k',
					'max_tokens' => 100,
				)
			)['error']
		);
	}
);

abcc_test(
	'gemini body with no parts and a SAFETY finish is a parse error, not an empty completion',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( null, 'SAFETY' ) );
		$result = abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		abcc_assert_same( 'abcc_provider_parse_error', $result['error']->get_error_code() );
	}
);
