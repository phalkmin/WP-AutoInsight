<?php
/**
 * Regression tests for the WordPress.org review request notice (v4.6).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Put the site in a state where every show-condition holds for user 1.
 *
 * @return void
 */
function abcc_test_make_review_notice_eligible() {
	abcc_update_setting( 'abcc_generation_success_count', 5 );
	abcc_update_setting( 'abcc_first_generation_at', time() - 15 * DAY_IN_SECONDS );
	abcc_update_setting( 'abcc_last_generation_error_at', 0 );
}

/**
 * Pretend the current admin screen has the given ID.
 *
 * @param string $id Screen ID.
 * @return void
 */
function abcc_test_set_screen( $id ) {
	$screen                              = new stdClass();
	$screen->id                          = $id;
	$GLOBALS['abcc_test_current_screen'] = $screen;
}

/**
 * Capture the notice markup.
 *
 * @return string
 */
function abcc_test_render_review_notice() {
	ob_start();
	abcc_render_review_notice();
	return (string) ob_get_clean();
}

/**
 * Run the AJAX handler with a posted action type and return the JSON envelope.
 *
 * @param string $action_type Posted action_type.
 * @return array|null
 */
function abcc_test_run_review_action( $action_type ) {
	$_POST['action_type']           = $action_type;
	$GLOBALS['abcc_test_last_json'] = null;
	try {
		abcc_handle_review_notice_action();
	} finally {
		unset( $_POST['action_type'] );
	}
	return $GLOBALS['abcc_test_last_json'];
}

abcc_test(
	'review notice shows once every condition holds',
	function () {
		abcc_test_make_review_notice_eligible();

		abcc_assert_true( abcc_should_show_review_notice( 1 ), 'An admin with 5 posts over 15 days and no recent error should see the notice.' );
	}
);

abcc_test(
	'review notice is hidden for users without manage_options',
	function () {
		abcc_test_make_review_notice_eligible();
		$GLOBALS['abcc_test_current_user_caps']['manage_options'] = false;

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'Non-admins must never see the review notice.' );
	}
);

abcc_test(
	'review notice waits for five successful posts',
	function () {
		abcc_test_make_review_notice_eligible();
		abcc_update_setting( 'abcc_generation_success_count', 4 );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'Four posts are not enough.' );
	}
);

abcc_test(
	'review notice waits fourteen days after the first generation',
	function () {
		abcc_test_make_review_notice_eligible();
		abcc_update_setting( 'abcc_first_generation_at', time() - 13 * DAY_IN_SECONDS );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'Thirteen days is too soon.' );

		abcc_update_setting( 'abcc_first_generation_at', 0 );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'Without a first-generation timestamp the clock has not started.' );
	}
);

abcc_test(
	'review notice stays hidden once dismissed',
	function () {
		abcc_test_make_review_notice_eligible();
		update_user_meta( 1, 'abcc_review_notice_state', array( 'done' => true ) );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'A dismissed notice must not come back.' );
	}
);

abcc_test(
	'review notice stays away for a day after a generation error',
	function () {
		abcc_test_make_review_notice_eligible();
		abcc_update_setting( 'abcc_last_generation_error_at', time() - HOUR_IN_SECONDS );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'An error an hour ago must suppress the notice.' );

		abcc_update_setting( 'abcc_last_generation_error_at', time() - 25 * HOUR_IN_SECONDS );

		abcc_assert_true( abcc_should_show_review_notice( 1 ), 'An error 25 hours ago no longer suppresses it.' );
	}
);

