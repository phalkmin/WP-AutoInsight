<?php
/**
 * Publication decisions are pinned when a job is queued, not when the
 * worker inserts the post (4.5.0 pre-release fixes).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'queued topic job keeps its draft override after the topic is deleted',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status']            = 'publish';
		$GLOBALS['abcc_test_post_meta'][77]['_abcc_topic_post_status_override'] = 'draft';

		$payload = abcc_build_generation_payload(
			array(
				'keywords' => array( 'topic keyword' ),
				'topic_id' => 77,
				'source'   => 'topic',
			)
		);
		$job_id  = abcc_queue_generation_job( $payload );
		$stored  = get_post_meta( $job_id, '_abcc_job_payload', true );

		abcc_assert_same( 'draft', $stored['post_status'], 'Queue must snapshot the topic override.' );

		// Topic deleted while the job waits: the worker's resolution still sees the snapshot.
		unset( $GLOBALS['abcc_test_post_meta'][77] );
		$resolved = abcc_resolve_post_status(
			array(
				'post_status' => $stored['post_status'],
				'topic_id'    => 77,
				'source'      => 'topic',
			)
		);
		abcc_assert_same( 'draft', $resolved );
	}
);

abcc_test(
	'a requester without publish capability is queued as draft; cron context is untouched',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'publish_posts' => false );

		$payload = abcc_finalize_payload_post_status( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		abcc_assert_same( 'draft', $payload['post_status'], 'Contributor asking for publish gets a draft.' );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'publish_posts' => true );
		$payload                                = abcc_finalize_payload_post_status( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		abcc_assert_same( 'publish', $payload['post_status'] );

		// Pages map to publish_pages, not publish_posts.
		$GLOBALS['abcc_test_current_user_caps'] = array( 'publish_posts' => true, 'publish_pages' => false );
		$payload                                = abcc_finalize_payload_post_status( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		abcc_assert_same( 'draft', $payload['post_status'] );

		// No current user (cron/WP-CLI): the schedule's author decision stands.
		$GLOBALS['abcc_test_current_user_id']   = 0;
		$GLOBALS['abcc_test_current_user_caps'] = array( 'publish_posts' => false );
		$payload                                = abcc_finalize_payload_post_status( array( 'post_type' => 'post' ) );
		abcc_assert_same( 'publish', $payload['post_status'] );
	}
);

abcc_test(
	'regenerate handler queues a draft even when the global default is publish',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_post_meta'][42]['_abcc_generation_params'] = wp_json_encode(
			array(
				'keywords'   => array( 'kw' ),
				'model'      => 'gpt-4.1-mini-2025-04-14',
				'tone'       => 'default',
				'char_limit' => 200,
			)
		);
		$_POST = array( 'post_id' => 42, 'nonce' => 'x' );

		abcc_handle_regenerate_post();

		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'], 'Handler must succeed.' );
		$job_id = $GLOBALS['abcc_test_last_json']['data']['job_id'];
		$stored = get_post_meta( $job_id, '_abcc_job_payload', true );
		abcc_assert_same( 'draft', $stored['post_status'], '"Regenerate as New Draft" must never publish.' );
		abcc_assert_same( 'regenerate', $stored['source'] );
		$_POST = array();
	}
);

abcc_test(
	'create post handler refuses a post type the user cannot create',
	function () {
		$GLOBALS['abcc_test_options']['abcc_selected_post_types'] = array( 'post', 'page' );
		$GLOBALS['abcc_test_options']['abcc_keyword_groups']      = array( array( 'name' => 'G', 'keywords' => array( 'kw' ) ) );
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'edit_pages' => false );
		$_POST = array( 'post_type' => 'page', 'nonce' => 'x' );

		abcc_handle_create_post();

		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], 'permission' ), 'Must explain the capability failure.' );
		$_POST = array();
	}
);

abcc_test(
	'create post handler downgrades a requested publish for a role that cannot publish and says so',
	function () {
		$GLOBALS['abcc_test_options']['abcc_selected_post_types'] = array( 'post' );
		$GLOBALS['abcc_test_options']['abcc_keyword_groups']      = array( array( 'name' => 'G', 'keywords' => array( 'kw' ) ) );
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'publish_posts' => false );
		$_POST = array( 'post_type' => 'post', 'post_status' => 'publish', 'nonce' => 'x' );

		abcc_handle_create_post();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( $json['success'], 'Job still queues.' );
		abcc_assert_same( 'draft', $json['data']['post_status'] );
		abcc_assert_true( false !== stripos( $json['data']['message'], 'draft' ), 'Response must mention the downgrade.' );
		$stored = get_post_meta( $json['data']['job_id'], '_abcc_job_payload', true );
		abcc_assert_same( 'draft', $stored['post_status'] );

		// Same request from a role that can publish keeps publish.
		$GLOBALS['abcc_test_current_user_caps'] = array( 'publish_posts' => true );
		abcc_handle_create_post();
		abcc_assert_same( 'publish', $GLOBALS['abcc_test_last_json']['data']['post_status'] );
		$_POST = array();
	}
);
