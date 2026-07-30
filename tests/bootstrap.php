<?php
/**
 * Lightweight regression test bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'ABCC_VERSION' ) ) {
	define( 'ABCC_VERSION', '4.2.0' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
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
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_code() {
			return $this->code;
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
$GLOBALS['abcc_test_post_meta']    = array();
$GLOBALS['abcc_test_post_types']   = array();

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
$GLOBALS['abcc_test_filters'] = array();
function add_filter($hook, $callback) { $GLOBALS['abcc_test_filters'][ $hook ][] = $callback; }
function remove_all_filters($hook) { unset($GLOBALS['abcc_test_filters'][ $hook ]); }
function apply_filters($hook, $value, ...$args) {
	foreach ( $GLOBALS['abcc_test_filters'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func( $callback, $value, ...$args );
	}
	return $value;
}
function add_action($hook, $callback) { $GLOBALS['abcc_test_actions'][ $hook ][] = $callback; }
function current_user_can($cap, ...$args) {
	// Per-object cap (e.g. current_user_can('edit_post', $post_id)): a test can
	// mark specific post IDs as uneditable via $GLOBALS['abcc_test_uneditable_posts'].
	if ( 'edit_post' === $cap && ! empty( $args ) ) {
		$post_id = (int) $args[0];
		if ( in_array( $post_id, (array) ( $GLOBALS['abcc_test_uneditable_posts'] ?? array() ), true ) ) {
			return false;
		}
	}
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
function update_option($key, $value) {
	// Mirror WP: booleans persist as '1' / '' strings on the next read.
	if ( is_bool( $value ) ) {
		$value = $value ? '1' : '';
	}
	$GLOBALS['abcc_test_options'][ $key ] = $value;
	return true;
}
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
function wp_get_attachment_url($attachment_id) { return 'https://example.test/wp-content/uploads/audio-' . (int) $attachment_id . '.mp3'; }
function get_post_meta($post_id, $key = '', $single = false) {
	$meta = $GLOBALS['abcc_test_post_meta'][ (int) $post_id ][ $key ] ?? null;
	if ( null === $meta ) {
		return $single ? '' : array();
	}
	return $single ? $meta : array( $meta );
}
function update_post_meta($post_id, $key, $value) { $GLOBALS['abcc_test_post_meta'][ (int) $post_id ][ $key ] = $value; return true; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['abcc_test_post_meta'][ (int) $post_id ][ $key ]); return true; }
function get_post_type($post_id) { return $GLOBALS['abcc_test_post_types'][ (int) $post_id ] ?? false; }
function wp_cache_delete($key, $group = '') { return true; }
function current_time($type) { return gmdate('Y-m-d H:i:s'); }
function register_post_type($post_type, $args = array()) { return true; }
function wp_mail($to, $subject, $message) { return true; }
$GLOBALS['abcc_test_scheduled_events'] = array();
function wp_schedule_single_event($timestamp, $hook, $args = array()) {
	$GLOBALS['abcc_test_scheduled_events'][] = array(
		'timestamp' => $timestamp,
		'hook'      => $hook,
		'args'      => $args,
	);
	return true;
}
function spawn_cron() { return true; }

// --- Minimal in-memory posts store (shares post-type/meta stores above) ---
$GLOBALS['abcc_test_posts']        = array();
$GLOBALS['abcc_test_next_post_id'] = 1000;

function wp_insert_post($postarr, $wp_error = false) {
	if ( ! empty( $GLOBALS['abcc_test_force_insert_error'] ) || ! empty( $GLOBALS['abcc_test_force_insert_error_once'] ) ) {
		unset( $GLOBALS['abcc_test_force_insert_error_once'] );
		return $wp_error ? new WP_Error( 'insert_failed', 'Simulated insert failure.' ) : 0;
	}

	$id = ++$GLOBALS['abcc_test_next_post_id'];

	$post               = new stdClass();
	$post->ID           = $id;
	$post->post_type    = $postarr['post_type'] ?? 'post';
	$post->post_status  = $postarr['post_status'] ?? 'draft';
	$post->post_title   = $postarr['post_title'] ?? '';
	$post->post_content = $postarr['post_content'] ?? '';

	$GLOBALS['abcc_test_posts'][ $id ]      = $post;
	$GLOBALS['abcc_test_post_types'][ $id ] = $post->post_type;

	foreach ( ( $postarr['meta_input'] ?? array() ) as $key => $value ) {
		$GLOBALS['abcc_test_post_meta'][ $id ][ $key ] = $value;
	}

	return $id;
}

function wp_update_post($postarr, $wp_error = false) {
	$id = (int) ( $postarr['ID'] ?? 0 );
	if ( empty( $GLOBALS['abcc_test_posts'][ $id ] ) ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}
	$post = $GLOBALS['abcc_test_posts'][ $id ];
	foreach ( array( 'post_status', 'post_title', 'post_content' ) as $field ) {
		if ( isset( $postarr[ $field ] ) ) {
			$post->{$field} = $postarr[ $field ];
		}
	}
	return $id;
}

function wp_delete_post($post_id, $force = false) {
	$post_id = (int) $post_id;
	$post    = $GLOBALS['abcc_test_posts'][ $post_id ] ?? false;
	unset(
		$GLOBALS['abcc_test_posts'][ $post_id ],
		$GLOBALS['abcc_test_post_types'][ $post_id ],
		$GLOBALS['abcc_test_post_meta'][ $post_id ]
	);
	return $post;
}

function get_post($post_id) { return $GLOBALS['abcc_test_posts'][ (int) $post_id ] ?? null; }

/**
 * Seed a topic post (publish) for resolver/composer tests.
 *
 * Title -> post_title (topic name), prompt -> post_content.
 */
