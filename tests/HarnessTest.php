<?php
/**
 * Tests for the test harness itself.
 */

abcc_test(
	'nonce stubs honour abcc_test_nonce_valid',
	function () {
		$GLOBALS['abcc_test_nonce_valid'] = false;
		abcc_assert_false( wp_verify_nonce( 'x', 'y' ), 'wp_verify_nonce should fail when the flag is false.' );
		abcc_assert_false( check_ajax_referer( 'x', 'y', false ), 'check_ajax_referer should fail when the flag is false.' );

		$GLOBALS['abcc_test_nonce_valid'] = true;
		abcc_assert_true( wp_verify_nonce( 'x', 'y' ), 'wp_verify_nonce should pass when the flag is true.' );
	}
);

abcc_test(
	'state reset clears options, transients and flags',
	function () {
		update_option( 'abcc_harness_probe', 'dirty' );
		set_transient( 'abcc_harness_probe', 'dirty' );
		$GLOBALS['abcc_test_nonce_valid']       = false;
		$GLOBALS['abcc_test_uneditable_posts']  = array( 42 );

		abcc_test_reset_state();

		abcc_assert_false( get_option( 'abcc_harness_probe' ), 'Options should be cleared.' );
		abcc_assert_false( get_transient( 'abcc_harness_probe' ), 'Transients should be cleared.' );
		abcc_assert_true( $GLOBALS['abcc_test_nonce_valid'], 'Nonce flag should reset to true.' );
		abcc_assert_same( array(), $GLOBALS['abcc_test_uneditable_posts'], 'Uneditable posts should reset.' );
	}
);

abcc_test(
	'ABCC_VERSION in tests matches the plugin header',
	function () {
		$header = file_get_contents( dirname( __DIR__ ) . '/auto-post.php' );
		preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $m );

		abcc_assert_true( ! empty( $m[1] ), 'Could not read Version from the plugin header.' );
		abcc_assert_same(
			$m[1],
			ABCC_VERSION,
			'tests/bootstrap.php ABCC_VERSION (' . ABCC_VERSION . ') has drifted from the plugin header (' . $m[1] . ').'
		);
	}
);
