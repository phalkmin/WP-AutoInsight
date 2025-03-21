<?php
/**
 * Uninstall file for WP-AutoInsight
 *
 * This file runs when the plugin is uninstalled via the WordPress admin.
 * It removes all options and data created by the plugin.
 *
 * @package WP-AutoInsight
 */

// If uninstall is not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// List of all options created by the plugin
$options = array(
	'openai_api_key',
	'gemini_api_key',
	'claude_api_key',
	'stability_api_key',
	'openai_custom_endpoint',
	'openai_generate_images',
	'openai_auto_create',
	'prompt_select',
	'openai_char_limit',
	'openai_email_notifications',
	'preferred_image_service',
	'openai_keywords',
	'openai_selected_categories',
	'openai_tone',
	'custom_tone',
	'openai_generate_seo',
);

// Delete all options
foreach ( $options as $option ) {
	delete_option( $option );
}

// Clear any scheduled events
wp_clear_scheduled_hook( 'abcc_openai_generate_post_hook' );

// Delete any transients
delete_transient( 'abcc_available_models' );
