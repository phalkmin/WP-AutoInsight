<?php
/**
 * Queued jobs that never start are failed with a WP-Cron hint (C-05).
 */

function abcc_test_make_queued_job( $minutes_ago, $run_id = '' ) {
	$job_id = wp_insert_post(
		array(
			'post_type'   => ABCC_Job::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Generation Job: Manual',
		)
	);
	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_QUEUED );
	update_post_meta( $job_id, '_abcc_job_created_at', gmdate( 'Y-m-d H:i:s', time() - $minutes_ago * 60 ) );
	// Recovery fails a queued job only on a second sighting; mark the first as long past.
	update_post_meta( $job_id, '_abcc_job_stale_seen', time() - 10 * MINUTE_IN_SECONDS );
	if ( '' !== $run_id ) {
		update_post_meta( $job_id, '_abcc_job_run_id', $run_id );
	}
	return $job_id;
}

abcc_test(
	'a queued job older than 30 minutes is failed with a cron hint; a 20-minute one is left alone',
	function () {
		$old   = abcc_test_make_queued_job( 31 );
		$fresh = abcc_test_make_queued_job( 20 );

		$recovered = abcc_recover_stale_running_jobs();

		abcc_assert_same( 1, $recovered );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $old, '_abcc_job_status', true ) );
		abcc_assert_true( false !== stripos( get_post_meta( $old, '_abcc_job_error', true ), 'WP-Cron' ) );
		abcc_assert_same( 'abcc_job_never_started', get_post_meta( $old, '_abcc_job_error_code', true ) );
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $fresh, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'batch jobs get a three-hour window',
	function () {
		$batch_young = abcc_test_make_queued_job( 90, 'run1' );
		$batch_old   = abcc_test_make_queued_job( 181, 'run1' );

		abcc_recover_stale_running_jobs();

		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $batch_young, '_abcc_job_status', true ) );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $batch_old, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'on external-cron sites every queued job gets the three-hour window',
	function () {
		$single = abcc_test_make_queued_job( 45 );
		abcc_recover_stale_running_jobs( 900, 1800, 10800, true );
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $single, '_abcc_job_status', true ), 'An hourly external cron may still pick it up.' );

		$stale = abcc_test_make_queued_job( 181 );
		abcc_recover_stale_running_jobs( 900, 1800, 10800, true );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $stale, '_abcc_job_status', true ) );

		abcc_recover_stale_running_jobs( 900, 1800, 10800, false );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $single, '_abcc_job_status', true ), 'With WP-Cron on, 45 minutes is stale.' );
	}
);

abcc_test(
	'bulk SEO jobs share one run_id and get the batch window',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'SEO target',
			)
		);
		$result  = abcc_queue_seo_regen_batch( array( $post_id, $post_id ) );
		abcc_assert_same( 2, $result['queued'] );

		$jobs = array();
		foreach ( get_posts(
			array(
				'post_type'      => ABCC_Job::POST_TYPE,
				'posts_per_page' => -1,
			)
		) as $job ) {
			if ( $job->ID > $post_id && 'seo_regen' === get_post_meta( $job->ID, '_abcc_job_source', true ) ) {
				$jobs[] = $job->ID;
			}
		}
		abcc_assert_same( 2, count( $jobs ), 'Both jobs were queued.' );

		$run_ids = array_unique(
			array_map(
				function ( $id ) {
					return get_post_meta( $id, '_abcc_job_run_id', true );
				},
				$jobs
			)
		);
		abcc_assert_same( 1, count( $run_ids ), 'One run_id per batch.' );
		abcc_assert_true( '' !== (string) reset( $run_ids ), 'The run_id is set.' );

		foreach ( $jobs as $id ) {
			update_post_meta( $id, '_abcc_job_created_at', gmdate( 'Y-m-d H:i:s', time() - 45 * MINUTE_IN_SECONDS ) );
		}
		abcc_recover_stale_running_jobs();
		foreach ( $jobs as $id ) {
			abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $id, '_abcc_job_status', true ), 'A 45-minute-old bulk SEO job is still waiting.' );
		}
	}
);

abcc_test(
	'a bulk SEO job queued before 4.5.2 (no run_id) still gets the batch window',
	function () {
		$job_id = abcc_test_make_queued_job( 45 );
		update_post_meta( $job_id, '_abcc_job_source', 'seo_regen' );

		abcc_recover_stale_running_jobs();

		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $job_id, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'a stale queued job is re-spawned on the first sighting and failed only on a later one',
	function () {
		$job_id = abcc_test_make_queued_job( 45 );
		delete_post_meta( $job_id, '_abcc_job_stale_seen' );

		$spawns_before = (int) $GLOBALS['abcc_test_spawn_cron_calls'];
		abcc_recover_stale_running_jobs();
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $job_id, '_abcc_job_status', true ), 'First sighting only marks the job.' );
		abcc_assert_true( (int) get_post_meta( $job_id, '_abcc_job_stale_seen', true ) > 0 );
		abcc_assert_same( $spawns_before + 1, (int) $GLOBALS['abcc_test_spawn_cron_calls'], 'The first sighting re-spawns cron once.' );

		abcc_recover_stale_running_jobs();
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $job_id, '_abcc_job_status', true ), 'Seen seconds ago: still waiting for the spawned cron.' );

		update_post_meta( $job_id, '_abcc_job_stale_seen', time() - 3 * MINUTE_IN_SECONDS );
		abcc_recover_stale_running_jobs();
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'the job poll runs recovery at most once a minute',
	function () {
		$job_id                          = abcc_test_make_queued_job( 45 );
		$GLOBALS['abcc_test_transients'] = array();

		abcc_get_job_data( $job_id );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ), 'The first poll sweeps.' );

		$second = abcc_test_make_queued_job( 45 );
		abcc_get_job_data( $second );
		abcc_assert_same( ABCC_Job::STATUS_QUEUED, get_post_meta( $second, '_abcc_job_status', true ), 'Within the minute the poll skips the sweep.' );
	}
);