function abcc_test_make_topic( $title, $prompt ) {
	static $next_id = 9000;
	$id = $next_id++;

	$post               = new stdClass();
	$post->ID           = $id;
	$post->post_type    = defined( 'ABCC_TOPIC_POST_TYPE' ) ? ABCC_TOPIC_POST_TYPE : 'abcc_topic';
	$post->post_status  = 'publish';
	$post->post_title   = $title;
	$post->post_content = $prompt;

	$GLOBALS['abcc_test_posts'][ $id ]      = $post;
	$GLOBALS['abcc_test_post_types'][ $id ] = $post->post_type;

	return $id;
}

function get_posts($args = array()) {
	$results  = array();
	$statuses = (array) ( $args['post_status'] ?? array( 'publish' ) );

	foreach ( $GLOBALS['abcc_test_posts'] as $post ) {
		if ( isset( $args['post_type'] ) && 'any' !== $args['post_type'] && $post->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( ! in_array( 'any', $statuses, true ) && ! in_array( $post->post_status, $statuses, true ) ) {
			continue;
		}
		if ( ! empty( $args['meta_query'] ) && ! abcc_test_post_matches_meta_query( $post->ID, $args['meta_query'] ) ) {
			continue;
		}
		$results[] = $post;
	}

	return $results;
}

/**
 * Evaluate a (flat, AND-only) meta_query against the meta store.
 */
function abcc_test_post_matches_meta_query($post_id, $meta_query) {
	foreach ( $meta_query as $clause ) {
		if ( ! is_array( $clause ) || empty( $clause['key'] ) ) {
			continue;
		}
		$stored  = $GLOBALS['abcc_test_post_meta'][ $post_id ][ $clause['key'] ] ?? null;
		$compare = $clause['compare'] ?? '=';
		$value   = $clause['value'] ?? '';

		if ( '<=' === $compare ) {
			if ( null === $stored || ! ( (float) $stored <= (float) $value ) ) {
				return false;
			}
		} elseif ( (string) $stored !== (string) $value ) {
			return false;
		}
	}
	return true;
}
function wp_upload_dir() {
	$base = sys_get_temp_dir() . '/abcc-test-uploads';
	return array(
		'path' => $base,
		'url'  => 'http://example.test/uploads',
	);
}
function wp_mkdir_p($dir) { return is_dir($dir) || mkdir($dir, 0777, true); }

if ( ! class_exists( 'ABCC_Test_WPDB' ) ) {
	/**
	 * Minimal $wpdb stub: prepare() interpolates, query() supports the
	 * conditional job-status UPDATE against the shared post-meta store.
	 */
	class ABCC_Test_WPDB {
		public $postmeta = 'wp_postmeta';

		public function prepare( $query, ...$args ) {
			$query = str_replace( array( '%s', '%d' ), array( "'%s'", '%d' ), $query );
			$escaped = array();
			foreach ( $args as $arg ) {
				$escaped[] = is_int( $arg ) ? $arg : addslashes( (string) $arg );
			}
			return vsprintf( $query, $escaped );
		}

		public function query( $sql ) {
			if ( preg_match( "/UPDATE \\S+ SET meta_value = '([^']*)'\\s+WHERE post_id = (\\d+) AND meta_key = '([^']*)' AND meta_value = '([^']*)'/s", $sql, $m ) ) {
				$post_id = (int) $m[2];
				$current = $GLOBALS['abcc_test_post_meta'][ $post_id ][ $m[3] ] ?? null;
				if ( $current === $m[4] ) {
					$GLOBALS['abcc_test_post_meta'][ $post_id ][ $m[3] ] = $m[1];
					return 1;
				}
				return 0;
			}
			return 0;
		}
	}
}
$GLOBALS['wpdb'] = new ABCC_Test_WPDB();

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

if ( ! function_exists( 'wp_remote_request' ) ) {
	// ABCC_OpenAI_Client uses wp_remote_request; share the same canned queue.
	function wp_remote_request( $url, $args = array() ) {
		return wp_remote_post( $url, $args );
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
require_once dirname(__DIR__) . '/includes/topics.php';
require_once dirname(__DIR__) . '/includes/images.php';
require_once dirname(__DIR__) . '/includes/content-generation.php';
require_once dirname(__DIR__) . '/includes/seo.php';
require_once dirname(__DIR__) . '/includes/seo-regen.php';
require_once dirname(__DIR__) . '/includes/bulk-seo.php';
require_once dirname(__DIR__) . '/includes/class-abcc-openai-client.php';
require_once dirname(__DIR__) . '/gpt.php';
require_once dirname(__DIR__) . '/includes/blocks.php';
require_once dirname(__DIR__) . '/includes/audio.php';
require_once dirname(__DIR__) . '/includes/class-abcc-job.php';
require_once dirname(__DIR__) . '/includes/onboarding.php';

// Snapshot the AJAX/action hooks registered at file scope during plugin load,
// before any test resets $GLOBALS['abcc_test_actions']. Lets a test assert that
// a bootstrap-loaded file (e.g. audio.php) registered its hook, without
// re-requiring it (which would fatal on function redeclare).
$GLOBALS['abcc_test_actions_at_load'] = $GLOBALS['abcc_test_actions'];

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $text, $allowed_tags = array() ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true ) {
		return str_repeat( 'x', (int) $length );
	}
}

/**
 * Queue a raw canned HTTP response for the next wp_remote_post/wp_remote_request.
 *
 * @param string $body Raw response body string.
 */
function abcc_test_queue_http_response( $body ) {
	$GLOBALS['abcc_http_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => $body,
	);
}

/**
 * Return a fake Whisper transcription response body (raw text — response_format=text).
 *
 * @param string $text The transcript text.
 * @return string
 */
function abcc_test_fake_transcription( $text ) {
	return $text;
}

/**
 * Return a fake chat-completion response body whose content is SEO JSON,
 * shaped for abcc_generate_title_and_seo's OpenAI parser.
 *
 * Keys match the schema seo.php expects at lines 106-113:
 * title, meta_description, primary_keyword, secondary_keywords, social_excerpt.
 *
 * @param string $title       SEO title.
 * @param string $description Meta description.
 * @param string $keyword     Primary keyword.
 * @return string JSON-encoded chat-completion response body.
 */
function abcc_test_fake_seo_json( $title, $description, $keyword ) {
	$seo_json = wp_json_encode(
		array(
			'title'               => $title,
			'meta_description'    => $description,
			'primary_keyword'     => $keyword,
			'secondary_keywords'  => array(),
			'social_excerpt'      => $title . ' - ' . $keyword,
		)
	);

	return wp_json_encode(
		array(
			'choices' => array(
				array(
					'message'       => array( 'content' => $seo_json ),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
			),
		)
	);
}

/**
 * Return a fake chat-completion response body shaped for abcc_call_provider_api's OpenAI parser.
 *
 * @param string $text The generated text (may contain newlines).
 * @return string JSON-encoded response body.
 */
function abcc_test_fake_generation( $text ) {
	return wp_json_encode(
		array(
			'choices' => array(
				array(
					'message'       => array( 'content' => $text ),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 10,
				'completion_tokens' => 20,
			),
		)
	);
}

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
