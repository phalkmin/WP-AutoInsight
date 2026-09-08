<?php
/**
 * Tests for abcc_run_seo_regen().
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Force Yoast detection: abcc_get_active_seo_plugin() checks defined('WPSEO_VERSION') first.
// Defining it here (before the test callback runs) makes it return 'yoast' with no
// is_plugin_active() fallback — safe because no other test depends on WPSEO_VERSION being absent.
if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '99' );
}

abcc_test(
	'seo regen updates title and writes seo meta for an existing post',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Old Title',
				'post_content' => 'Body about photosynthesis.',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			),
			true
		);
		update_post_meta( $post_id, '_abcc_generation_params', wp_json_encode( array( 'keywords' => array( 'photosynthesis' ) ) ) );

		// Canned SEO JSON response for abcc_generate_title_and_seo.
		abcc_test_queue_http_response( abcc_test_fake_seo_json( 'New SEO Title', 'A crisp meta description.', 'photosynthesis' ) );

		$result = abcc_run_seo_regen( $post_id, 'gpt-4.1-mini-2025-04-14' );

		abcc_assert_true( ! is_wp_error( $result ), 'Regen should succeed for a valid post.' );
		abcc_assert_same( 'New SEO Title', get_post( $post_id )->post_title );
		abcc_assert_same( 'A crisp meta description.', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
	}
);

abcc_test(
	'seo regen errors on a missing post',
	function () {
		$result = abcc_run_seo_regen( 99999, 'gpt-4.1-mini-2025-04-14' );
		abcc_assert_true( is_wp_error( $result ), 'Missing post must error.' );
		abcc_assert_same( 'abcc_seo_regen_no_post', $result->get_error_code() );
	}
);

abcc_test(
	'bulk seo batch queues one job per post and counts results',
	function () {
		$GLOBALS['abcc_test_options']    = array( 'openai_api_key' => 'sk-test', 'prompt_select' => 'gpt-4.1-mini-2025-04-14' );
		$GLOBALS['abcc_test_active_seo'] = 'yoast';

		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = wp_insert_post(
				array( 'post_title' => 'P' . $i, 'post_content' => 'x', 'post_status' => 'publish', 'post_type' => 'post' ),
				true
			);
		}

		// Default-cron dispatch (process_inline not forced) so we only count queued jobs,
		// not run them — keeps this test about fan-out, not generation.
		$result = abcc_queue_seo_regen_batch( $ids );

		abcc_assert_same( 3, $result['queued'] );
		abcc_assert_same( 0, $result['failed'] );

		// Three seo_regen jobs exist.
		$jobs = get_posts( array( 'post_type' => ABCC_Job::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1 ) );
		$seo_jobs = 0;
		foreach ( $jobs as $job ) {
			if ( 'seo_regen' === get_post_meta( $job->ID, '_abcc_job_source', true ) ) {
				$seo_jobs++;
			}
		}
		abcc_assert_same( 3, $seo_jobs );
	}
);

abcc_test(
	'seo_regen job updates the target post and records success',
	function () {
		$GLOBALS['abcc_test_options']    = array( 'openai_api_key' => 'sk-test', 'prompt_select' => 'gpt-4.1-mini-2025-04-14' );
		$GLOBALS['abcc_test_active_seo'] = 'yoast';

		$post_id = wp_insert_post(
			array( 'post_title' => 'Stale', 'post_content' => 'About comets.', 'post_status' => 'publish', 'post_type' => 'post' ),
			true
		);

		abcc_test_queue_http_response( abcc_test_fake_seo_json( 'Fresh Comet Title', 'Comets explained.', 'comets' ) );

		$payload = abcc_build_generation_payload(
			array(
				'type'     => 'seo_regen',
				'post_id'  => $post_id,
				'source'   => 'seo_regen',
				'keywords' => array( 'comets' ),
			)
		);
		$job_id = abcc_queue_generation_job( $payload, array( 'process_inline' => true ) );

		abcc_assert_true( ! is_wp_error( $job_id ), 'Job should queue.' );
		abcc_assert_same( ABCC_Job::STATUS_SUCCESS, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_same( 'Fresh Comet Title', get_post( $post_id )->post_title );
		abcc_assert_same( (string) $post_id, (string) get_post_meta( $job_id, '_abcc_job_result_post_id', true ) );
	}
);

abcc_test(
	'seo regen resolves the API key from the passed model, not the global model',
	function () {
		// Job model is Claude; the globally-selected model is OpenAI (e.g. admin
		// switched it after queueing). The key must follow the job model.
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-openai-global',
			'claude_api_key' => 'sk-claude-job',
			'prompt_select'  => 'gpt-5.4-mini',
		);

		$post_id = wp_insert_post(
			array( 'post_title' => 'Old', 'post_content' => 'About bees.', 'post_status' => 'publish', 'post_type' => 'post' ),
			true
		);

		// Claude-shaped SEO JSON response (wrapper reads content[0].text).
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => '{"title":"Bees 101","meta_description":"All about bees.","primary_keyword":"bees","secondary_keywords":[],"social_excerpt":"x"}' ) ),
					'stop_reason' => 'end_turn',
					'usage'       => array( 'input_tokens' => 5, 'output_tokens' => 9 ),
				)
			),
		);

		$result = abcc_run_seo_regen( $post_id, 'claude-sonnet-4-6' );

		abcc_assert_true( ! is_wp_error( $result ), 'Regen should succeed.' );
		$headers = $GLOBALS['abcc_http_last_request']['args']['headers'] ?? array();
		abcc_assert_same( 'sk-claude-job', $headers['x-api-key'] ?? '', 'Generation must use the Claude key matching the job model.' );
	}
);

abcc_test(
	'bulk batch skips posts the user cannot edit',
	function () {
		$GLOBALS['abcc_test_options']          = array( 'openai_api_key' => 'sk-test', 'prompt_select' => 'gpt-5.4-mini' );
		$GLOBALS['abcc_test_uneditable_posts'] = array();

		$editable  = wp_insert_post( array( 'post_title' => 'Mine', 'post_content' => 'x', 'post_status' => 'publish', 'post_type' => 'post' ), true );
		$forbidden = wp_insert_post( array( 'post_title' => 'Theirs', 'post_content' => 'x', 'post_status' => 'publish', 'post_type' => 'post' ), true );

		$GLOBALS['abcc_test_uneditable_posts'] = array( $forbidden );

		$result = abcc_queue_seo_regen_batch( array( $editable, $forbidden ) );

		abcc_assert_same( 1, $result['queued'], 'Only the editable post is queued.' );
		abcc_assert_same( 1, $result['failed'], 'The forbidden post counts as failed/skipped.' );

		$GLOBALS['abcc_test_uneditable_posts'] = array();
	}
);

abcc_test(
	'bulk SEO queues many jobs with a single cron spawn',
	function () {
		$GLOBALS['abcc_test_spawn_cron_calls'] = 0;

		$post_ids = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$post_ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Post ' . $i ) );
		}

		abcc_queue_seo_regen_batch( $post_ids );

		abcc_assert_true(
			$GLOBALS['abcc_test_spawn_cron_calls'] <= 1,
			'A 12-post batch must spawn cron at most once, got ' . $GLOBALS['abcc_test_spawn_cron_calls'] . '.'
		);
	}
);
