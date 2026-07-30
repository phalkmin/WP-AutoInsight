<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'post-status onboarding handler validates nonce and persists the choice',
	function () {
		$GLOBALS['abcc_test_options'] = array();

		$_POST                          = array( 'nonce' => 'x', 'status' => 'publish' );
		$GLOBALS['abcc_test_last_json'] = null;

		abcc_handle_onboarding_post_status();

		abcc_assert_same( 'publish', abcc_get_setting( 'abcc_default_post_status' ) );
		abcc_assert_true( ! empty( $GLOBALS['abcc_test_last_json']['success'] ), 'Handler should send a success response.' );

		// Junk status falls back to draft via the sanitizer.
		$_POST = array( 'nonce' => 'x', 'status' => 'garbage' );
		abcc_handle_onboarding_post_status();
		abcc_assert_same( 'draft', abcc_get_setting( 'abcc_default_post_status' ) );

		$_POST = array();
	}
);

abcc_test(
	'first-topic onboarding handler creates a topic',
	function () {
		$GLOBALS['abcc_test_options']   = array();
		$GLOBALS['abcc_test_last_json'] = null;

		$_POST = array(
			'nonce'     => 'x',
			'title'     => 'Weekly roundup',
			'prompt'    => 'Write a weekly roundup about {keyword}.',
			'frequency' => 'weekly',
		);

		abcc_handle_onboarding_first_topic();

		abcc_assert_true( ! empty( $GLOBALS['abcc_test_last_json']['success'] ), 'Should succeed.' );
		$topic_id = $GLOBALS['abcc_test_last_json']['data']['topic_id'];
		$topic    = abcc_get_topic( $topic_id );
		abcc_assert_same( 'Weekly roundup', $topic['title'] );

		$_POST = array();
	}
);

abcc_test(
	'completion handler marks onboarding done',
	function () {
		$GLOBALS['abcc_test_options']   = array();
		$GLOBALS['abcc_test_last_json'] = null;

		$_POST = array( 'nonce' => 'x' );
		abcc_handle_onboarding_complete();

		abcc_assert_true( (bool) get_option( 'abcc_onboarding_completed', false ), 'Onboarding must be marked complete.' );
		abcc_assert_true( ! empty( $GLOBALS['abcc_test_last_json']['success'] ), 'Should send success.' );

		$_POST = array();
	}
);

abcc_test(
	'skip handler also marks onboarding done (regression on existing behavior)',
	function () {
		$GLOBALS['abcc_test_options']   = array();
		$GLOBALS['abcc_test_last_json'] = null;

		$_POST = array( 'nonce' => 'x' );
		abcc_handle_onboarding_skip();
		abcc_assert_true( (bool) get_option( 'abcc_onboarding_completed', false ), 'Skip must complete onboarding.' );

		$_POST = array();
	}
);

abcc_test(
	'onboarding step model is provider-first with four numbered steps',
	function () {
		$steps = abcc_get_onboarding_steps();

		// Numbered steps in order.
		$numbered = array();
		foreach ( $steps as $s ) {
			if ( isset( $s['num'] ) && $s['num'] > 0 ) {
				$numbered[ $s['num'] ] = $s['key'];
			}
		}
		ksort( $numbered );

		abcc_assert_equals( array( 1 => 'provider', 2 => 'post_status', 3 => 'first_topic', 4 => 'try_audio' ), $numbered );

		// A terminal success step exists, unnumbered.
		$has_success = false;
		foreach ( $steps as $s ) {
			if ( 'success' === $s['key'] ) {
				$has_success = true;
			}
		}
		abcc_assert_true( $has_success, 'A success step must exist.' );
	}
);
