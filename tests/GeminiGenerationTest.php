<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
/**
 * Regression tests for the wp_remote_post-based Gemini rewrite (B3/B4).
 *
 * gpt.php and onboarding.php are required by the bootstrap after the
 * GeminiAPI SDK dependency is removed.
 */

// abcc_gemini_generate_text — success path.
abcc_test(
	'Gemini generate text returns array of lines on 200 response',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content' => array(
								'parts' => array(
									array( 'text' => "Line one\nLine two\nLine three" ),
								),
							),
						),
					),
				)
			),
		);

		$result = abcc_gemini_generate_text( 'test-key', 'Write something', 500, 'gemini-2.5-flash' );

		if ( false === $result ) {
			throw new Exception( 'Expected array, got false' );
		}
		abcc_assert_same( 'Line one', $result[0] );
		abcc_assert_same( 'Line two', $result[1] );
	}
);

// abcc_gemini_generate_text — HTTP 4xx.
abcc_test(
	'Gemini generate text returns false on 4xx response',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 400 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Bad request' ) ) ),
		);

		$result = abcc_gemini_generate_text( 'bad-key', 'prompt', 500, 'gemini-2.5-flash' );
		abcc_assert_same( false, $result );
	}
);

// abcc_gemini_generate_text — HTTP 5xx.
abcc_test(
	'Gemini generate text returns false on 5xx response',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 503 ),
			'body'     => '',
		);

		$result = abcc_gemini_generate_text( 'test-key', 'prompt', 500, 'gemini-2.5-flash' );
		abcc_assert_same( false, $result );
	}
);

// abcc_gemini_generate_text — WP_Error network failure.
abcc_test(
	'Gemini generate text returns false on WP_Error',
	function () {
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );

		$result = abcc_gemini_generate_text( 'test-key', 'prompt', 500, 'gemini-2.5-flash' );
		abcc_assert_same( false, $result );
	}
);

// abcc_gemini_generate_text — malformed JSON body.
abcc_test(
	'Gemini generate text returns false on malformed JSON',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'not-valid-json{{',
		);

		$result = abcc_gemini_generate_text( 'test-key', 'prompt', 500, 'gemini-2.5-flash' );
		abcc_assert_same( false, $result );
	}
);

// abcc_test_gemini_connection — success path.
abcc_test(
	'Gemini connection test returns success on 200 response',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content' => array(
								'parts' => array(
									array( 'text' => 'Hello' ),
								),
							),
						),
					),
				)
			),
		);

		$result = abcc_test_gemini_connection( 'test-key' );
		abcc_assert_same( true, $result['success'] );
	}
);

// abcc_test_gemini_connection — HTTP error.
abcc_test(
	'Gemini connection test returns failure on non-200 response',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 403 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'API key invalid' ) ) ),
		);

		$result = abcc_test_gemini_connection( 'bad-key' );
		abcc_assert_same( false, $result['success'] );
	}
);

// abcc_test_gemini_connection — WP_Error.
abcc_test(
	'Gemini connection test returns failure on WP_Error',
	function () {
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'Connection refused' );

		$result = abcc_test_gemini_connection( 'test-key' );
		abcc_assert_same( false, $result['success'] );
	}
);

abcc_test(
	'Gemini requests send the key as a header, never in the URL',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => 'Body text.' ) ) ),
							'finishReason' => 'STOP',
						),
					),
				)
			)
		);

		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'Write something.',
			array( 'api_key' => 'test-gemini-key' )
		);

		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_true( is_array( $request ), 'A Gemini request should have been made.' );
		abcc_assert_false(
			strpos( $request['url'], 'key=' ) !== false,
			'Gemini key must not appear in the request URL: ' . $request['url']
		);
		abcc_assert_same(
			'test-gemini-key',
			$request['args']['headers']['x-goog-api-key'] ?? '',
			'Gemini key must be sent as the x-goog-api-key header.'
		);
	}
);