abcc_test(
	'snooze hides the notice for thirty days and counts',
	function () {
		abcc_test_make_review_notice_eligible();
		$before = time();

		$state = abcc_apply_review_notice_action( 1, 'snooze' );

		abcc_assert_same( 1, $state['snooze_count'] );
		abcc_assert_false( $state['done'], 'A first snooze is not a dismissal.' );
		abcc_assert_true(
			$state['snoozed_until'] >= $before + 30 * DAY_IN_SECONDS && $state['snoozed_until'] <= time() + 30 * DAY_IN_SECONDS,
			'snoozed_until should be about 30 days out.'
		);
		abcc_assert_same( $state, abcc_get_review_notice_state( 1 ), 'The state is persisted in user meta.' );
		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'A snoozed notice is hidden.' );

		$state['snoozed_until'] = time() - 1;
		update_user_meta( 1, 'abcc_review_notice_state', $state );

		abcc_assert_true( abcc_should_show_review_notice( 1 ), 'An expired snooze shows the notice again.' );
	}
);

abcc_test(
	'the notice stops after the second snooze expires',
	function () {
		abcc_test_make_review_notice_eligible();

		abcc_apply_review_notice_action( 1, 'snooze' );
		$second = abcc_apply_review_notice_action( 1, 'snooze' );
		abcc_assert_same( 2, $second['snooze_count'] );
		abcc_assert_false( $second['done'], 'Two snoozes are still snoozes.' );

		$second['snoozed_until'] = time() - 1;
		update_user_meta( 1, 'abcc_review_notice_state', $second );

		abcc_assert_false( abcc_should_show_review_notice( 1 ), 'The notice must not return for a third snooze.' );

		$third = abcc_apply_review_notice_action( 1, 'snooze' );
		abcc_assert_true( $third['done'], 'A direct action past the cap still fails closed.' );
		abcc_assert_same( 2, $third['snooze_count'], 'The snooze count stops at the cap.' );
	}
);

abcc_test(
	'done and dismiss hide the notice forever',
	function () {
		foreach ( array( 'done', 'dismiss' ) as $action ) {
			$GLOBALS['abcc_test_user_meta'] = array();
			abcc_test_make_review_notice_eligible();

			$state = abcc_apply_review_notice_action( 1, $action );

			abcc_assert_true( $state['done'], $action . ' must set done.' );
			abcc_assert_false( abcc_should_show_review_notice( 1 ), $action . ' must hide the notice.' );
		}
	}
);

abcc_test(
	'review notice handler rejects users without manage_options',
	function () {
		abcc_test_make_review_notice_eligible();
		$GLOBALS['abcc_test_current_user_caps']['manage_options'] = false;

		$response = abcc_test_run_review_action( 'dismiss' );

		abcc_assert_false( $response['success'], 'Non-admins must not be able to change the state.' );
		abcc_assert_false( abcc_get_review_notice_state( 1 )['done'], 'The state must be untouched.' );
	}
);

abcc_test(
	'review notice handler fails closed on a bad nonce',
	function () {
		abcc_test_make_review_notice_eligible();
		$GLOBALS['abcc_test_nonce_valid'] = false;

		$threw = false;
		try {
			abcc_test_run_review_action( 'dismiss' );
		} catch ( Exception $e ) {
			$threw = 'abcc_test_nonce_failure' === $e->getMessage();
		}

		abcc_assert_true( $threw, 'check_ajax_referer must stop the handler.' );
		abcc_assert_false( abcc_get_review_notice_state( 1 )['done'], 'The state must be untouched.' );
	}
);

abcc_test(
	'review notice handler rejects unknown action types',
	function () {
		abcc_test_make_review_notice_eligible();

		$response = abcc_test_run_review_action( 'later' );

		abcc_assert_false( $response['success'], 'An unknown action_type is an error.' );
		abcc_assert_true( '' !== (string) $response['data']['message'], 'The error carries a user-facing message.' );
		abcc_assert_same(
			array(
				'snoozed_until' => 0,
				'snooze_count'  => 0,
				'done'          => false,
			),
			abcc_get_review_notice_state( 1 )
		);
	}
);

abcc_test(
	'review notice handler snoozes and dismisses through AJAX',
	function () {
		abcc_test_make_review_notice_eligible();

		$response = abcc_test_run_review_action( 'snooze' );

		abcc_assert_true( $response['success'], 'A valid snooze succeeds.' );
		abcc_assert_same( 1, $response['data']['state']['snooze_count'] );
		abcc_assert_same( 1, abcc_get_review_notice_state( 1 )['snooze_count'] );

		$response = abcc_test_run_review_action( 'done' );

		abcc_assert_true( $response['success'], 'Leaving a review succeeds.' );
		abcc_assert_true( abcc_get_review_notice_state( 1 )['done'], 'done is persisted.' );
	}
);

