<?php
/**
 * Recent Activity shows real titles and working links (P1-10, P1-06).
 */

function abcc_test_render_dashboard() {
	$page_slug = 'automated-blog-content-creator';
	ob_start();
	include dirname( __DIR__ ) . '/includes/admin/tab-dashboard.php';
	return ob_get_clean();
}

abcc_test(
	'activity shows the result post title and no View link for trashed or deleted posts',
	function () {
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk';

		$ok_post = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'A Real Title',
				'post_status' => 'draft',
			)
		);
		$ok_job  = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Generation Job: Manual',
			)
		);
		update_post_meta( $ok_job, '_abcc_job_status', ABCC_Job::STATUS_SUCCESS );
		update_post_meta( $ok_job, '_abcc_job_result_post_id', $ok_post );

		$trashed = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Gone',
				'post_status' => 'trash',
			)
		);
		$bad_job = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Generation Job: Manual',
			)
		);
		update_post_meta( $bad_job, '_abcc_job_status', ABCC_Job::STATUS_SUCCESS );
		update_post_meta( $bad_job, '_abcc_job_result_post_id', $trashed );
		update_post_meta( $bad_job, '_abcc_job_keywords', array( 'anime' ) );

		$html = abcc_test_render_dashboard();

		abcc_assert_true( false !== strpos( $html, 'A Real Title' ), 'Result post title replaces the generic job title.' );
		abcc_assert_same( 1, substr_count( $html, 'class="abcc-activity-link"' ), 'Only the live post gets a View link.' );
		abcc_assert_true( false !== strpos( $html, 'anime' ), 'A gone post falls back to the keyword.' );
	}
);

abcc_test(
	'activity age uses GMT so a just-created job is minutes ago, not hours',
	function () {
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk';
		$job = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Generation Job: Manual',
			)
		);
		$GLOBALS['abcc_test_posts'][ $job ]->post_date_gmt_ts = time() - 60;
		update_post_meta( $job, '_abcc_job_status', ABCC_Job::STATUS_QUEUED );

		$html = abcc_test_render_dashboard();

		abcc_assert_true( false !== strpos( $html, '1 mins ago' ) || false !== strpos( $html, '1 min ago' ), $html );
		abcc_assert_false( false !== strpos( $html, '3 hours ago' ), 'Offset leaked into the age.' );
	}
);
