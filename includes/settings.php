<?php
/**
 * Versioned settings schema and migration helpers.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get the default content template.
 *
 * @return array
 */
function abcc_get_default_content_template() {
	return array(
		'name'   => 'Default Template',
		'prompt' => "Write a {tone} blog post with the following title: {title}\n\nUsing these keywords: {keywords}",
	);
}

/**
 * Get the versioned settings schema.
 *
 * @return array
 */
function abcc_get_settings_schema() {
	return array(
		'version'  => ABCC_VERSION,
		'settings' => array(
			'abcc_version'                    => array( 'default' => ABCC_VERSION ),
			'abcc_onboarding_completed'       => array( 'default' => false ),
			'abcc_keyword_groups'             => array(
				'default' => array(
					array(
						'name'     => 'Default Group',
						'keywords' => array(),
						'category' => 0,
						'template' => 'default',
					),
				),
			),
			'abcc_content_templates'          => array(
				'default' => array(
					'default' => abcc_get_default_content_template(),
				),
			),
			'custom_tone'                     => array( 'default' => '' ),
			'openai_tone'                     => array( 'default' => 'friendly' ),
			'openai_generate_seo'             => array( 'default' => true ),
			'abcc_draft_first'                => array( 'default' => true ),
			'abcc_selected_post_types'        => array( 'default' => array( 'post' ) ),
			'prompt_select'                   => array( 'default' => 'gpt-4.1-mini-2025-04-14' ),
			'openai_api_key'                  => array( 'default' => '' ),
			'gemini_api_key'                  => array( 'default' => '' ),
			'claude_api_key'                  => array( 'default' => '' ),
			'perplexity_api_key'              => array( 'default' => '' ),
			'stability_api_key'               => array( 'default' => '' ),
			'abcc_perplexity_citation_style'  => array( 'default' => 'inline' ),
			'abcc_perplexity_recency_filter'  => array( 'default' => '' ),
			'openai_auto_create'              => array( 'default' => '' ),
			'openai_char_limit'               => array( 'default' => 200 ),
			'openai_email_notifications'      => array( 'default' => false ),
			'openai_generate_images'          => array( 'default' => true ),
			'preferred_image_service'         => array( 'default' => 'auto' ),
			'abcc_gemini_image_model'         => array( 'default' => 'gemini-2.5-flash-image' ),
			'abcc_gemini_image_size'          => array( 'default' => '2K' ),
			'abcc_openai_image_size'          => array( 'default' => '1024x1024' ),
			'abcc_openai_image_quality'       => array( 'default' => 'standard' ),
			'abcc_stability_image_size'       => array( 'default' => '1024x1024' ),
			'abcc_enable_audio_transcription' => array( 'default' => true ),
			'abcc_supported_audio_formats'    => array( 'default' => array( 'mp3', 'wav', 'm4a', 'webm' ) ),
			'abcc_transcription_language'     => array( 'default' => 'en' ),
		),
	);
}

/**
 * Get a setting definition.
 *
 * @param string $key Setting key.
 * @return array|null
 */
function abcc_get_setting_definition( $key ) {
	$schema = abcc_get_settings_schema();

	return $schema['settings'][ $key ] ?? null;
}

/**
 * Get the default value for a setting.
 *
 * @param string $key      Setting key.
 * @param mixed  $fallback Fallback default.
 * @return mixed
 */
function abcc_get_setting_default( $key, $fallback = null ) {
	$definition = abcc_get_setting_definition( $key );

	if ( isset( $definition['default'] ) ) {
		return $definition['default'];
	}

	return $fallback;
}

/**
 * Get a setting value using the schema default when needed.
 *
 * @param string $key      Setting key.
 * @param mixed  $fallback Fallback default.
 * @return mixed
 */
function abcc_get_setting( $key, $fallback = null ) {
	return get_option( $key, abcc_get_setting_default( $key, $fallback ) );
}

/**
 * Update a setting.
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value.
 * @return bool
 */
function abcc_update_setting( $key, $value ) {
	return update_option( $key, $value );
}

/**
 * Queue a one-time migration notice.
 *
 * @param string $from_version Previous version.
 * @param string $to_version   Current version.
 * @return void
 */
function abcc_queue_settings_migration_notice( $from_version, $to_version ) {
	update_option(
		'abcc_settings_migration_notice',
		array(
			'from' => $from_version,
			'to'   => $to_version,
		)
	);
}

/**
 * Display the migration notice.
 *
 * @return void
 */
function abcc_display_settings_migration_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = get_option( 'abcc_settings_migration_notice', array() );
	if ( empty( $notice['from'] ) || empty( $notice['to'] ) ) {
		return;
	}

	delete_option( 'abcc_settings_migration_notice' );
	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: previous version, 2: current version */
					__( 'Settings migrated from v%1$s to v%2$s. Your API keys and keyword groups are intact.', 'automated-blog-content-creator' ),
					$notice['from'],
					$notice['to']
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'abcc_display_settings_migration_notice' );

/**
 * Run settings migrations.
 *
 * @return void
 */
function abcc_run_settings_migrations() {
	$installed_version = get_option( 'abcc_version', '1.0.0' );
	$start_version     = $installed_version;

	if ( version_compare( $installed_version, '3.3.0', '<' ) ) {
		$installed_version = '3.3.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( $installed_version, '3.5.0', '<' ) ) {
		if ( false === get_option( 'abcc_content_templates' ) ) {
			abcc_update_setting(
				'abcc_content_templates',
				array(
					'default' => abcc_get_default_content_template(),
				)
			);
		}

		if ( false === get_option( 'abcc_keyword_groups' ) ) {
			$old_keywords        = get_option( 'openai_keywords', '' );
			$selected_categories = get_option( 'openai_selected_categories', array() );
			$keyword_array       = array_filter( array_map( 'trim', explode( "\n", $old_keywords ) ) );

			abcc_update_setting(
				'abcc_keyword_groups',
				array(
					array(
						'name'     => 'Default Group',
						'keywords' => $keyword_array,
						'category' => ! empty( $selected_categories ) ? (int) $selected_categories[0] : 0,
						'template' => 'default',
					),
				)
			);
		}

		$installed_version = '3.5.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( $installed_version, '3.6.0', '<' ) ) {
		if ( method_exists( 'ABCC_Plugin', 'instance' ) ) {
			ABCC_Plugin::instance()->setup_prompt_ai_capability();
		}

		$installed_version = '3.6.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( $installed_version, '3.7.0', '<' ) ) {
		$installed_version = '3.7.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( $installed_version, '3.8.0', '<' ) ) {
		if ( false === get_option( 'abcc_content_templates' ) ) {
			abcc_update_setting(
				'abcc_content_templates',
				array(
					'default' => abcc_get_default_content_template(),
				)
			);
		}

		$current_model = get_option( 'prompt_select', '' );
		if ( empty( $current_model ) ) {
			abcc_update_setting( 'prompt_select', abcc_get_setting_default( 'prompt_select' ) );
		}

		$installed_version = '3.8.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( get_option( 'abcc_version', '1.0.0' ), ABCC_VERSION, '<' ) ) {
		abcc_update_setting( 'abcc_version', ABCC_VERSION );
	}

	if ( version_compare( $start_version, ABCC_VERSION, '<' ) ) {
		abcc_queue_settings_migration_notice( $start_version, ABCC_VERSION );
	}
}
