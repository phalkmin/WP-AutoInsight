<?php
/**
 * Regression tests for abcc_call_provider_api() (v4.2 Unit B).
 *
 * The wrapper must return the normalized tuple for all four text providers,
 * detect each provider's truncation signal, and never throw on failure.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a canned OpenAI/Perplexity-shaped chat completion response.
 *
 * @param string $text          Message content.
 * @param string $finish_reason finish_reason value.
 * @param array  $extra         Extra top-level body keys (e.g. citations).
 * @return array
 */
function abcc_test_chat_completion_response( $text, $finish_reason = 'stop', $extra = array() ) {
	$body = array_merge(
		array(
			'choices' => array(
				array(
					'message'       => array( 'content' => $text ),
					'finish_reason' => $finish_reason,
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
			),
		),
		$extra
	);

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode( $body ),
	);
}

// --- Tuple shape per provider ---

abcc_test(
	'wrapper returns normalized tuple for Claude',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => "Line one\nLine two" ) ),
					'stop_reason' => 'end_turn',
					'usage'       => array(
						'input_tokens'  => 12,
						'output_tokens' => 34,
					),
				)
			),
		);

		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_same( array( 'Line one', 'Line two' ), $result['content'] );
		abcc_assert_same( false, $result['truncated'] );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 34, $result['usage']['output_tokens'] );
	}
);

abcc_test(
	'wrapper returns normalized tuple for Gemini',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates'    => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => 'Gemini line' ) ) ),
							'finishReason' => 'STOP',
						),
					),
					'usageMetadata' => array( 'totalTokenCount' => 99 ),
				)
			),
		);

		$result = abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'prompt', array( 'api_key' => 'g-test' ) );

		abcc_assert_same( array( 'Gemini line' ), $result['content'] );
		abcc_assert_same( false, $result['truncated'] );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 99, $result['usage']['totalTokenCount'] );
	}
);

abcc_test(
	'wrapper returns normalized tuple for OpenAI and detects finish_reason length',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( "One\nTwo", 'length' );

		$result = abcc_call_provider_api( 'openai', 'gpt-4.1-mini', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_same( array( 'One', 'Two' ), $result['content'] );
		abcc_assert_true( $result['truncated'], 'finish_reason=length must set truncated.' );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 20, $result['usage']['completion_tokens'] );
	}
);

abcc_test(
	'wrapper returns normalized tuple for Perplexity with citations in raw',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response(
			'Cited line',
			'stop',
			array( 'citations' => array( 'https://example.com/source' ) )
		);

		$result = abcc_call_provider_api( 'perplexity', 'sonar', 'prompt', array( 'api_key' => 'pplx-test' ) );

		abcc_assert_same( array( 'Cited line' ), $result['content'] );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( array( 'https://example.com/source' ), $result['raw']['citations'] );
	}
);

// --- OpenAI reasoning-model parameter mapping (GPT-5.x / o-series) ---

abcc_test(
	'openai gpt-5.x requests use max_completion_tokens and omit sampling params',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'ok' );

		abcc_call_provider_api(
			'openai',
			'gpt-5.4',
			'prompt',
			array(
				'api_key'    => 'sk-test',
				'max_tokens' => 800,
			)
		);

		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );

		abcc_assert_true( isset( $body['max_completion_tokens'] ), 'GPT-5.x must send max_completion_tokens.' );
		abcc_assert_true( ! isset( $body['max_tokens'] ), 'GPT-5.x must not send max_tokens.' );
		abcc_assert_true( ! isset( $body['temperature'] ), 'GPT-5.x must not send temperature.' );
		abcc_assert_true( ! isset( $body['top_p'] ), 'GPT-5.x must not send top_p.' );
		abcc_assert_true( ! isset( $body['frequency_penalty'] ), 'GPT-5.x must not send frequency_penalty.' );
		abcc_assert_true( ! isset( $body['presence_penalty'] ), 'GPT-5.x must not send presence_penalty.' );
	}
);

