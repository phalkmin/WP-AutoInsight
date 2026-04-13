<?php
/**
 * Tab: Media dispatcher
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$media_subtabs  = array(
	'images'       => __( 'Images', 'automated-blog-content-creator' ),
	'audio'        => __( 'Audio', 'automated-blog-content-creator' ),
	'infographics' => __( 'Infographics', 'automated-blog-content-creator' ),
);
$current_subtab = abcc_get_current_subtab( array_keys( $media_subtabs ), 'images' );

abcc_render_subtab_nav( 'media', $media_subtabs, $current_subtab );

if ( 'images' === $current_subtab ) {
	include __DIR__ . '/tab-media-images.php';
} elseif ( 'audio' === $current_subtab ) {
	include __DIR__ . '/tab-media-audio.php';
} elseif ( 'infographics' === $current_subtab ) {
	include __DIR__ . '/tab-media-infographics.php';
}
