<?php
/**
 * Regression tests for the provider health probe.
 *
 * The probe must run the real connection test — it previously fell through
 * to `return true` for every saved key (false-green health dashboard).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'health probe returns false for an empty key',
	function () {
		if ( ! function_exists( 'abcc_validate_provider_api_key_probe' ) ) {
			require dirname( __DIR__ ) . '/includes/ajax-handlers.php';
		}

		abcc_assert_false(
			abcc_validate_provider_api_key_probe( 'openai', '' ),
			'Empty key must not report connected.'
		);
	}
);

abcc_test(
	'health probe returns false for an unknown provider (no optimistic true)',
	function () {
		if ( ! function_exists( 'abcc_validate_provider_api_key_probe' ) ) {
			require dirname( __DIR__ ) . '/includes/ajax-handlers.php';
		}

		abcc_assert_false(
			abcc_validate_provider_api_key_probe( 'not-a-provider', 'sk-some-key' ),
			'Unknown provider must report failed, not optimistic connected.'
		);
	}
);

abcc_test(
	'health probe returns true when the real connection test succeeds',
	function () {
		if ( ! function_exists( 'abcc_validate_provider_api_key_probe' ) ) {
			require dirname( __DIR__ ) . '/includes/ajax-handlers.php';
		}

		// Stability's connection test succeeds for any non-empty key without HTTP.
		abcc_assert_true(
			abcc_validate_provider_api_key_probe( 'stability', 'sk-valid-key' ),
			'Successful connection test must report connected.'
		);
	}
);

abcc_test(
	'health probe returns false when the connection test reports failure',
	function () {
		if ( ! function_exists( 'abcc_validate_provider_api_key_probe' ) ) {
			require dirname( __DIR__ ) . '/includes/ajax-handlers.php';
		}

		// Queue a failed HTTP response for a provider whose test makes a request.
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'invalid key' ) ) ),
		);

		abcc_assert_false(
			abcc_validate_provider_api_key_probe( 'claude', 'sk-bad-key' ),
			'Failed connection test must report failed.'
		);
		abcc_assert_true(
			empty( $GLOBALS['abcc_http_queue'] ),
			'Probe must have consumed the queued HTTP response (real connection test ran).'
		);
	}
);
