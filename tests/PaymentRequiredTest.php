<?php
/**
 * HTTP 402 (out of credits or over the key's spending limit) has its own code
 * and message; it is neither "model gone" nor something to report upstream.
 */

abcc_test(
	'a 402 from a wp_remote_post provider is classified as payment required',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => '{"error":{"message":"Insufficient credits"}}',
		);
		$result                       = abcc_call_provider_api( 'claude', 'claude-sonnet-4-6', 'p', array( 'api_key' => 'k' ) );

		abcc_assert_same( 'abcc_provider_payment_required', $result['error']->get_error_code() );
		abcc_assert_false( abcc_is_retryable_generation_error( $result['error'] ), 'Credits do not come back on a retry.' );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_provider_payment_required' ) );

		$message = abcc_format_generation_error( $result['error'], array( 'provider' => 'claude' ) );
		abcc_assert_true( false !== strpos( $message, 'credits' ), $message );
		abcc_assert_false( strpos( $message, 'no longer be available' ) );
		abcc_assert_true( '' !== abcc_get_generation_error_next_step( 'abcc_provider_payment_required' ) );
	}
);

abcc_test(
	'a 402 from the OpenAI client is classified the same way',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 402 ),
			'body'     => '{"error":{"message":"Insufficient credits"}}',
		);
		$result                       = abcc_call_provider_api( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ) );

		abcc_assert_same( 'abcc_provider_payment_required', $result['error']->get_error_code() );
	}
);
