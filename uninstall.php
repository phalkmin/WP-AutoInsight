<?php
/**
 * Uninstall file for WP-AutoInsight
 *
 * This file runs when the plugin is uninstalled via the WordPress admin.
 * It removes all options and data created by the plugin.
 *
 * @package WP-AutoInsight
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// settings.php references ABCC_VERSION — define a placeholder so the schema
// loads safely without the full plugin bootstrap.
if ( ! defined( 'ABCC_VERSION' ) ) {
	define( 'ABCC_VERSION', '0.0.0' );
}

// Load the settings schema so we can delete every registered option durably.
$settings_file = plugin_dir_path( __FILE__ ) . 'includes/settings.php';
if ( file_exists( $settings_file ) ) {
	require_once $settings_file;
}

if ( function_exists( 'abcc_get_settings_schema' ) ) {
	$schema = abcc_get_settings_schema();
	foreach ( array_keys( $schema['settings'] ) as $option_key ) {
		delete_option( $option_key );
	}
}

// Legacy / non-schema options that may linger from older versions.
$legacy_options = array(
	'openai_custom_endpoint',
	'abcc_last_group_index',
	'abcc_provider_health',
	'abcc_topics_auto_paused',
	'abcc_topics_last_sweep',
	'abcc_draft_mode_notice',
	'abcc_version',
	'abcc_onboarding_completed',
);
foreach ( $legacy_options as $option_key ) {
	delete_option( $option_key );
}

// Delete all job + topic custom posts (and their meta).
// Slugs verified against ABCC_Job::POST_TYPE ('abcc_job') and
// ABCC_TOPIC_POST_TYPE ('abcc_topic') in includes/class-abcc-job-type.php
// and includes/topics.php respectively.
$cpt_slugs = array( 'abcc_job', 'abcc_topic' );
foreach ( $cpt_slugs as $cpt ) {
	$cpt_post_ids = get_posts(
		array(
			'post_type'        => $cpt,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	foreach ( $cpt_post_ids as $cpt_post_id ) {
		wp_delete_post( $cpt_post_id, true );
	}
}

// Clear all scheduled cron events.
// Hook names verified against add_action registrations in:
//   includes/scheduling.php    → abcc_openai_generate_post_hook
//   includes/class-abcc-job.php → abcc_process_generation_job
//   includes/class-abcc-plugin.php → abcc_daily_provider_health_check
//   includes/class-abcc-plugin.php → abcc_run_topic_schedules
$cron_hooks = array(
	'abcc_openai_generate_post_hook',
	'abcc_process_generation_job',
	'abcc_daily_provider_health_check',
	'abcc_run_topic_schedules',
);
foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// Delete transients.
delete_transient( 'abcc_available_models' );
delete_transient( 'abcc_onboarding_just_completed' );
delete_transient( 'abcc_last_validation_openai' );
delete_transient( 'abcc_last_validation_claude' );
delete_transient( 'abcc_last_validation_gemini' );
delete_transient( 'abcc_last_validation_perplexity' );
delete_transient( 'abcc_last_validation_stability' );

// Delete per-user dismissal meta across all users.
delete_metadata( 'user', 0, 'abcc_draft_mode_notice_dismissed', '', true );
