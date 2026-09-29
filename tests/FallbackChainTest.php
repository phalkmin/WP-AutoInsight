<?php
/**
 * Regression tests for the provider fallback chain (v4.5 Unit B).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Queue a raw HTTP status response (non-2xx) for the next provider call.
 *
 * @param int $status HTTP status.
 */
function abcc_test_queue_http_status( $status ) {
	$GLOBALS['abcc_http_queue'][] = array(
		'response' => array( 'code' => $status ),
		'body'     => '{"error":"nope"}',
	);
}

/**
 * Queue a Claude-shaped success response.
 *
 * @param string $text Text.
 */
function abcc_test_queue_claude_success( $text ) {
	$GLOBALS['abcc_http_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'content'     => array( array( 'text' => $text ) ),
				'stop_reason' => 'end_turn',
				'usage'       => array(
					'input_tokens'  => 1,
					'output_tokens' => 2,
				),
			)
		),
	);
}

/**
 * Record every wp_remote_* URL by wrapping the canned queue.
 *
 * The bootstrap only keeps the last request, so tests that need the full
 * sequence read this list.
 */
function abcc_test_reset_http_log() {
	$GLOBALS['abcc_test_http_log'] = array();
	remove_all_filters( 'abcc_fallback_backoff_us' );
	add_filter(
		'abcc_fallback_backoff_us',
		function () {
			return 0;
		}
	);
}

abcc_test(
	'fallback chain sanitizer keeps only registered text-capable provider/model pairs',
	function () {
		abcc_assert_same( array(), abcc_sanitize_fallback_chain( 'nope' ), 'Non-array collapses to empty.' );
		abcc_assert_same( array(), abcc_sanitize_fallback_chain( array( array( 'provider' => 'bogus', 'model' => 'x' ) ) ), 'Unknown provider dropped.' );
		abcc_assert_same( array(), abcc_sanitize_fallback_chain( array( array( 'provider' => 'stability', 'model' => 'x' ) ) ), 'Image-only provider dropped.' );
		abcc_assert_same( array(), abcc_sanitize_fallback_chain( array( array( 'provider' => 'claude', 'model' => 'gpt-5.4' ) ) ), 'Model from another provider dropped.' );

		$kept = abcc_sanitize_fallback_chain(
			array(
				array( 'provider' => 'bogus', 'model' => 'x' ),
				array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6', 'junk' => 1 ),
			)
		);
		abcc_assert_same( array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ), $kept, 'Valid entry kept, re-indexed, extra keys stripped.' );
	}
);

abcc_test(
	'retry classifier only retries transient provider errors',
	function () {
		foreach ( array( 'abcc_provider_rate_limited', 'abcc_provider_server_error', 'http_request_failed' ) as $code ) {
			abcc_assert_true( abcc_is_retryable_generation_error( new WP_Error( $code, 'x' ) ), $code . ' must be retryable.' );
		}
		foreach ( array( 'abcc_provider_auth_error', 'abcc_provider_http_error', 'context_overflow', 'abcc_provider_parse_error', 'abcc_provider_empty_completion', 'abcc_unknown_provider', 'abcc_no_api_key' ) as $code ) {
			abcc_assert_false( abcc_is_retryable_generation_error( new WP_Error( $code, 'x' ) ), $code . ' must not be retryable.' );
		}
		abcc_assert_false( abcc_is_retryable_generation_error( null ) );
	}
);

abcc_test(
	'primary 429 with a keyed fallback retries once on the secondary provider',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'claude_api_key'      => 'sk-claude',
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ),
		);
		abcc_test_queue_http_status( 429 );
		abcc_test_queue_claude_success( 'Rescued.' );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200, array( 'job_id' => 9 ) );

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( array( 'Rescued.' ), $result['content'] );
		abcc_assert_same( 'openai', $result['fallback_from']['provider'] );
		abcc_assert_same( 'claude', $result['served_provider'] );
		abcc_assert_same( 2, $result['attempt'] );
		abcc_assert_true( false !== strpos( (string) $GLOBALS['abcc_http_last_request']['url'], 'api.anthropic.com' ), 'Second call must hit the fallback provider.' );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'Exactly two HTTP calls.' );

		$notes = get_post_meta( 9, '_abcc_job_notes', true );
		abcc_assert_true( is_array( $notes ) && 1 === count( $notes ), 'One job note recorded.' );
		abcc_assert_true( false !== strpos( $notes[0], 'Claude' ) && false !== strpos( $notes[0], 'OpenAI' ), 'Note names both providers: ' . $notes[0] );
	}
);

abcc_test(
	'primary auth error never triggers fallback',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'claude_api_key'      => 'sk-claude',
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ),
		);
		abcc_test_queue_http_status( 401 );
		abcc_test_queue_claude_success( 'Should not be used.' );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		abcc_assert_same( 'abcc_provider_auth_error', $result['error']->get_error_code() );
		abcc_assert_same( 1, count( $GLOBALS['abcc_http_queue'] ), 'Only one HTTP call was made.' );
		abcc_assert_same( null, $result['fallback_from'] );
		abcc_assert_same( 1, $result['attempt'] );
	}
);