abcc_test(
	'success recorder counts posts and stamps the first generation once',
	function () {
		$before = time();

		abcc_record_generation_success();

		abcc_assert_same( 1, (int) abcc_get_setting( 'abcc_generation_success_count', 0 ) );
		$first = (int) abcc_get_setting( 'abcc_first_generation_at', 0 );
		abcc_assert_true( $first >= $before && $first <= time(), 'The first success stamps the clock.' );

		abcc_update_setting( 'abcc_first_generation_at', 1000 );
		abcc_record_generation_success();

		abcc_assert_same( 2, (int) abcc_get_setting( 'abcc_generation_success_count', 0 ) );
		abcc_assert_same( 1000, (int) abcc_get_setting( 'abcc_first_generation_at', 0 ), 'Later successes keep the original timestamp.' );
	}
);

abcc_test(
	'failure recorder stamps the last error',
	function () {
		$before = time();

		abcc_record_generation_failure();

		$last = (int) abcc_get_setting( 'abcc_last_generation_error_at', 0 );
		abcc_assert_true( $last >= $before && $last <= time(), 'A failure stamps the last-error time.' );
	}
);

abcc_test(
	'a job failed for AI being off still counts as a recent error',
	function () {
		$GLOBALS['abcc_test_ai_supported'] = false;

		$job_id                                     = 961;
		$GLOBALS['abcc_test_post_types'][ $job_id ] = ABCC_Job::POST_TYPE;
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_status']     = ABCC_Job::STATUS_QUEUED;
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_payload']    = array(
			'source'   => 'manual',
			'keywords' => array( 'test' ),
		);
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_created_by'] = 1;

		abcc_process_generation_job( $job_id );

		abcc_assert_same( ABCC_Job::STATUS_FAILED, $GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_status'] );
		abcc_assert_true( (int) abcc_get_setting( 'abcc_last_generation_error_at', 0 ) > 0, 'abcc_mark_job_failed must stamp the last error.' );
	}
);

abcc_test(
	'review notice renders only on plugin screens and the posts list',
	function () {
		abcc_test_make_review_notice_eligible();

		abcc_test_set_screen( 'dashboard' );
		abcc_assert_same( '', abcc_test_render_review_notice(), 'The dashboard is not a plugin screen.' );

		abcc_test_set_screen( 'edit-page' );
		abcc_assert_same( '', abcc_test_render_review_notice(), 'The Pages list is not the Posts list.' );

		abcc_test_set_screen( 'toplevel_page_automated-blog-content-creator-post' );
		$html = abcc_test_render_review_notice();
		abcc_assert_true( false !== strpos( $html, 'notice notice-info abcc-review-notice' ), 'Plugin screen renders the notice.' );
		abcc_assert_true( false !== strpos( $html, 'data-action-type="snooze"' ), 'Maybe later button is present.' );
		abcc_assert_true( false !== strpos( $html, 'data-action-type="dismiss"' ), 'Don\'t ask again button is present.' );
		abcc_assert_true( false !== strpos( $html, 'reviews/' ), 'The review link points at the reviews page.' );
		abcc_assert_true( false !== strpos( $html, 'rel="noopener noreferrer"' ), 'External links open safely.' );
		abcc_assert_true( false !== strpos( $html, 'data-nonce="' ), 'The nonce rides on the notice for the script.' );

		abcc_test_set_screen( 'edit-post' );
		abcc_assert_true( false !== strpos( abcc_test_render_review_notice(), 'abcc-review-notice' ), 'The Posts list renders the notice.' );

		abcc_update_setting( 'abcc_generation_success_count', 1 );
		abcc_assert_same( '', abcc_test_render_review_notice(), 'Conditions still gate the plugin screen.' );
	}
);
