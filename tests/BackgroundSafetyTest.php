<?php
/**
 * 4.5.1: background work honors the site AI toggle, cron-context jobs stay
 * async, publish clamping covers cron authors, audio checks the attachment.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'worker fails a queued job without calling a provider when site AI is off',
	function () {
		$GLOBALS['abcc_test_ai_supported'] = false;

		$job_id = 951;
		$GLOBALS['abcc_test_post_types'][ $job_id ]                        = ABCC_Job::POST_TYPE;
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_status']     = ABCC_Job::STATUS_QUEUED;
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_payload']    = array(
			'source'   => 'topic',
			'keywords' => array( 'test' ),
		);
		$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_created_by'] = 1;

		abcc_process_generation_job( $job_id );

		abcc_assert_same( ABCC_Job::STATUS_FAILED, $GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_status'] );
		abcc_assert_true( null === $GLOBALS['abcc_http_last_request'], 'No provider request may be sent.' );
	}
);

abcc_test(
	'topic sweep and legacy schedule queue nothing when site AI is off',
	function () {
		$GLOBALS['abcc_test_ai_supported'] = false;
		$now                               = 1750000000;

		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Due',
				'prompt'    => 'Write about it.',
				'frequency' => 'daily',
			)
		);
		update_post_meta( $topic_id, '_abcc_topic_next_run', $now - 10 );

		abcc_assert_same( 0, abcc_run_topic_schedules( $now ) );
		abcc_assert_same( $now - 10, (int) get_post_meta( $topic_id, '_abcc_topic_next_run', true ), 'next_run must stay put so the topic resumes.' );

		$GLOBALS['abcc_test_options']['openai_auto_create']  = 'daily';
		$GLOBALS['abcc_test_options']['abcc_keyword_groups'] = array( array( 'keywords' => array( 'kw' ) ) );
		abcc_assert_false( abcc_openai_generate_post_scheduled() );
		abcc_assert_same( array(), $GLOBALS['abcc_test_scheduled_events'] );
	}
);

abcc_test(
	'jobs queued from a cron request are scheduled, not run inline',
	function () {
		$GLOBALS['abcc_test_doing_cron'] = true;

		abcc_assert_true( abcc_should_defer_job_to_cron() );

		$job_id = abcc_queue_generation_job( abcc_build_generation_payload( array( 'keywords' => array( 'kw' ) ) ) );

		abcc_assert_same( 1, count( $GLOBALS['abcc_test_scheduled_events'] ) );
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $job_id, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'a failed cron schedule runs the job immediately and says so',
	function () {
		$GLOBALS['abcc_test_schedule_fails'] = true;
		$GLOBALS['abcc_test_ai_supported']   = false; // Keep the inline run off the network.

		$job_id = abcc_queue_generation_job( abcc_build_generation_payload( array( 'keywords' => array( 'kw' ) ) ) );

		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ), 'Inline run must have claimed the job.' );
		abcc_assert_same( 1, count( (array) get_post_meta( $job_id, '_abcc_job_notes', true ) ) );
		abcc_assert_same( 0, $GLOBALS['abcc_test_spawn_cron_calls'] );
	}
);

abcc_test(
	'with no current user, the job author must be able to publish',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_current_user_id']                     = 0;
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'publish_posts' => false );

		$payload = abcc_finalize_payload_post_status( array( 'post_type' => 'post' ), 7 );
		abcc_assert_same( 'draft', $payload['post_status'], 'An author who cannot publish gets a draft under cron.' );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'publish_posts' => true );
		$payload                                = abcc_finalize_payload_post_status( array( 'post_type' => 'post' ), 7 );
		abcc_assert_same( 'publish', $payload['post_status'] );
	}
);

abcc_test(
	'audio handlers reject an attachment the user cannot edit',
	function () {
		$GLOBALS['abcc_test_uneditable_posts'] = array( 321 );
		$_POST                                 = array(
			'nonce'         => 'x',
			'attachment_id' => '321',
			'transcript'    => 'Hello',
		);

		foreach ( array( 'abcc_handle_audio_transcription', 'abcc_handle_create_post_from_transcript', 'abcc_handle_audio_generate_post' ) as $handler ) {
			$GLOBALS['abcc_test_last_json'] = null;
			$handler();
			abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'], $handler . ' must refuse.' );
			abcc_assert_true( false !== stripos( $GLOBALS['abcc_test_last_json']['data']['message'], 'permission' ), $handler . ' must refuse on permission.' );
		}

		$_POST = array();
	}
);
