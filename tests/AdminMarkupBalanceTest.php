<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
/**
 * Static markup checks for the admin tab partials.
 *
 * Every tab partial is included inside admin.php's <div class="tab-content">,
 * so each file must close exactly the <div>s it opens. One stray close in
 * tab-dashboard.php (v4.3) escaped .tab-content/.wrap/#wpbody-content and
 * left the WP admin footer floating over the dashboard. None of the partials
 * emit <div> conditionally, so a static count is an exact check.
 *
 * @package WP-AutoInsight
 */

abcc_test(
	'admin tab partials open and close the same number of divs',
	function () {
		$partials = glob( dirname( __DIR__ ) . '/includes/admin/tab-*.php' );
		abcc_assert_true( ! empty( $partials ), 'No admin tab partials found — glob path broken?' );

		$unbalanced = array();

		foreach ( $partials as $partial ) {
			$markup = file_get_contents( $partial ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$opens  = preg_match_all( '/<div\b/i', $markup );
			$closes = preg_match_all( '#</div>#i', $markup );

			if ( $opens !== $closes ) {
				$unbalanced[] = sprintf( '%s (%d open / %d close)', basename( $partial ), $opens, $closes );
			}
		}

		abcc_assert_true(
			empty( $unbalanced ),
			'Unbalanced <div>s break the admin page structure (footer overlap): ' . implode( ', ', $unbalanced )
		);
	}
);
