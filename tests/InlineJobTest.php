<?php
/**
 * Rewrite and Infographic write a row to the generation log (v4.6 §7).
 */

/**
 * Jobs of one source, newest first.
 *
 * @param string $source Source slug.
 * @return array Job IDs.
 */
function abcc_test_inline_jobs( $source ) {
	$ids = array();
	foreach ( get_posts( array( 'post_type' => ABCC_Job::POST_TYPE ) ) as $job ) {
		if ( get_post_meta( $job->ID, '_abcc_job_source', true ) === $source ) {
			$ids[] = (int) $job->ID;
		}
	}
	rsort( $ids );
	return $ids;
}

abcc_test(
	'a rewrite leaves a succeeded log row with its usage, and nothing is queued for a worker',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Inline Job Post',
				'post_content' => '<p>Original body text that will be rewritten.</p>',
			)
		);

		$before = count( abcc_test_inline_jobs( 'rewrite' ) );

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Rewritten body text.</p>' ) );
		$_POST = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();
		$_POST = array();

		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );

		$jobs = abcc_test_inline_jobs( 'rewrite' );
		abcc_assert_same( $before + 1, count( $jobs ) );
		$job_id = $jobs[0];

		abcc_assert_same( ABCC_Job::STATUS_SUCCESS, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_same( $post_id, (int) get_post_meta( $job_id, '_abcc_job_result_post_id', true ) );
		abcc_assert_same( 'openai::gpt-5.4-mini', get_post_meta( $job_id, '_abcc_job_model', true ) );
		abcc_assert_same( array( 'Inline Job Post' ), get_post_meta( $job_id, '_abcc_job_keywords', true ) );
		abcc_assert_same( 'inline', get_post_meta( $job_id, '_abcc_job_payload', true )['type'] );

		$usage = abcc_get_job_usage( $job_id );
		abcc_assert_same( 1, count( $usage ) );
		abcc_assert_same( 'rewrite', $usage[0]['operation'] );

		$notes = implode( ' | ', (array) get_post_meta( $job_id, '_abcc_job_notes', true ) );
		abcc_assert_true( false !== strpos( $notes, 'Nothing is saved until the post is updated' ) );
		abcc_assert_true( false !== strpos( $notes, 'Usage: 1 call' ) );

		abcc_assert_same( array(), $GLOBALS['abcc_test_scheduled_events'], 'An inline row is never handed to cron.' );
		abcc_assert_same( 'Rewrite', abcc_get_job_source_label( 'rewrite' ) );
	}
);

abcc_test(
	'a failed rewrite leaves a failed row with the reason; a refused one leaves no row',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Inline Failure Post',
				'post_content' => '<p>Body.</p>',
			)
		);
		$before  = count( abcc_test_inline_jobs( 'rewrite' ) );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{"error":{"message":"Rate limit"}}',
		);
		$_POST                        = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();

		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		$jobs = abcc_test_inline_jobs( 'rewrite' );
		abcc_assert_same( $before + 1, count( $jobs ) );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $jobs[0], '_abcc_job_status', true ) );
		abcc_assert_same( 'abcc_provider_rate_limited', get_post_meta( $jobs[0], '_abcc_job_error_code', true ) );
		abcc_assert_true( '' !== (string) get_post_meta( $jobs[0], '_abcc_job_error', true ) );
		$notes = (array) get_post_meta( $jobs[0], '_abcc_job_notes', true );
		abcc_assert_same( 1, count( array_keys( $notes, abcc_format_job_usage_note( $jobs[0] ), true ) ), 'The shared terminal-job path must not duplicate the inline usage note.' );

		// Too long to rewrite: refused before any provider call, so no row.
		$long_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Too Long',
				'post_content' => str_repeat( 'word ', 20000 ),
			)
		);
		$_POST   = array(
			'post_id' => $long_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();
		$_POST = array();

		abcc_assert_same( $before + 1, count( abcc_test_inline_jobs( 'rewrite' ) ) );
	}
);

abcc_test(
	'inline job helpers ignore a missing row and record duration and failure code',
	function () {
		abcc_finish_inline_job( 0, true );
		abcc_finish_inline_job( 987654321, false, array( 'message' => 'x' ) );

		$job_id = abcc_start_inline_job(
			'infographic',
			array(
				'model'   => 'openai::gpt-5.4-mini',
				'post_id' => 55,
				'label'   => 'A Post',
			)
		);
		abcc_assert_true( $job_id > 0 );
		abcc_assert_same( ABCC_Job::STATUS_RUNNING, get_post_meta( $job_id, '_abcc_job_status', true ) );

		// A worker that somehow gets the ID does nothing: the row is not queued.
		abcc_process_generation_job( $job_id );
		abcc_assert_same( ABCC_Job::STATUS_RUNNING, get_post_meta( $job_id, '_abcc_job_status', true ) );

		abcc_finish_inline_job(
			$job_id,
			false,
			array(
				'message' => 'No image generation service available',
				'code'    => '',
			),
			microtime( true ) - 1.5
		);
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_true( (float) get_post_meta( $job_id, '_abcc_job_duration', true ) >= 1.5 );
		abcc_assert_same( 'Infographic', abcc_get_job_source_label( 'infographic' ) );
	}
);