abcc_test(
	'fallback entry without a key is skipped with a note',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ),
		);
		abcc_test_queue_http_status( 500 );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200, array( 'job_id' => 11 ) );

		abcc_assert_same( 'abcc_provider_server_error', $result['error']->get_error_code(), 'Primary error is returned.' );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'One call only.' );

		$notes = get_post_meta( 11, '_abcc_job_notes', true );
		abcc_assert_true( is_array( $notes ) && false !== stripos( $notes[0], 'skipped' ), 'Skip note recorded.' );
	}
);

abcc_test(
	'fallback entry whose model cannot fit the prompt is skipped',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'claude_api_key'      => 'sk-claude',
			// Haiku 4.5 has a 200k window; the primary has 1M.
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-haiku-4-5-20251001' ) ),
		);
		abcc_test_queue_http_status( 429 );
		abcc_test_queue_claude_success( 'Should not be used.' );

		$prompt = str_repeat( 'word ', 220000 ); // ~275k tokens.
		$result = abcc_generate_content_detailed( 'sk-openai', $prompt, 'gpt-4.1-mini-2025-04-14', 200, array( 'job_id' => 12 ) );

		abcc_assert_same( 'abcc_provider_rate_limited', $result['error']->get_error_code() );
		abcc_assert_same( 1, count( $GLOBALS['abcc_http_queue'] ), 'Fallback call must not be made.' );

		$notes = get_post_meta( 12, '_abcc_job_notes', true );
		abcc_assert_true( is_array( $notes ) && false !== stripos( $notes[0], 'skipped' ), 'Skip note recorded: ' . wp_json_encode( $notes ) );
	}
);

abcc_test(
	'when both providers fail the primary error is returned and both are noted',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'claude_api_key'      => 'sk-claude',
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ),
		);
		abcc_test_queue_http_status( 503 );
		abcc_test_queue_http_status( 429 );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200, array( 'job_id' => 13 ) );

		abcc_assert_same( 'abcc_provider_server_error', $result['error']->get_error_code(), 'Primary cause wins.' );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'] );
		abcc_assert_same( null, $result['fallback_from'] );

		$notes = get_post_meta( 13, '_abcc_job_notes', true );
		abcc_assert_true( is_array( $notes ) && 1 === count( $notes ), 'One "also failed" note: ' . wp_json_encode( $notes ) );
		abcc_assert_true( false !== stripos( $notes[0], 'also failed' ), $notes[0] );
	}
);

abcc_test(
	'empty fallback chain behaves exactly like 4.4',
	function () {
		abcc_test_reset_http_log();
		abcc_test_queue_http_status( 429 );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		abcc_assert_same( 'abcc_provider_rate_limited', $result['error']->get_error_code() );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'One call.' );
		abcc_assert_same( null, $result['fallback_from'] );
		abcc_assert_same( 'openai', $result['served_provider'] );
	}
);

abcc_test(
	'a fallback entry equal to the primary is skipped',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'      => 'sk-openai',
			'abcc_fallback_chain' => array( array( 'provider' => 'openai', 'model' => 'gpt-4.1-mini-2025-04-14' ) ),
		);
		abcc_test_queue_http_status( 429 );
		abcc_test_queue_http_response( abcc_test_fake_generation( 'nope' ) );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		abcc_assert_same( 'abcc_provider_rate_limited', $result['error']->get_error_code() );
		abcc_assert_same( 1, count( $GLOBALS['abcc_http_queue'] ), 'Same-target entry must not be retried.' );
	}
);

abcc_test(
	'generation hooks fire once across a fallback and see the served provider',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'claude_api_key'      => 'sk-claude',
			'abcc_fallback_chain' => array( array( 'provider' => 'claude', 'model' => 'claude-sonnet-4-6' ) ),
		);
		abcc_test_queue_http_status( 429 );
		abcc_test_queue_claude_success( 'Rescued.' );

		abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		abcc_assert_same( 1, did_action( 'abcc_before_generate' ) );
		abcc_assert_same( 1, did_action( 'abcc_after_generate' ) );

		$after = abcc_test_fired_action_args( 'abcc_after_generate' );
		abcc_assert_same( 2, $after[0]['attempt'] );
		abcc_assert_same( 'claude', $after[0]['served_provider'] );
		abcc_assert_same( 'openai', $after[0]['provider'], 'provider stays the primary the caller asked for.' );
		abcc_assert_false( array_key_exists( 'api_key', $after[0] ) );
	}
);

abcc_test(
	'Perplexity citations are stashed only when the served provider supports them',
	function () {
		abcc_test_reset_http_log();
		$GLOBALS['abcc_test_options'] = array(
			'perplexity_api_key'  => 'pplx',
			'abcc_fallback_chain' => array( array( 'provider' => 'perplexity', 'model' => 'sonar' ) ),
		);
		abcc_test_queue_http_status( 429 );
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'Cited.', 'stop', array( 'citations' => array( 'https://a.example' ) ) );

		$result = abcc_generate_content_detailed( 'sk-openai', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200, array( 'job_id' => 14 ) );

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'perplexity', $result['served_provider'] );
		abcc_assert_same( array( 'https://a.example' ), get_transient( 'abcc_pplx_citations_job_14' ), 'Citations stash keys off the served provider.' );
	}
);
