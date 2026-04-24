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
		// Register activation hook.
		register_activation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'activate_plugin' ) );

		// Register deactivation hook.
		register_deactivation_hook( dirname( __DIR__ ) . '/auto-post.php', array( $this, 'deactivate_plugin' ) );

		// Enqueue scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'display_settings_errors' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_ai_disabled_notice' ) );

		// Onboarding.
		add_action( 'admin_init', 'abcc_check_existing_user_on_activation' );

		// Migrations.
		add_action( 'admin_init', array( $this, 'run_migrations' ) );

		// Daily provider health check cron.
		add_action( 'abcc_daily_provider_health_check', 'abcc_run_provider_health_check' );
		if ( ! wp_next_scheduled( 'abcc_daily_provider_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'abcc_daily_provider_health_check' );
		}
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
		wp_clear_scheduled_hook( 'abcc_daily_provider_health_check' );
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

		// Post edit screen: meta box scripts.
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style(
				'abcc-meta-box-styles',
				plugins_url( '/css/admin.css', __DIR__ ),
				array(),
				$this->version
			);
			wp_enqueue_script(
				'abcc-meta-boxes',
				plugins_url( '/js/meta-boxes.js', __DIR__ ),
				array( 'jquery', 'abcc-ui-script' ),
				$this->version,
				true
			);
			wp_localize_script(
				'abcc-meta-boxes',
				'abccMetaBox',
				array(
					'i18n' => array(
						/* translators: confirmation dialog when rewriting a post with AI */
						'confirmRewrite'        => __( 'Are you sure you want to rewrite this post? This will replace the current content.', 'automated-blog-content-creator' ),
						/* translators: confirmation dialog when regenerating a post as a new draft */
						'confirmRegenerate'     => __( 'Regenerate this post? A new draft will be created with the same parameters.', 'automated-blog-content-creator' ),
						/* translators: confirmation dialog when creating an infographic */
						'confirmInfographic'    => __( 'Create an infographic for this post?', 'automated-blog-content-creator' ),
						/* translators: button label while AI rewrite is in progress */
						'rewriting'             => __( 'Rewriting...', 'automated-blog-content-creator' ),
						'analyzing'             => __( 'Analyzing content...', 'automated-blog-content-creator' ),
						'rewriteSuccess'        => __( 'Success! Reloading page...', 'automated-blog-content-creator' ),
						'rewriteBtn'            => __( 'Rewrite with AI', 'automated-blog-content-creator' ),
						/* translators: button label while regenerating a post */
						'regenerating'          => __( "Regenerating\u2026", 'automated-blog-content-creator' ),
						'generatingDraft'       => __( "Generating new draft\u2026", 'automated-blog-content-creator' ),
						'regenerateSuccess'     => __( "Done! Opening new draft\u2026", 'automated-blog-content-creator' ),
						'regenerateBtn'         => __( 'Regenerate as New Draft', 'automated-blog-content-creator' ),
						/* translators: button label while infographic is being created */
						'creating'              => __( 'Creating...', 'automated-blog-content-creator' ),
						'generatingInfographic' => __( 'Generating infographic...', 'automated-blog-content-creator' ),
						'infographicSuccess'    => __( 'Success!', 'automated-blog-content-creator' ),
						'infographicBtn'        => __( 'Create Infographic', 'automated-blog-content-creator' ),
						'view'                  => __( 'View', 'automated-blog-content-creator' ),
						'edit'                  => __( 'Edit', 'automated-blog-content-creator' ),
						'networkError'          => __( 'Network error occurred', 'automated-blog-content-creator' ),
						'unknownError'          => __( 'Unknown error', 'automated-blog-content-creator' ),
					),
				)
			);
		}

		// Everything else is only needed on the plugin's own settings page.
		if ( 'toplevel_page_automated-blog-content-creator-post' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'select2-css', plugins_url( '/css/select2.min.css', __DIR__ ), array(), '4.1.0-rc.0' );
		wp_enqueue_script( 'select2-js', plugins_url( '/js/select2.min.js', __DIR__ ), array( 'jquery' ), '4.1.0-rc.0', true );
		wp_enqueue_style( 'abcc-admin-style', plugins_url( '/css/admin-style.css', __DIR__ ), array(), $this->version );
	}

	/**
	 * Display settings errors
	 */
	public function display_settings_errors() {
		settings_errors( 'openai-settings' );
	}

	/**
	 * Render a dismissible notice when WP 7.0 reports AI is disabled at
	 * the site level. Shown only on WP-AutoInsight admin pages so it
	 * does not follow the user around the dashboard.
	 *
	 * @since 4.1.0
	 */
	public function maybe_render_ai_disabled_notice() {
		if ( ! function_exists( 'wp_supports_ai' ) ) {
			return;
		}
		if ( wp_supports_ai() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'automated-blog-content-creator' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__( 'AI features are currently disabled at the WordPress level. WP-AutoInsight will not generate content until a site administrator enables AI support in WordPress Settings.', 'automated-blog-content-creator' )
		);
	}
}
