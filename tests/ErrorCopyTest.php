<?php
/**
 * Stored error copy is ASCII-safe and names the real navigation path (P1-08, P1-20).
 */

abcc_test(
	'formatted generation errors avoid non-ASCII arrows and the wrong Settings path',
	function () {
		$codes = array( 'abcc_no_api_key', 'abcc_provider_auth_error', 'abcc_provider_rate_limited', 'abcc_provider_server_error', 'abcc_provider_text_unsupported', 'abcc_unknown_provider', 'http_request_failed', 'abcc_provider_empty_completion', 'abcc_provider_parse_error', 'abcc_seo_unparseable', 'abcc_provider_http_error', 'context_overflow', 'some_unmapped_code' );
		foreach ( $codes as $code ) {
			$msg = abcc_format_generation_error( new WP_Error( $code, 'x', array( 'status' => 404 ) ), array( 'provider' => 'openai' ) );
			abcc_assert_false( false !== strpos( $msg, '→' ), $code . ' must not carry a non-ASCII arrow: ' . $msg );
			abcc_assert_false( false !== strpos( $msg, 'Settings → Connections' ), $code . ': Connections is a top-level tab.' );
			abcc_assert_false( false !== strpos( $msg, 'Settings > Connections' ), $code . ': Connections is a top-level tab.' );
		}
	}
);

abcc_test(
	'job error storage transliterates arrows defensively',
	function () {
		$job = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		abcc_mark_job_failed( $job, 'Go to Connections → API Keys' );
		abcc_assert_same( 'Go to Connections > API Keys', get_post_meta( $job, '_abcc_job_error', true ) );
	}
);

abcc_test(
	'report-this-error link is hidden for user-side error codes',
	function () {
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_provider_auth_error' ) );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_no_api_key' ) );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_provider_rate_limited' ) );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_ai_disabled' ) );
		abcc_assert_false( abcc_should_offer_error_report( 'context_overflow' ) );
		abcc_assert_false( abcc_should_offer_error_report( 'abcc_job_never_started' ) );
		abcc_assert_true( abcc_should_offer_error_report( 'abcc_provider_parse_error' ) );
		abcc_assert_true( abcc_should_offer_error_report( '' ) );
	}
);
