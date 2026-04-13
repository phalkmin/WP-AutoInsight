<?php

abcc_test(
	'provider health recognizes wp-config credentials as configured',
	function () {
		delete_transient( 'abcc_last_validation_openai' );
		$GLOBALS['abcc_test_options']['abcc_provider_health'] = array();

		$snapshot = abcc_get_provider_health_snapshot( 'openai' );

		abcc_assert_same( 'constant', $snapshot['source'] );
		abcc_assert_same( 'stale', $snapshot['health'] );
	}
);

abcc_test(
	'provider health falls back to stored daily health results',
	function () {
		delete_transient( 'abcc_last_validation_openai' );
		$GLOBALS['abcc_test_options']['abcc_provider_health'] = array(
			'openai' => array(
				'status'    => 'connected',
				'timestamp' => time(),
			),
		);

		$snapshot = abcc_get_provider_health_snapshot( 'openai' );

		abcc_assert_same( 'connected', $snapshot['health'] );
		abcc_assert_same( 'verified', $snapshot['last_check']['status'] );
	}
);
