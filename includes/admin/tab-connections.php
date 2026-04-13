<?php
/**
 * Tab: Connections dispatcher
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$connections_subtabs = array(
	'api-keys'   => __( 'API Keys & Connections', 'automated-blog-content-creator' ),
	'scheduling' => __( 'Scheduling', 'automated-blog-content-creator' ),
);
$current_subtab = abcc_get_current_subtab( array_keys( $connections_subtabs ), 'api-keys' );

abcc_render_subtab_nav( 'connections', $connections_subtabs, $current_subtab );

if ( 'api-keys' === $current_subtab ) {
	include __DIR__ . '/tab-connections-api-keys.php';
} elseif ( 'scheduling' === $current_subtab ) {
	include __DIR__ . '/tab-connections-scheduling.php';
}
