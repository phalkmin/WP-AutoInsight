<?php
/**
 * User-facing generation error messages.
 */

abcc_test(
	'each error code maps to a distinct actionable message',
	function () {
		$context = array(
			'provider' => 'claude',
			'model'    => 'claude-sonnet-4-6',
		);

		$codes = array(
			'abcc_provider_auth_error',
			'abcc_provider_rate_limited',
			'abcc_provider_server_error',
			'context_overflow',
			'abcc_unknown_provider',
			'abcc_provider_parse_error',
			'abcc_provider_empty_completion',
			'http_request_failed',
			'abcc_no_api_key',
			'abcc_provider_text_unsupported',
		);

		$seen = array();

		foreach ( $codes as $code ) {
			$message = abcc_format_generation_error( new WP_Error( $code, 'raw upstream text' ), $context );

			abcc_assert_true( '' !== $message, $code . ' must produce a message.' );
			abcc_assert_false(
				false !== strpos( $message, 'raw upstream text' ),
				$code . ' must not leak the raw upstream string to the user.'
			);
			abcc_assert_false(
				in_array( $message, $seen, true ),
				$code . ' must not duplicate another code\'s message: ' . $message
			);

			$seen[] = $message;
		}
	}
);

abcc_test(
	'provider name appears in messages that reference a provider',
	function () {
		$message = abcc_format_generation_error(
			new WP_Error( 'abcc_provider_auth_error', 'HTTP 401' ),
			array( 'provider' => 'claude' )
		);

		abcc_assert_true(
			false !== stripos( $message, 'claude' ),
			'An auth error should name the provider: ' . $message
		);
	}
);

abcc_test(
	'unknown codes fall back to a generic message without leaking internals',
	function () {
		$message = abcc_format_generation_error(
			new WP_Error( 'something_nobody_planned_for', 'stack trace here' ),
			array()
		);

		abcc_assert_true( '' !== $message, 'Unknown codes still need a message.' );
		abcc_assert_false(
			false !== strpos( $message, 'stack trace here' ),
			'Generic fallback must not leak the raw message.'
		);
	}
);

abcc_test(
	'a non-WP_Error input is handled without fataling',
	function () {
		abcc_assert_true( '' !== abcc_format_generation_error( null, array() ), 'null must not fatal.' );
		abcc_assert_true( '' !== abcc_format_generation_error( 'a string', array() ), 'A string must not fatal.' );
	}
);

abcc_test(
	'a rate-limited generation records the actionable message on the job',
	function () {
		abcc_update_setting( 'prompt_select', 'claude-sonnet-4-6' );
		abcc_update_setting( 'claude_api_key', 'sk-test' );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{"error":{"message":"rate limit exceeded"}}',
		);

		$payload = abcc_build_generation_payload(
			array(
				'keywords' => array( 'widgets' ),
				'model'    => 'claude-sonnet-4-6',
			)
		);
		$job_id  = abcc_queue_generation_job( $payload );

		// The harness stubs wp_schedule_single_event, so run the worker directly.
		abcc_process_generation_job( $job_id );

		$error = (string) get_post_meta( $job_id, '_abcc_job_error', true );

		abcc_assert_true( '' !== $error, 'A failed job must record an error.' );
		abcc_assert_true(
			false !== stripos( $error, 'rate limit' ),
			'The recorded error should be the actionable message, got: ' . $error
		);
		abcc_assert_false(
			false !== strpos( $error, 'HTTP 429' ),
			'The recorded error should not be the raw upstream string, got: ' . $error
		);
	}
);
