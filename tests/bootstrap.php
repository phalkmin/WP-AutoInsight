<?php
/**
 * Lightweight regression test bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'ABCC_VERSION' ) ) {
	define( 'ABCC_VERSION', '4.0.1' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ABCC_CONTENT_FORMAT_REQUIREMENTS' ) ) {
	define(
		'ABCC_CONTENT_FORMAT_REQUIREMENTS',
		"\n\nFormat requirements:\n- Use <h2>Heading</h2> for main sections\n- Use <h3>Heading</h3> for subsections\n- Put each paragraph in its own <p> tag\n- Do not include the title in the content\n- Put each section on a new line\n- Do not include empty lines or paragraphs\n- Ensure clean HTML without extra spaces or newlines\n- Always close every HTML tag before ending your response"
	);
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

$GLOBALS['abcc_test_options']    = array();
$GLOBALS['abcc_test_transients'] = array();
$GLOBALS['abcc_test_actions']    = array();
$GLOBALS['abcc_test_meta_boxes'] = array();
$GLOBALS['abcc_tests']           = array();
$GLOBALS['abcc_test_connectors'] = array();
$GLOBALS['abcc_test_current_user_caps']  = array();
$GLOBALS['abcc_test_current_user_roles'] = array( 'administrator' );
$GLOBALS['abcc_test_current_user_exists'] = true;
$GLOBALS['abcc_test_last_json'] = null;
$GLOBALS['abcc_test_schedule']   = array(
	'timestamp' => false,
	'schedule'  => false,
);
$GLOBALS['abcc_http_queue']        = array();
$GLOBALS['abcc_http_last_request'] = null;

if ( ! class_exists( 'ABCC_Test_User' ) ) {
	class ABCC_Test_User {
		public $roles = array();
		private $exists = true;

		public function __construct( $roles = array(), $exists = true ) {
			$this->roles  = $roles;
			$this->exists = $exists;
		}

		public function exists() {
			return $this->exists;
		}
	}
}

function add_meta_box( $id, $title, $callback, $screen, $context = 'advanced', $priority = 'default' ) {
	$GLOBALS['abcc_test_meta_boxes'][] = array(
		'id'       => $id,
		'title'    => $title,
		'callback' => $callback,
		'screen'   => $screen,
		'context'  => $context,
		'priority' => $priority,
	);
}

function __($text) { return $text; }
function esc_html__($text) { return $text; }
function esc_attr__($text) { return $text; }
function apply_filters($hook, $value) { return $value; }
function add_action($hook, $callback) { $GLOBALS['abcc_test_actions'][ $hook ][] = $callback; }
function current_user_can($cap) {
	if ( array_key_exists( $cap, $GLOBALS['abcc_test_current_user_caps'] ) ) {
		return (bool) $GLOBALS['abcc_test_current_user_caps'][ $cap ];
	}

	return true;
}
function check_ajax_referer() { return true; }
function wp_verify_nonce() { return true; }
function wp_send_json_error($data = array()) {
	$GLOBALS['abcc_test_last_json'] = array(
		'success' => false,
		'data'    => $data,
	);
	return $data;
}
function wp_send_json_success($data = array()) {
	$GLOBALS['abcc_test_last_json'] = array(
		'success' => true,
		'data'    => $data,
	);
	return $data;
}
function add_settings_error() { return true; }
function wp_json_encode($value) { return json_encode($value); }
function sanitize_text_field($value) { return is_string($value) ? trim($value) : $value; }
function sanitize_textarea_field($value) { return is_string($value) ? trim($value) : $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function get_current_user_id() { return 1; }
function is_wp_error($thing) { return $thing instanceof WP_Error; }
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, $args); }
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['abcc_test_options']) ? $GLOBALS['abcc_test_options'][ $key ] : $default; }
function update_option($key, $value) { $GLOBALS['abcc_test_options'][ $key ] = $value; return true; }
function delete_option($key) { unset($GLOBALS['abcc_test_options'][ $key ]); return true; }
function get_transient($key) { return $GLOBALS['abcc_test_transients'][ $key ] ?? false; }
function set_transient($key, $value) { $GLOBALS['abcc_test_transients'][ $key ] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['abcc_test_transients'][ $key ]); return true; }
function get_bloginfo($key) {
	if ( 'name' === $key ) {
		return 'WP-AutoInsight Test Site';
	}
	if ( 'description' === $key ) {
		return 'Testing';
	}
	return '';
}
function get_cat_name($id) { return 0 === (int) $id ? 'General' : 'Category ' . (int) $id; }
function wp_trim_words($text, $num_words = 55) {
	$words = preg_split('/\s+/', trim((string) $text));
	return implode(' ', array_slice($words, 0, $num_words));
}
function wp_kses_post($text) { return $text; }
function wp_get_current_user() {
	return new ABCC_Test_User(
		$GLOBALS['abcc_test_current_user_roles'],
		$GLOBALS['abcc_test_current_user_exists']
	);
}
function wp_is_connector_registered($connector_id) { return in_array($connector_id, $GLOBALS['abcc_test_connectors'], true); }
function wp_next_scheduled($hook) { return 'abcc_openai_generate_post_hook' === $hook ? $GLOBALS['abcc_test_schedule']['timestamp'] : false; }
function wp_get_schedule($hook) { return 'abcc_openai_generate_post_hook' === $hook ? $GLOBALS['abcc_test_schedule']['schedule'] : false; }
function date_i18n($format, $timestamp) { return gmdate('Y-m-d H:i', (int) $timestamp); }
function abcc_debug_log($message) { return null; }
function get_attached_file($attachment_id) { return '/tmp/audio-' . (int) $attachment_id . '.mp3'; }

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['abcc_http_last_request'] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( empty( $GLOBALS['abcc_http_queue'] ) ) {
			return new WP_Error( 'no_canned_response', 'Test queue empty — did you forget to push a canned response?' );
		}
		return array_shift( $GLOBALS['abcc_http_queue'] );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return 0;
		}
		return $response['response']['code'] ?? 0;
	}
}

if ( ! defined( 'OPENAI_API' ) ) {
	define( 'OPENAI_API', 'const-openai-key' );
}

require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/providers.php';
require_once dirname(__DIR__) . '/includes/api-keys.php';
require_once dirname(__DIR__) . '/includes/token-handling.php';
require_once dirname(__DIR__) . '/includes/scheduling.php';
require_once dirname(__DIR__) . '/includes/images.php';
require_once dirname(__DIR__) . '/includes/content-generation.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/gpt.php';
require_once dirname(__DIR__) . '/includes/onboarding.php';

function abcc_test($name, callable $callback) {
	$GLOBALS['abcc_tests'][] = array(
		'name'     => $name,
		'callback' => $callback,
	);
}

function abcc_assert_true($condition, $message = 'Expected condition to be true.') {
	if ( ! $condition ) {
		throw new Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}

function abcc_assert_false($condition, $message = 'Expected condition to be false.') {
	if ( $condition ) {
		throw new Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}

function abcc_assert_same($expected, $actual, $message = '') {
	if ( $expected !== $actual ) {
		throw new Exception($message ?: 'Failed asserting that values are identical.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}

function abcc_assert_equals($expected, $actual, $message = '') {
	if ( $expected != $actual ) {
		throw new Exception($message ?: 'Failed asserting that values are equal.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}

function abcc_assert_array_has_key($key, $array, $message = '') {
	if ( ! is_array($array) || ! array_key_exists($key, $array) ) {
		throw new Exception($message ?: 'Failed asserting that array has expected key.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
