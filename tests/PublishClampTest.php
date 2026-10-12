<?php
/**
 * The queue-time publish clamp checks the job author when no user is logged in (cron).
 */

abcc_test(
	'on the cron path the author\'s publish capability decides between publish and draft',
	function () {
		$GLOBALS['abcc_test_current_user_id'] = 0;
		$payload                              = array(
			'post_status' => 'publish',
			'post_type'   => 'post',
		);

		$GLOBALS['abcc_test_user_caps_by_id'][7] = array( 'publish_posts' => false );
		abcc_assert_same( 'draft', abcc_finalize_payload_post_status( $payload, 7 )['post_status'], 'A contributor author gets a draft.' );

		$GLOBALS['abcc_test_user_caps_by_id'][7] = array( 'publish_posts' => true );
		abcc_assert_same( 'publish', abcc_finalize_payload_post_status( $payload, 7 )['post_status'], 'An author who can publish keeps publish.' );

		$GLOBALS['abcc_test_current_user_id'] = 1;
	}
);
