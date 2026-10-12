<?php
/**
 * Nothing that looks like a key reaches debug.log (P1-22).
 */

abcc_test(
	'configured provider keys and common key shapes are masked, prose is untouched',
	function () {
		$GLOBALS['abcc_test_options']['claude_api_key'] = 'zzCUSTOMKEY123456';

		$in  = 'OpenAI API HTTP 401: {"error":{"message":"Incorrect API key provided: sk-proj-ABCDEFGHIJKLMNOP1234. You can find"}} key=AIzaSyA1234567890abcdefghijk pplx-abcdef123456 also sk-ant-api03-SECRETSECRET and zzCUSTOMKEY123456';
		$out = abcc_redact_secrets( $in );

		foreach ( array( 'sk-proj-ABCDEFGHIJKLMNOP1234', 'AIzaSyA1234567890abcdefghijk', 'pplx-abcdef123456', 'sk-ant-api03-SECRETSECRET', 'zzCUSTOMKEY123456' ) as $secret ) {
			abcc_assert_false( false !== strpos( $out, $secret ), 'Still contains ' . $secret . ': ' . $out );
		}
		abcc_assert_true( false !== strpos( $out, 'Incorrect API key provided' ), 'Prose must survive.' );
		abcc_assert_true( false !== strpos( $out, '[redacted]' ) );

		abcc_assert_same( 'Job 12 crashed: timeout after 60s', abcc_redact_secrets( 'Job 12 crashed: timeout after 60s' ) );
	}
);

abcc_test(
	'bearer authorization headers are masked',
	function () {
		$out = abcc_redact_secrets( 'Request failed: Authorization: Bearer abc123DEF456ghi789' );
		abcc_assert_false( false !== strpos( $out, 'abc123DEF456ghi789' ), $out );
		abcc_assert_true( false !== strpos( $out, 'Request failed' ) );
	}
);

abcc_test(
	'key shapes inside ordinary words are not redacted',
	function () {
		$plain = 'risk-assessment failed for task-management-plugin, monkey=banana';
		abcc_assert_same( $plain, abcc_redact_secrets( $plain ) );

		$out = abcc_redact_secrets( $plain . ' with sk-proj-ABCDEFGHIJKLMNOP1234' );
		abcc_assert_true( 0 === strpos( $out, $plain ), $out );
		abcc_assert_false( false !== strpos( $out, 'ABCDEFGHIJKLMNOP1234' ), $out );
	}
);

abcc_test(
	'stored job errors and validation messages are redacted',
	function () {
		$job_id = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Generation Job: Manual',
			)
		);
		abcc_mark_job_failed( $job_id, 'OpenAI rejected sk-proj-ABCDEFGHIJKLMNOP1234 for this request', false, 'abcc_provider_auth_error' );
		$stored = get_post_meta( $job_id, '_abcc_job_error', true );
		abcc_assert_false( false !== strpos( $stored, 'ABCDEFGHIJKLMNOP1234' ), $stored );
		abcc_assert_true( false !== strpos( $stored, 'OpenAI rejected' ) );

		abcc_record_provider_validation( 'openai', false, 'Invalid key sk-proj-ABCDEFGHIJKLMNOP1234' );
		$transient = get_transient( 'abcc_last_validation_openai' );
		abcc_assert_false( false !== strpos( $transient['message'], 'ABCDEFGHIJKLMNOP1234' ), $transient['message'] );
	}
);
