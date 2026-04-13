<?php
/**
 * Tab: Settings dispatcher
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_subtabs = array(
	'general'     => __( 'General', 'automated-blog-content-creator' ),
	'permissions' => __( 'Permissions', 'automated-blog-content-creator' ),
	'advanced'    => __( 'Advanced', 'automated-blog-content-creator' ),
);
$current_subtab = abcc_get_current_subtab( array_keys( $settings_subtabs ), 'general' );

abcc_render_subtab_nav( 'settings', $settings_subtabs, $current_subtab );

if ( 'general' === $current_subtab ) {
	include __DIR__ . '/tab-settings-general.php';
} elseif ( 'permissions' === $current_subtab ) {
	include __DIR__ . '/tab-settings-permissions.php';
} elseif ( 'advanced' === $current_subtab ) {
	include __DIR__ . '/tab-settings-advanced.php';
}
