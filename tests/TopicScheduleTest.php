<?php
/**
 * Regression tests for the hourly topic schedule sweep (v4.2 Unit D).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reset the in-memory post store and sweep guard for an isolated sweep test.
 *
 * @return void
 */
function abcc_test_reset_topic_store() {
	$GLOBALS['abcc_test_posts']      = array();
	$GLOBALS['abcc_test_post_meta']  = array();
	$GLOBALS['abcc_test_post_types'] = array();
	$GLOBALS['abcc_test_options']['abcc_topics_last_sweep'] = 0;
	unset( $GLOBALS['abcc_test_options']['abcc_topics_auto_paused'] );
}

/**
 * Create a topic due at the given time.
 *
 * @param int    $next_run Due timestamp.
 * @param string $title    Topic title.
 * @return int Topic ID.
 */
function abcc_test_make_due_topic( $next_run, $title = 'Due topic' ) {
	$topic_id = abcc_create_topic(
		array(
			'title'     => $title,
			'prompt'    => 'Write about it.',
			'frequency' => 'daily',
		)
	);
	update_post_meta( $topic_id, '_abcc_topic_next_run', $next_run );
	return $topic_id;
}

abcc_test(
	'sweep queues only due topics and advances their schedule',
	function () {
		abcc_test_reset_topic_store();
		$now = 1750000000;

		$due_id     = abcc_test_make_due_topic( $now - 10, 'Due' );
		$not_due_id = abcc_test_make_due_topic( $now + 999999, 'Not due' );

		$queued = abcc_run_topic_schedules( $now );

		abcc_assert_same( 1, $queued, 'Exactly one due topic must queue.' );

		$due = abcc_get_topic( $due_id );
		abcc_assert_same( $now, $due['last_run'] );
		abcc_assert_same( $now + DAY_IN_SECONDS, $due['next_run'], 'next_run must advance by the frequency interval.' );
		abcc_assert_true( $due['last_job_id'] > 0, 'Queued job ID must be stored.' );

		$not_due = abcc_get_topic( $not_due_id );
		abcc_assert_same( 0, $not_due['last_run'], 'Not-due topic must be untouched.' );
	}
);

abcc_test(
	'sweep skips paused topics even when overdue',
	function () {
		abcc_test_reset_topic_store();
		$now = 1750000000;

		$topic_id = abcc_test_make_due_topic( $now - 10, 'Paused overdue' );
		abcc_pause_topic( $topic_id );
		update_post_meta( $topic_id, '_abcc_topic_next_run', $now - 10 ); // Pause doesn't clear next_run.

		abcc_assert_same( 0, abcc_run_topic_schedules( $now ) );
	}
);

abcc_test(
	'sweep re-entry guard bails within 5 minutes',
	function () {
		$now = 1750000000;
		$GLOBALS['abcc_test_options']['abcc_topics_last_sweep'] = $now - 60;

		abcc_test_make_due_topic( $now - 10, 'Due during guard' );

		abcc_assert_same( 0, abcc_run_topic_schedules( $now ), 'Sweep must bail when last sweep was under 5 minutes ago.' );
	}
);

abcc_test(
	'queue failure leaves next_run unchanged and increments the failure counter',
	function () {
		abcc_test_reset_topic_store();
		$now = 1750000000;

		$topic_id = abcc_test_make_due_topic( $now - 10, 'Failing' );
		// Provider override with a model is fine; failure comes from the job
		// layer — simulate by deleting the topic's prompt store entry so
		// abcc_queue_topic_generation errors via a missing topic. Instead,
		// force failure deterministically: point wp_insert_post at an error.
		$GLOBALS['abcc_test_force_insert_error'] = true;

		$queued = abcc_run_topic_schedules( $now );
		unset( $GLOBALS['abcc_test_force_insert_error'] );

		abcc_assert_same( 0, $queued );

		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( $now - 10, $topic['next_run'], 'Failed queue must leave next_run unchanged for retry.' );
		abcc_assert_same( 1, $topic['consecutive_failures'] );
		abcc_assert_same( 0, $topic['last_run'], 'Failed queue must not record a run.' );
	}
);

