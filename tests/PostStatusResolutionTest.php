<?php
/**
 * Regression tests for abcc_resolve_post_status() (v4.2 Unit C).
 *
 * Precedence: force_draft > topic override > global setting; the
 * abcc_resolve_post_status filter sees the final value.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'force_draft wins over publish setting and topic override',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_post_meta'][77]['_abcc_topic_post_status_override'] = 'publish';

		$status = abcc_resolve_post_status(
			array(
				'force_draft' => true,
				'topic_id'    => 77,
			)
		);

		abcc_assert_same( 'draft', $status );
	}
);

abcc_test(
	'topic override beats the global setting',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'draft';
		$GLOBALS['abcc_test_post_meta'][88]['_abcc_topic_post_status_override'] = 'publish';

		abcc_assert_same( 'publish', abcc_resolve_post_status( array( 'topic_id' => 88 ) ) );
	}
);

abcc_test(
	'topic without an override falls through to the global setting',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		unset( $GLOBALS['abcc_test_post_meta'][99] );

		abcc_assert_same( 'publish', abcc_resolve_post_status( array( 'topic_id' => 99 ) ) );
	}
);

abcc_test(
	'global setting draft and publish are respected; default is draft',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'draft';
		abcc_assert_same( 'draft', abcc_resolve_post_status() );

		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		abcc_assert_same( 'publish', abcc_resolve_post_status() );

		unset( $GLOBALS['abcc_test_options']['abcc_default_post_status'] );
		abcc_assert_same( 'draft', abcc_resolve_post_status(), 'Unset option must resolve to the schema default (draft).' );
	}
);

abcc_test(
	'invalid stored values are sanitized to draft',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'pending';
		abcc_assert_same( 'draft', abcc_resolve_post_status() );

		$GLOBALS['abcc_test_post_meta'][55]['_abcc_topic_post_status_override'] = 'private';
		abcc_assert_same( 'draft', abcc_resolve_post_status( array( 'topic_id' => 55 ) ) );
	}
);

abcc_test(
	'abcc_resolve_post_status filter can override the resolved value',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'draft';

		add_filter(
			'abcc_resolve_post_status',
			function ( $status, $context ) {
				return 'cli' === ( $context['source'] ?? '' ) ? 'publish' : $status;
			}
		);

		abcc_assert_same( 'publish', abcc_resolve_post_status( array( 'source' => 'cli' ) ) );
		abcc_assert_same( 'draft', abcc_resolve_post_status( array( 'source' => 'manual' ) ) );

		remove_all_filters( 'abcc_resolve_post_status' );
	}
);
