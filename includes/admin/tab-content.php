<?php
/**
 * Tab: Content
 *
 * Dispatcher for Content sub-tabs.
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content_subtabs = array(
	'keywords' => __( 'Keywords & Templates', 'automated-blog-content-creator' ),
	'bulk'     => __( 'Bulk Generate', 'automated-blog-content-creator' ),
	'log'      => __( 'Generation Log', 'automated-blog-content-creator' ),
);
$current_subtab = abcc_get_current_subtab( array_keys( $content_subtabs ), 'keywords' );

abcc_render_subtab_nav( 'content', $content_subtabs, $current_subtab );

if ( 'keywords' === $current_subtab ) {
	include __DIR__ . '/tab-content-keywords.php';
} elseif ( 'bulk' === $current_subtab ) {
	include __DIR__ . '/tab-content-bulk.php';
} elseif ( 'log' === $current_subtab ) {
	include __DIR__ . '/tab-content-log.php';
}