abcc_test(
	'fifth consecutive failure auto-pauses the topic and queues the admin notice',
	function () {
		abcc_test_reset_topic_store();
		$now = 1750000000;

		$topic_id = abcc_test_make_due_topic( $now - 10, 'Doomed schedule' );
		update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 4 );

		$GLOBALS['abcc_test_force_insert_error'] = true;
		abcc_run_topic_schedules( $now );
		unset( $GLOBALS['abcc_test_force_insert_error'] );

		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( 5, $topic['consecutive_failures'] );
		abcc_assert_false( $topic['active'], 'Topic must auto-pause after 5 consecutive failures.' );
		abcc_assert_true(
			in_array( $topic_id, (array) get_option( 'abcc_topics_auto_paused', array() ), true ),
			'Auto-paused topic must be queued for the admin notice.'
		);
	}
);

abcc_test(
	'one failing topic does not block other due topics',
	function () {
		abcc_test_reset_topic_store();
		$now = 1750000000;

		// The failing topic errors at queue time via a nonexistent-topic race:
		// delete it from the store after the due query would have found it is
		// not simulatable here, so use ordering: force_insert_error only for
		// the first queue call.
		$first_id  = abcc_test_make_due_topic( $now - 20, 'First (fails)' );
		$second_id = abcc_test_make_due_topic( $now - 10, 'Second (succeeds)' );

		$GLOBALS['abcc_test_force_insert_error_once'] = true;
		$queued = abcc_run_topic_schedules( $now );
		unset( $GLOBALS['abcc_test_force_insert_error_once'] );

		abcc_assert_same( 1, $queued, 'The healthy topic must still queue.' );
		abcc_assert_same( 1, abcc_get_topic( $first_id )['consecutive_failures'] );
		abcc_assert_true( abcc_get_topic( $second_id )['last_job_id'] > 0 );
	}
);

abcc_test(
	'manual run-now queues a job without touching next_run',
	function () {
		$now      = 1750000000;
		$topic_id = abcc_test_make_due_topic( $now + 5000, 'Manual' );

		$job_id = abcc_queue_topic_generation( $topic_id );

		abcc_assert_true( ! is_wp_error( $job_id ) && $job_id > 0 );

		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( $now + 5000, $topic['next_run'], 'Manual runs are out-of-band: next_run untouched.' );
		abcc_assert_same( 0, $topic['last_run'] );

		// Payload carries the topic linkage and prompt.
		$payload = get_post_meta( $job_id, '_abcc_job_payload', true );
		abcc_assert_same( $topic_id, $payload['topic_id'] );
		abcc_assert_same( 'Write about it.', $payload['prompt'] );
		abcc_assert_same( 'topic', $payload['source'] );
	}
);

abcc_test(
	'run-now AJAX handler clears the consecutive-failure counter on success',
	function () {
		if ( ! function_exists( 'abcc_handle_topic_run_now' ) ) {
			require dirname( __DIR__ ) . '/includes/ajax-handlers.php';
		}

		// Earlier permission tests may have stripped the simulated user.
		$GLOBALS['abcc_test_current_user_caps']   = array();
		$GLOBALS['abcc_test_current_user_roles']  = array( 'administrator' );
		$GLOBALS['abcc_test_current_user_exists'] = true;

		$topic_id = abcc_test_make_due_topic( 1750005000, 'Recovering' );
		update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 4 );

		$_POST = array( 'topic_id' => $topic_id );
		abcc_handle_topic_run_now();
		$_POST = array();

		abcc_assert_true( ! empty( $GLOBALS['abcc_test_last_json']['success'] ), 'Run-now must succeed.' );
		abcc_assert_same(
			0,
			abcc_get_topic( $topic_id )['consecutive_failures'],
			'A successful manual run must reset the auto-pause counter.'
		);
	}
);
