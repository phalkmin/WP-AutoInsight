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
	protected static $instance = null;

	/**
	 * Main ABCC_Plugin Instance
	 *
	 * Ensures only one instance of ABCC_Plugin is loaded or can be loaded.
	 *
	 * @return ABCC_Plugin - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version = ABCC_VERSION;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Register activation hook
		register_activation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'activate_plugin' ) );

		// Register deactivation hook
		register_deactivation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'deactivate_plugin' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin notices
		add_action( 'admin_notices', array( $this, 'display_settings_errors' ) );

		// Onboarding
		add_action( 'admin_init', 'abcc_check_existing_user_on_activation' );

		// Migrations
		add_action( 'admin_init', array( $this, 'run_migrations' ) );

		// WP 7.0 Connectors registration
		add_action( 'connections-wp-admin-init', array( $this, 'register_wp70_connector' ) );
	}

	/**
	 * Run database migrations.
	 */
	public function run_migrations() {
		abcc_run_settings_migrations();
	}

	/**
	 * Setup prompt_ai capability for administrators.
	 *
	 * @since 3.6.0
	 */
	public function setup_prompt_ai_capability() {
		$roles_to_grant = array( 'administrator', 'editor' );

		foreach ( $roles_to_grant as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( 'prompt_ai' ) ) {
				$role->add_cap( 'prompt_ai' );
			}
		}
	}

	/**
	 * Register the plugin on the WordPress 7.0 Connectors page.
	 *
	 * @since 3.6.0
	 */
	public function register_wp70_connector() {
		if ( ! function_exists( 'wp_ai_register_connector' ) ) {
			return;
		}

		wp_ai_register_connector(
			array(
				'id'           => 'wp-autoinsight',
				'name'         => __( 'WP-AutoInsight', 'automated-blog-content-creator' ),
				'description'  => __( 'Automated AI content generation for posts, SEO, images, and scheduling.', 'automated-blog-content-creator' ),
				'settings_url' => admin_url( 'admin.php?page=automated-blog-content-creator-post' ),
				'version'      => defined( 'ABCC_VERSION' ) ? ABCC_VERSION : $this->version,
			)
		);
	}

	/**
	 * Plugin activation logic.
	 */
	public function activate_plugin() {
		$this->check_requirements();
		$this->setup_prompt_ai_capability();
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
					esc_html__( 'WP-AutoInsight requires PHP version 7.4 or higher. Your current PHP version is %s. Please upgrade your PHP version or contact your host for assistance.', 'automated-blog-content-creator' ),
					esc_html( PHP_VERSION )
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		if ( version_compare( $wp_version, '6.8', '<' ) ) {
			deactivate_plugins( plugin_basename( dirname( __DIR__ ) . '/auto-post.php' ) );
			wp_die(
				sprintf(
					/* translators: %s: WordPress version */
					esc_html__( 'WP-AutoInsight requires WordPress version 6.8 or higher. Your current WordPress version is %s. Please upgrade WordPress to activate this plugin.', 'automated-blog-content-creator' ),
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
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// abcc-ui.js provides the shared status/spinner component used by meta boxes
		// on post edit screens, so it loads on all admin pages.
		wp_enqueue_script( 'abcc-ui-script', plugins_url( '/js/abcc-ui.js', __DIR__ ), array( 'jquery' ), $this->version, true );

		// Everything else is only needed on the plugin's own settings page.
		if ( 'toplevel_page_automated-blog-content-creator-post' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
		wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );
		wp_enqueue_style( 'abcc-admin-style', plugins_url( '/css/admin-style.css', __DIR__ ), array(), $this->version );
	}

	/**
	 * Display settings errors
	 */
	public function display_settings_errors() {
		settings_errors( 'openai-settings' );
	}
}
