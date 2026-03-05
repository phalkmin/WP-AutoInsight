<?php
/**
 * Plugin Name:       WP-AutoInsight
 * Plugin URI:        https://phalkmin.me/
 * Description:       Create blog posts automatically using the OpenAI and Gemini APIs!
 * Version:           3.3.0
 * Author:            Paulo H. Alkmin
 * Author URI:        https://phalkmin.me/
 * Text Domain:       automated-blog-content-creator
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Requires PHP:      8.1
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin version.
define( 'ABCC_VERSION', '3.3.0' );

// Include required files.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/class-abcc-plugin.php';
require_once __DIR__ . '/includes/class-abcc-openai-client.php';
require_once __DIR__ . '/includes/token-handling.php';
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
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/gpt.php';

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

	error_log( sprintf( '%s API Request Error: %s', $api, $error_message ) );

	add_settings_error(
		'openai-settings',
		'api-request-error',
		// Translators: %1$s is the API name, %2$s is the error message.
		sprintf( esc_html__( 'Error in %1$s API request: %2$s', 'automated-blog-content-creator' ), $api, $error_message ),
		'error'
	);
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
