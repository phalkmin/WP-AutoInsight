<?php
/**
 * Job data is visible to its creator and to administrators only (C-17).
 */

abcc_test(
	'a job is visible to its creator and to admins, not to other prompt users',
	function () {
		$GLOBALS['abcc_test_current_user_id'] = 7;
		$job_id                               = abcc_queue_generation_job(
			array(
				'keywords' => array( 'x' ),
				'source'   => 'manual',
			),
			array(
				'process_inline' => false,
				'defer_spawn'    => true,
			)
		);

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		abcc_assert_true( abcc_user_can_view_job( $job_id, 7 ), 'Creator sees the job.' );
		abcc_assert_false( abcc_user_can_view_job( $job_id, 8 ), 'Another non-admin user does not.' );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => true );
		abcc_assert_true( abcc_user_can_view_job( $job_id, 8 ), 'An admin sees every job.' );
	}
);

abcc_test(
	'job status endpoint answers not-found for a job the user may not see',
	function () {
		$GLOBALS['abcc_test_current_user_id'] = 7;
		$job_id                               = abcc_queue_generation_job(
			array(
				'keywords' => array( 'x' ),
				'source'   => 'manual',
			),
			array(
				'process_inline' => false,
				'defer_spawn'    => true,
			)
		);

		$GLOBALS['abcc_test_current_user_id']   = 8;
		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$GLOBALS['abcc_test_last_json']         = null;
		$_POST                                  = array( 'job_id' => $job_id );

		abcc_handle_get_job_status();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'Generation job not found.', $GLOBALS['abcc_test_last_json']['data']['message'] );
		$_POST = array();
	}
);

abcc_test(
	'a job with created_by 0 is visible to admins only',
	function () {
		$GLOBALS['abcc_test_current_user_id'] = 7;
		$job_id                               = abcc_queue_generation_job(
			array(
				'keywords' => array( 'x' ),
				'source'   => 'manual',
			),
			array(
				'process_inline' => false,
				'defer_spawn'    => true,
			)
		);
		update_post_meta( $job_id, '_abcc_job_created_by', 0 );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		abcc_assert_false( abcc_user_can_view_job( $job_id, 5 ), 'A non-admin does not see an authorless job.' );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => true );
		abcc_assert_true( abcc_user_can_view_job( $job_id, 5 ), 'An admin does.' );
	}
);
