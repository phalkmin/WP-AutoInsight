<?php
/**
 * One writer for provider health: transient + option row, with a timestamp.
 */

abcc_test(
	'recording a validation writes the transient and the health row together',
	function () {
		abcc_record_provider_validation( 'openai', true, 'Verified just now' );

		$transient = get_transient( 'abcc_last_validation_openai' );
		abcc_assert_same( 'verified', $transient['status'] );
		abcc_assert_true( ! empty( $transient['timestamp'] ), 'Validate-written transients need a timestamp for the 24h staleness check.' );

		$rows = get_option( 'abcc_provider_health', array() );
		abcc_assert_same( 'connected', $rows['openai']['status'] );

		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'openai' )['health'] );
	}
);

abcc_test(
	'recording a failure marks the snapshot failed',
	function () {
		abcc_record_provider_validation( 'openai', false, 'invalid_api_key' );
		abcc_assert_same( 'failed', abcc_get_provider_health_snapshot( 'openai' )['health'] );
	}
);

abcc_test(
	'unknown providers are ignored by the writer',
	function () {
		abcc_record_provider_validation( 'nope', true );
		abcc_assert_same( false, get_transient( 'abcc_last_validation_nope' ) );
	}
);

abcc_test(
	'daily health check validates wp-config keys instead of wiping them',
	function () {
		$GLOBALS['abcc_test_options']['stability_api_key'] = 'sk-stability';
		abcc_test_queue_http_response( abcc_test_fake_generation( 'ok' ) );
		abcc_run_provider_health_check();

		$rows = get_option( 'abcc_provider_health', array() );
		abcc_assert_true( 'no_key' !== ( $rows['openai']['status'] ?? 'no_key' ), 'A wp-config key must be probed, not reported as missing.' );
	}
);

abcc_test(
	'validate handler refuses an empty key without calling the provider',
	function () {
		$GLOBALS['abcc_test_last_json'] = null;
		$_POST                          = array(
			'provider' => 'claude',
			'api_key'  => '',
		);
		$GLOBALS['abcc_http_queue']     = array();

		abcc_handle_validate_api_key();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'Enter an API key first.', $GLOBALS['abcc_test_last_json']['data']['message'] );
		abcc_assert_same( false, get_transient( 'abcc_last_validation_claude' ), 'No provider call, no cached failure.' );
		$_POST = array();
	}
);

abcc_test(
	'an auth error during generation marks the provider failed; a rate limit does not',
	function () {
		abcc_record_provider_validation( 'openai', true );

		abcc_note_provider_outcome( 'openai', new WP_Error( 'abcc_provider_rate_limited', '429' ) );
		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'openai' )['health'], 'A 429 says nothing about the key.' );

		abcc_note_provider_outcome( 'openai', new WP_Error( 'abcc_provider_auth_error', '401' ) );
		abcc_assert_same( 'failed', abcc_get_provider_health_snapshot( 'openai' )['health'] );
	}
);

abcc_test(
	'a successful generation marks a failed provider verified, and does not rewrite a fresh verification',
	function () {
		abcc_record_provider_validation( 'openai', false, 'bad key' );
		abcc_note_provider_outcome( 'openai', null );
		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'openai' )['health'] );

		$before = get_transient( 'abcc_last_validation_openai' );
		abcc_note_provider_outcome( 'openai', null );
		abcc_assert_same( $before, get_transient( 'abcc_last_validation_openai' ), 'Verified within the hour: no second write.' );
	}
);

abcc_test(
	'recording a missing key writes a no_key row and drops the stale validation',
	function () {
		abcc_record_provider_validation( 'claude', true );
		abcc_record_provider_no_key( 'claude' );

		$rows = get_option( 'abcc_provider_health', array() );
		abcc_assert_same( 'no_key', $rows['claude']['status'] );
		abcc_assert_true( is_int( $rows['claude']['timestamp'] ) && $rows['claude']['timestamp'] > 0, 'The row carries a timestamp.' );
		abcc_assert_same( false, get_transient( 'abcc_last_validation_claude' ), 'The old verification no longer applies.' );

		abcc_record_provider_no_key( 'nope' );
		abcc_assert_false( isset( get_option( 'abcc_provider_health', array() )['nope'] ), 'Unknown providers are ignored.' );
	}
);

abcc_test(
	'a 403 during generation does not mark the key rejected; a 401 does',
	function () {
		abcc_record_provider_validation( 'openai', true );

		abcc_note_provider_outcome( 'openai', new WP_Error( 'abcc_provider_auth_error', 'no access', array( 'status' => 403 ) ) );
		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'openai' )['health'], 'A 403 is a model-access problem, not a bad key.' );

		abcc_note_provider_outcome( 'openai', new WP_Error( 'abcc_provider_auth_error', 'bad key', array( 'status' => 401 ) ) );
		abcc_assert_same( 'failed', abcc_get_provider_health_snapshot( 'openai' )['health'] );
	}
);

abcc_test(
	'validate handler tests a typed key without recording it as the provider health',
	function () {
		$GLOBALS['abcc_test_options']['claude_api_key'] = 'sk-ant-saved';
		abcc_record_provider_validation( 'claude', true );

		$GLOBALS['abcc_test_last_json'] = null;
		$_POST                          = array(
			'provider' => 'claude',
			'api_key'  => 'sk-ant-typo',
		);
		$GLOBALS['abcc_http_queue'][]   = array(
			'response' => array( 'code' => 401 ),
			'body'     => '{"error":{"message":"invalid x-api-key"}}',
		);

		abcc_handle_validate_api_key();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'], 'The typed key is reported as failing.' );
		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'claude' )['health'], 'The saved key stays healthy until it is replaced.' );
		$_POST                      = array();
		$GLOBALS['abcc_http_queue'] = array();
	}
);

abcc_test(
	'validate handler with an empty field tests the configured key and records the outcome',
	function () {
		$GLOBALS['abcc_test_options']['claude_api_key'] = 'sk-ant-saved';
		$GLOBALS['abcc_test_last_json']                 = null;
		$_POST                        = array(
			'provider' => 'claude',
			'api_key'  => '',
		);
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 401 ),
			'body'     => '{"error":{"message":"invalid x-api-key"}}',
		);

		abcc_handle_validate_api_key();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'failed', abcc_get_provider_health_snapshot( 'claude' )['health'], 'The key generation uses was rejected.' );
		$_POST                      = array();
		$GLOBALS['abcc_http_queue'] = array();
	}
);
