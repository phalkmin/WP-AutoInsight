<?php
/**
 * Regression tests for atomic generation-job claiming.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed a job post with the given status in the test meta store.
 *
 * @param int    $job_id Job ID.
 * @param string $status Job status.
 * @return void
 */
function abcc_test_seed_job( $job_id, $status ) {
	$GLOBALS['abcc_test_post_types'][ $job_id ]                        = ABCC_Job::POST_TYPE;
	$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_status']     = $status;
	$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_payload']    = array(
		'source'   => 'manual',
		'keywords' => array( 'test' ),
	);
	$GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_created_by'] = 1;
}

abcc_test(
	'job already running is not re-processed',
	function () {
		abcc_test_seed_job( 901, ABCC_Job::STATUS_RUNNING );

		abcc_process_generation_job( 901 );

		abcc_assert_same(
			ABCC_Job::STATUS_RUNNING,
			$GLOBALS['abcc_test_post_meta'][901]['_abcc_job_status'],
			'A running job must be left untouched by a second worker.'
		);
		abcc_assert_false(
			isset( $GLOBALS['abcc_test_post_meta'][901]['_abcc_job_started_at'] ),
			'A running job must not be claimed again (no started_at write).'
		);
	}
);

abcc_test(
	'job already succeeded is not re-processed',
	function () {
		abcc_test_seed_job( 902, ABCC_Job::STATUS_SUCCESS );

		abcc_process_generation_job( 902 );

		abcc_assert_same(
			ABCC_Job::STATUS_SUCCESS,
			$GLOBALS['abcc_test_post_meta'][902]['_abcc_job_status'],
			'A succeeded job must not be re-processed.'
		);
		abcc_assert_false(
			isset( $GLOBALS['abcc_test_post_meta'][902]['_abcc_job_started_at'] ),
			'A succeeded job must not be claimed again.'
		);
	}
);

abcc_test(
	'conditional claim succeeds exactly once for a queued job',
	function () {
		abcc_test_seed_job( 903, ABCC_Job::STATUS_QUEUED );

		global $wpdb;
		$first  = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s
				 WHERE post_id = %d AND meta_key = '_abcc_job_status' AND meta_value = %s",
				ABCC_Job::STATUS_RUNNING,
				903,
				ABCC_Job::STATUS_QUEUED
			)
		);
		$second = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = %s
				 WHERE post_id = %d AND meta_key = '_abcc_job_status' AND meta_value = %s",
				ABCC_Job::STATUS_RUNNING,
				903,
				ABCC_Job::STATUS_QUEUED
			)
		);

		abcc_assert_same( 1, $first, 'First worker must claim the queued job.' );
		abcc_assert_same( 0, $second, 'Second worker must NOT claim an already-claimed job.' );
		abcc_assert_same(
			ABCC_Job::STATUS_RUNNING,
			$GLOBALS['abcc_test_post_meta'][903]['_abcc_job_status'],
			'Claimed job ends up running.'
		);
	}
);

abcc_test(
	'second inline call on a claimed job returns without processing',
	function () {
		abcc_test_seed_job( 904, ABCC_Job::STATUS_QUEUED );

		// Simulate worker 1 winning the claim between worker 2's guard read and write.
		$GLOBALS['abcc_test_post_meta'][904]['_abcc_job_status'] = ABCC_Job::STATUS_RUNNING;

		abcc_process_generation_job( 904 );

		abcc_assert_false(
			isset( $GLOBALS['abcc_test_post_meta'][904]['_abcc_job_started_at'] ),
			'Worker that loses the claim race must not start processing.'
		);
	}
);
