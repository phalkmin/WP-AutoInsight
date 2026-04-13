<?php
/**
 * Tests: meta box registration.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/meta-boxes.php';

// Seed: two post types.
$GLOBALS['abcc_test_options']['abcc_selected_post_types'] = array( 'post', 'page' );
$GLOBALS['abcc_test_meta_boxes']                          = array();
abcc_register_meta_boxes();

abcc_test(
	'registers exactly one meta box per post type',
	function () {
		$boxes      = $GLOBALS['abcc_test_meta_boxes'];
		$post_boxes = array_filter( $boxes, fn( $b ) => 'post' === $b['screen'] );
		$page_boxes = array_filter( $boxes, fn( $b ) => 'page' === $b['screen'] );
		abcc_assert_same( 1, count( $post_boxes ), 'Expected 1 meta box for post, got ' . count( $post_boxes ) );
		abcc_assert_same( 1, count( $page_boxes ), 'Expected 1 meta box for page, got ' . count( $page_boxes ) );
	}
);

abcc_test(
	'meta box uses correct ID and title',
	function () {
		$boxes = $GLOBALS['abcc_test_meta_boxes'];
		$ids   = array_column( $boxes, 'id' );
		abcc_assert_true(
			in_array( 'abcc-ai-tools-meta-box', $ids, true ),
			'Expected meta box ID abcc-ai-tools-meta-box'
		);
		abcc_assert_false(
			in_array( 'abcc-rewrite-meta-box', $ids, true ),
			'Old ID abcc-rewrite-meta-box must not be registered'
		);
		abcc_assert_false(
			in_array( 'abcc-infographic-meta-box', $ids, true ),
			'Old ID abcc-infographic-meta-box must not be registered'
		);
		$post_box = current( array_filter( $boxes, fn( $b ) => 'post' === $b['screen'] ) );
		abcc_assert_same( 'WP-AutoInsight Tools', $post_box['title'], 'Title must be WP-AutoInsight Tools' );
	}
);

abcc_test(
	'meta box priority is high',
	function () {
		$boxes    = $GLOBALS['abcc_test_meta_boxes'];
		$post_box = current( array_filter( $boxes, fn( $b ) => 'post' === $b['screen'] ) );
		abcc_assert_same( 'high', $post_box['priority'], 'Priority must be high' );
	}
);
