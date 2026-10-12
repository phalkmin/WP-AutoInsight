<?php
/**
 * Topic auto-pause tracks terminal job failures, not queue acceptance (C-11).
 */

function abcc_test_topic_job( $topic_id ) {
	$job_id = wp_insert_post(
		array(
			'post_type'   => ABCC_Job::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Generation Job: Topic',
		)
	);
	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_RUNNING );
	update_post_meta( $job_id, '_abcc_job_source', 'topic' );
	update_post_meta(
		$job_id,
		'_abcc_job_payload',
		array(
			'topic_id' => $topic_id,
			'source'   => 'topic',
			'keywords' => array( 'x' ),
		)
	);
	return $job_id;
}

abcc_test(
	'five failed topic jobs pause the topic; a success resets the counter',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'T',
				'prompt'    => 'P',
				'frequency' => 'daily',
			)
		);

		for ( $i = 0; $i < 4; $i++ ) {
			abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'boom', false, 'abcc_provider_server_error' );
		}
		abcc_assert_same( 4, abcc_get_topic( $topic_id )['consecutive_failures'] );
		abcc_assert_true( abcc_get_topic( $topic_id )['active'], 'Four failures: still active.' );

		abcc_mark_job_succeeded_for_topic( $topic_id );
		abcc_assert_same( 0, abcc_get_topic( $topic_id )['consecutive_failures'], 'A success resets the counter.' );

		for ( $i = 0; $i < 5; $i++ ) {
			abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'boom', false, 'abcc_provider_server_error' );
		}
		abcc_assert_false( abcc_get_topic( $topic_id )['active'], 'Five failures pause the topic.' );
	}
);

abcc_test(
	'AI-disabled and no-key failures do not count toward auto-pause',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'T',
				'prompt'    => 'P',
				'frequency' => 'daily',
			)
		);
		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'off', false, 'abcc_ai_disabled' );
		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'no key', false, 'abcc_no_api_key' );
		abcc_assert_same( 0, abcc_get_topic( $topic_id )['consecutive_failures'] );
	}
);

abcc_test(
	'the sweep no longer resets the failure counter at queue time',
	function () {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/topics.php' );
		abcc_assert_false( 1 === preg_match( "/update_post_meta\(\s*\\\$topic_id,\s*'_abcc_topic_consecutive_failures',\s*0\s*\);\s*\+\+\\\$queued;/", $source ), 'Queue acceptance is not success.' );
	}
);

abcc_test(
	'a job that never started does not count toward auto-pause',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'T',
				'prompt'    => 'P',
				'frequency' => 'daily',
			)
		);
		for ( $i = 0; $i < 5; $i++ ) {
			abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'never ran', false, 'abcc_job_never_started' );
		}
		abcc_assert_same( 0, abcc_get_topic( $topic_id )['consecutive_failures'] );
		abcc_assert_true( abcc_get_topic( $topic_id )['active'], 'Runner outages do not pause the topic.' );
	}
);

abcc_test(
	'failure e-mail goes out once per topic and error code per day, naming the topic',
	function () {
		$GLOBALS['abcc_test_options']['openai_email_notifications'] = true;
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Weekly Roundup',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);

		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'no key', false, 'abcc_no_api_key' );
		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'no key', false, 'abcc_no_api_key' );
		abcc_assert_same( 1, count( $GLOBALS['abcc_test_mails'] ), 'Same topic and code within a day: one mail.' );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_mails'][0]['subject'], 'Weekly Roundup' ), $GLOBALS['abcc_test_mails'][0]['subject'] );

		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'boom', false, 'abcc_provider_server_error' );
		abcc_assert_same( 2, count( $GLOBALS['abcc_test_mails'] ), 'A different code is news.' );
	}
);

abcc_test(
	'a failure for a deleted topic is logged but writes no orphan meta',
	function () {
		$ghost = 987654;
		abcc_record_topic_failure( $ghost, 'provider down' );
		abcc_assert_same( '', (string) get_post_meta( $ghost, '_abcc_topic_consecutive_failures', true ) );
	}
);

abcc_test(
	'a failed e-mail send does not arm the daily dedupe',
	function () {
		$GLOBALS['abcc_test_options']['openai_email_notifications'] = true;
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Mail Topic',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);

		$GLOBALS['abcc_test_mail_fails'] = true;
		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'boom', false, 'abcc_provider_server_error' );
		abcc_assert_same( 0, count( $GLOBALS['abcc_test_mails'] ), 'The failed send delivered nothing.' );

		$GLOBALS['abcc_test_mail_fails'] = false;
		abcc_mark_job_failed( abcc_test_topic_job( $topic_id ), 'boom', false, 'abcc_provider_server_error' );
		abcc_assert_same( 1, count( $GLOBALS['abcc_test_mails'] ), 'The next failure still notifies.' );
	}
);