abcc_test(
	'openai gpt-5.x requests disable reasoning so tokens go to output',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'ok' );

		abcc_call_provider_api(
			'openai',
			'gpt-5.4-mini',
			'prompt',
			array(
				'api_key'    => 'sk-test',
				'max_tokens' => 200,
			)
		);

		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 'none', $body['reasoning_effort'], 'GPT-5.1+ models must send reasoning_effort=none.' );
	}
);

abcc_test(
	'openai o-series requests keep low reasoning with an enlarged budget',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'ok' );

		abcc_call_provider_api(
			'openai',
			'o4-mini',
			'prompt',
			array(
				'api_key'    => 'sk-test',
				'max_tokens' => 200,
			)
		);

		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 'low', $body['reasoning_effort'], 'o-series has no reasoning_effort=none; must send low.' );
		abcc_assert_true( $body['max_completion_tokens'] >= 4000, 'Active reasoning bills hidden tokens against the budget; floor must cover them.' );
	}
);

abcc_test(
	'openai gpt-5.x floors max_completion_tokens so reasoning does not eat tiny budgets',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'A Title' );

		abcc_call_provider_api(
			'openai',
			'gpt-5.4-mini',
			'prompt',
			array(
				'api_key'    => 'sk-test',
				'max_tokens' => 50,
			)
		);

		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_true( $body['max_completion_tokens'] >= 1000, 'Small budgets must be floored for reasoning models.' );
	}
);

abcc_test(
	'openai gpt-4.1 requests keep legacy max_tokens and temperature',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'ok' );

		abcc_call_provider_api(
			'openai',
			'gpt-4.1-2025-04-14',
			'prompt',
			array(
				'api_key'    => 'sk-test',
				'max_tokens' => 800,
			)
		);

		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );

		abcc_assert_true( isset( $body['max_tokens'] ), 'GPT-4.1 must keep max_tokens.' );
		abcc_assert_true( isset( $body['temperature'] ), 'GPT-4.1 must keep temperature.' );
		abcc_assert_true( ! isset( $body['max_completion_tokens'] ), 'GPT-4.1 must not send max_completion_tokens.' );
	}
);

// --- Single-line HTML normalization ---

abcc_test(
	'wrapper splits single-line HTML responses into block-level lines',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response(
			'<h2>Intro</h2><p>First paragraph.</p><p>Second paragraph.</p>'
		);

		$result = abcc_call_provider_api( 'openai', 'gpt-4.1-mini', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_same(
			array( '<h2>Intro</h2>', '<p>First paragraph.</p>', '<p>Second paragraph.</p>', '' ),
			$result['content']
		);
	}
);

// --- Empty completion guard ---

abcc_test(
	'wrapper returns error when the model emits an empty completion',
	function () {
		// HTTP 200, finish_reason=length, empty content: the reasoning-burn
		// signature that previously cascaded into a silent generic failure.
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( '', 'length' );

		$result = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'Empty completion must produce a WP_Error, not empty content lines.' );
		abcc_assert_same( array(), $result['content'] );
		abcc_assert_true(
			false !== strpos( $result['error']->get_error_message(), 'empty completion' ),
			'Error message must name the empty completion.'
		);
	}
);

abcc_test(
	'wrapper returns error on whitespace-only completion for Claude',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => "  \n  " ) ),
					'stop_reason' => 'end_turn',
				)
			),
		);

		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'Whitespace-only completion must produce a WP_Error.' );
		abcc_assert_same( array(), $result['content'] );
	}
);

// --- Truncation detection ---

abcc_test(
	'wrapper detects Claude truncation via stop_reason max_tokens',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => 'cut off mid' ) ),
					'stop_reason' => 'max_tokens',
				)
			),
		);

		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'prompt', array( 'api_key' => 'sk-test' ) );
		abcc_assert_true( $result['truncated'], 'stop_reason=max_tokens must set truncated.' );
		abcc_assert_same( null, $result['error'] );
	}
);

abcc_test(
	'wrapper detects Gemini truncation via finishReason MAX_TOKENS',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => 'cut off' ) ) ),
							'finishReason' => 'MAX_TOKENS',
						),
					),
				)
			),
		);

		$result = abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'prompt', array( 'api_key' => 'g-test' ) );
		abcc_assert_true( $result['truncated'], 'finishReason=MAX_TOKENS must set truncated.' );
	}
);

