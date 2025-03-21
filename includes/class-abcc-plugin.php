<?php
/**
 * Main plugin class file
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin class
 */
class ABCC_Plugin {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * The single instance of the class
	 *
	 * @var ABCC_Plugin
	 */
	protected static $_instance = null;

	/**
	 * Main ABCC_Plugin Instance
	 *
	 * Ensures only one instance of ABCC_Plugin is loaded or can be loaded.
	 *
	 * @return ABCC_Plugin - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version = filemtime( plugin_dir_path( __DIR__ ) . '/auto-post.php' );
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Register activation hook
		register_activation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'check_requirements' ) );

		// Register deactivation hook
		register_deactivation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'deactivate_plugin' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'display_settings_errors' ) );
	}

	/**
	 * Check if the WordPress and PHP versions meet the plugin's requirements.
	 */
	public function check_requirements() {
		$wp_version = get_bloginfo( 'version' );

		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__ ) . '/auto-post.php' ) );
			wp_die(
				sprintf(
					/* translators: %s: PHP version */
					esc_html__( 'WP-AutoInsight requires PHP version 7.4 or higher. Your current PHP version is %s. Please upgrade your PHP version or contact your host for assistance.', 'automated-wordpress-content-creator' ),
					esc_html( PHP_VERSION )
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		if ( version_compare( $wp_version, '5.6', '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__ ) . '/auto-post.php' ) );
			wp_die(
				sprintf(
					/* translators: %s: WordPress version */
					esc_html__( 'WP-AutoInsight requires WordPress version 5.6 or higher. Your current WordPress version is %s. Please upgrade WordPress to activate this plugin.', 'automated-wordpress-content-creator' ),
					esc_html( $wp_version )
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Deactivate plugin and clear scheduled hooks
	 */
	public function deactivate_plugin() {
		wp_clear_scheduled_hook( 'abcc_openai_generate_post_hook' );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
		wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0', true );
		wp_enqueue_style( 'abcc-admin-style', plugins_url( '/css/admin-style.css', __DIR__ ), array(), $this->version, true );
		wp_enqueue_script( 'abcc-admin-script', plugins_url( '/js/admin-script.js', __DIR__ ), array( 'jquery' ), $this->version, true );
	}

	/**
	 * Display settings errors
	 */
	public function display_settings_errors() {
		settings_errors( 'openai-settings' );
	}
}
