<?php
/**
 * Plugin Name:       WP-AutoInsight
 * Plugin URI:        https://phalkmin.me/
 * Description:       Create blog posts automatically using the OpenAI and Gemini APIs!
 * Version:           4.0.1
 * Author:            Paulo H. Alkmin
 * Author URI:        https://phalkmin.me/
 * Text Domain:       automated-blog-content-creator
 * Domain Path:       /languages
 * Requires at least: 6.8
 * Requires PHP:      7.4
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin version.
define( 'ABCC_VERSION', '4.0.1' );

// Format requirements appended to every AI content generation prompt.
// Defined here so they are enforced regardless of which template is active.
define(
	'ABCC_CONTENT_FORMAT_REQUIREMENTS',
	"\n\nFormat requirements:\n- Use <h2>Heading</h2> for main sections\n- Use <h3>Heading</h3> for subsections\n- Put each paragraph in its own <p> tag\n- Do not include the title in the content\n- Put each section on a new line\n- Do not include empty lines or paragraphs\n- Ensure clean HTML without extra spaces or newlines\n- Always close every HTML tag before ending your response"
);

// Include required files.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/class-abcc-plugin.php';
require_once __DIR__ . '/includes/class-abcc-job.php';
require_once __DIR__ . '/includes/class-abcc-openai-client.php';
require_once __DIR__ . '/includes/token-handling.php';
require_once __DIR__ . '/includes/providers.php';
require_once __DIR__ . '/includes/api-keys.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/images.php';
require_once __DIR__ . '/includes/content-generation.php';
require_once __DIR__ . '/includes/scheduling.php';
require_once __DIR__ . '/includes/ajax-handlers.php';
require_once __DIR__ . '/includes/admin-buttons.php';
require_once __DIR__ . '/includes/audio.php';
require_once __DIR__ . '/includes/infographic.php';
require_once __DIR__ . '/includes/onboarding.php';
require_once __DIR__ . '/includes/meta-boxes.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/gpt.php';

/**
 * Log a debug message if debug logging is enabled.
 *
 * Checks the abcc_debug_logging setting before writing to the PHP error log.
 * Always logs when WP_DEBUG is true regardless of the setting.
 *
 * @since 4.0.0
 *
 * @param string $message The message to log.
 * @return void
 */
function abcc_debug_log( $message ) {
	if ( abcc_get_setting( 'abcc_debug_logging', false ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
		error_log( '[WP-AutoInsight] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Handle API request errors.
 *
 * @since 1.0.0
 *
 * @param mixed  $response The API response.
 * @param string $api      The API name.
 * @return void
 */
function handle_api_request_error( $response, $api ) {
	if ( ! ( $response instanceof WP_Error ) && ! empty( $response ) ) {
		$response = new WP_Error( 'api_error', $response );
	}

	// Error logging.
	$error_message = 'Unknown error occurred.';
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
	}

	abcc_debug_log( sprintf( '%s API Request Error: %s', $api, $error_message ) );

	// add_settings_error() is only loaded in wp-admin. Background jobs (cron/AJAX)
	// still need to log provider failures without fatalling the whole request.
	if ( function_exists( 'add_settings_error' ) ) {
		add_settings_error(
			'openai-settings',
			'api-request-error',
			// Translators: %1$s is the API name, %2$s is the error message.
			sprintf( esc_html__( 'Error in %1$s API request: %2$s', 'automated-blog-content-creator' ), $api, $error_message ),
			'error'
		);
	}
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 *
 * @return ABCC_Plugin Plugin instance.
 */
function abcc_init() {
	return ABCC_Plugin::instance();
}

// Start the plugin.
abcc_init();