abcc_test(
	'wrapper detects Perplexity truncation via finish_reason length',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'cut off', 'length' );

		$result = abcc_call_provider_api( 'perplexity', 'sonar', 'prompt', array( 'api_key' => 'pplx-test' ) );
		abcc_assert_true( $result['truncated'], 'finish_reason=length must set truncated.' );
	}
);

// --- Error paths: error set, content empty, no exception ---

abcc_test(
	'wrapper returns error tuple on HTTP 500',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 500 ),
			'body'     => 'Internal Server Error',
		);

		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'HTTP 500 must produce a WP_Error.' );
		abcc_assert_same( array(), $result['content'] );
		abcc_assert_same( false, $result['truncated'] );
	}
);

abcc_test(
	'wrapper returns error tuple on transport WP_Error',
	function () {
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );

		$result = abcc_call_provider_api( 'gemini', 'gemini-2.5-flash', 'prompt', array( 'api_key' => 'g-test' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'Network failure must produce a WP_Error.' );
		abcc_assert_same( array(), $result['content'] );
	}
);

abcc_test(
	'wrapper returns error tuple on malformed response body',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'unexpected' => 'shape' ) ),
		);

		$result = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'prompt', array( 'api_key' => 'sk-test' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'Unparseable body must produce a WP_Error.' );
		abcc_assert_same( array(), $result['content'] );
	}
);

abcc_test(
	'wrapper rejects unknown providers without HTTP',
	function () {
		$queue_before = count( $GLOBALS['abcc_http_queue'] );

		$result = abcc_call_provider_api( 'not-a-provider', 'model', 'prompt', array( 'api_key' => 'key' ) );

		abcc_assert_true( is_wp_error( $result['error'] ), 'Unknown provider must produce a WP_Error.' );
		abcc_assert_same( $queue_before, count( $GLOBALS['abcc_http_queue'] ), 'No HTTP request may be made for unknown providers.' );
	}
);

// --- Adapter contracts preserved ---

abcc_test(
	'claude adapter still returns array of lines (legacy contract)',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => "A\nB" ) ),
					'stop_reason' => 'end_turn',
				)
			),
		);

		$result = abcc_claude_generate_text( 'sk-test', 'prompt', 500, 'claude-sonnet-4-6' );
		abcc_assert_same( array( 'A', 'B' ), $result );
	}
);

abcc_test(
	'perplexity adapter still returns text + citations shape (legacy contract)',
	function () {
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response(
			'Cited',
			'stop',
			array( 'citations' => array( 'https://example.com/a', 'https://example.com/b' ) )
		);

		$result = abcc_perplexity_generate_text( 'pplx-test', 'prompt', 500, 'sonar' );

		abcc_assert_same( array( 'Cited' ), $result['text'] );
		abcc_assert_same( 2, count( $result['citations'] ) );
	}
);

abcc_test(
	'claude adapter returns false on failure (legacy contract)',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'invalid api key' ) ) ),
		);

		$result = abcc_claude_generate_text( 'bad-key', 'prompt', 500, 'claude-sonnet-4-6' );
		abcc_assert_same( false, $result );
	}
);

abcc_test(
	'HTTP failures carry a specific error code per status class',
	function () {
		$cases = array(
			401 => 'abcc_provider_auth_error',
			403 => 'abcc_provider_auth_error',
			429 => 'abcc_provider_rate_limited',
			500 => 'abcc_provider_server_error',
			503 => 'abcc_provider_server_error',
			418 => 'abcc_provider_http_error',
		);

		foreach ( $cases as $status => $expected_code ) {
			$GLOBALS['abcc_http_queue'][] = array(
				'response' => array( 'code' => $status ),
				'body'     => '{"error":"nope"}',
			);

			$result = abcc_call_provider_api(
				'claude',
				'claude-sonnet-4-6',
				'prompt',
				array( 'api_key' => 'sk-test' )
			);

			abcc_assert_true( is_wp_error( $result['error'] ), 'HTTP ' . $status . ' should produce an error.' );
			abcc_assert_same(
				$expected_code,
				$result['error']->get_error_code(),
				'HTTP ' . $status . ' should map to ' . $expected_code . '.'
			);
		}
	}
);
