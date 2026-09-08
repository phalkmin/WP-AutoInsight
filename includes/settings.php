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
		'prompt' => 'Write a {tone} blog post in {language} titled "{title}", focused on {keyword}, '
			. 'for readers interested in {category}. Work these related keywords in naturally where they fit: {keywords}. '
			. 'Open with a concrete hook — a specific detail, number, or scenario — never a broad scene-setting cliché. '
			. 'Prefer short paragraphs and concrete examples over abstractions.',
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
			'abcc_default_post_status'        => array(
				'default'  => 'draft',
				'sanitize' => 'abcc_sanitize_post_status',
			),
			'abcc_content_language'           => array(
				'default'  => 'site',
				'sanitize' => 'abcc_sanitize_content_language',
			),
			'abcc_selected_post_types'        => array( 'default' => array( 'post' ) ),
			'prompt_select'                   => array( 'default' => 'gpt-5.4-mini' ),
			'abcc_composer_last_source'       => array(
				'default'  => '',
				'sanitize' => 'abcc_sanitize_composer_source',
			),
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
			'abcc_openai_image_model'         => array( 'default' => 'gpt-image-1' ),
			'abcc_openai_image_size'          => array( 'default' => '1024x1024' ),
			'abcc_openai_image_quality'       => array( 'default' => 'medium' ),
			'abcc_stability_image_size'       => array( 'default' => '1024x1024' ),
			'abcc_enable_audio_transcription' => array( 'default' => true ),
			'abcc_supported_audio_formats'    => array( 'default' => array( 'mp3', 'wav', 'm4a', 'webm' ) ),
			'abcc_transcription_language'     => array( 'default' => 'en' ),
			'abcc_audio_default_mode'         => array(
				'default'  => 'transcript_plus_intro',
				'sanitize' => 'abcc_sanitize_audio_mode',
			),
			'abcc_auto_alt_text'              => array( 'default' => true ),
			'abcc_enable_infographics'        => array( 'default' => true ),
			'abcc_infographic_provider'       => array( 'default' => 'auto' ),
			'abcc_topics_last_sweep'          => array(
				'default'  => 0,
				'sanitize' => 'absint',
			),
			'abcc_allowed_roles'              => array( 'default' => array( 'administrator', 'editor' ) ),
			'abcc_debug_logging'              => array( 'default' => false ),
		),
	);
}

/**
 * Sanitize a post-status setting value.
 *
 * Generated posts are only ever created as draft or publish; anything
 * unexpected falls back to the safe default.
 *
 * @since 4.2.0
 * @param mixed $value Raw value.
 * @return string 'publish' or 'draft'.
 */
function abcc_sanitize_post_status( $value ) {
	return 'publish' === $value ? 'publish' : 'draft';
}

/**
 * Sanitize a Composer source token.
 *
 * Valid shapes are 'group:<int>' (keyword-group index) and 'topic:<int>'
 * (topic post ID). Anything else — including non-strings — collapses to
 * the empty string, which the resolver treats as "first group with keywords".
 *
 * @since 4.3.0
 * @param mixed $value Raw value.
 * @return string '' | 'group:N' | 'topic:N'
 */
function abcc_sanitize_composer_source( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}

	if ( preg_match( '/^(group|topic):(\d+)$/', $value, $matches ) ) {
		return $matches[1] . ':' . (int) $matches[2];
	}

	return '';
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
 * Display the one-time draft-mode notice after upgrading to 4.2.
 *
 * Existing installs default to draft-first after the upgrade; the notice
 * explains the change and links to the setting. Dismissal is per-user.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_display_draft_mode_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( 1 !== (int) get_option( 'abcc_draft_mode_notice', 0 ) ) {
		return;
	}

	if ( get_user_meta( get_current_user_id(), 'abcc_draft_mode_notice_dismissed', true ) ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=automated-blog-content-creator-post&tab=settings' );
	?>
	<div class="notice notice-info is-dismissible" data-abcc-notice="draft-mode">
		<p>
			<strong><?php esc_html_e( 'WP-AutoInsight 4.2:', 'automated-blog-content-creator' ); ?></strong>
			<?php esc_html_e( 'Generated posts are now saved as drafts by default so you can review them before they go live. Prefer immediate publishing? Change it in the settings.', 'automated-blog-content-creator' ); ?>
			<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Review setting', 'automated-blog-content-creator' ); ?></a>
		</p>
	</div>
	<script>
	jQuery( function ( $ ) {
		$( document ).on( 'click', '[data-abcc-notice="draft-mode"] .notice-dismiss', function () {
			$.post( ajaxurl, {
				action: 'abcc_dismiss_draft_mode_notice',
				nonce: '<?php echo esc_js( wp_create_nonce( 'abcc_dismiss_draft_mode_notice' ) ); ?>'
			} );
		} );
	} );
	</script>
	<?php
}
add_action( 'admin_notices', 'abcc_display_draft_mode_notice' );

/**
 * Persist per-user dismissal of the draft-mode notice.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_dismiss_draft_mode_notice() {
	check_ajax_referer( 'abcc_dismiss_draft_mode_notice', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
		return;
	}

	update_user_meta( get_current_user_id(), 'abcc_draft_mode_notice_dismissed', 1 );
	wp_send_json_success();
}
add_action( 'wp_ajax_abcc_dismiss_draft_mode_notice', 'abcc_handle_dismiss_draft_mode_notice' );

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

	if ( version_compare( $installed_version, '4.0.0', '<' ) ) {
		// Seed allowed roles if not already set (new in 4.0).
		if ( false === get_option( 'abcc_allowed_roles' ) ) {
			abcc_update_setting( 'abcc_allowed_roles', abcc_get_setting_default( 'abcc_allowed_roles' ) );
		}

		// Seed debug logging flag if not already set (new in 4.0).
		if ( false === get_option( 'abcc_debug_logging' ) ) {
			abcc_update_setting( 'abcc_debug_logging', abcc_get_setting_default( 'abcc_debug_logging' ) );
		}

		$installed_version = '4.0.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( $installed_version, '4.2.0', '<' ) ) {
		// Raw get_option on purpose: abcc_get_setting would mask an explicit
		// false ("always publish") behind the schema default (true).
		$old_draft_first = get_option( 'abcc_draft_first' );
		if ( false !== $old_draft_first ) {
			abcc_update_setting( 'abcc_default_post_status', $old_draft_first ? 'draft' : 'publish' );

			// One-time notice for real upgrades whose stored preference was
			// translated; fresh installs and pre-draft_first installs (no
			// behavior change — both default to draft) are skipped.
			if ( version_compare( $start_version, '1.0.0', '>' ) ) {
				update_option( 'abcc_draft_mode_notice', 1 );
			}
		}
		// abcc_draft_first is superseded — left in place until the UI swap;
		// abcc_resolve_post_status() reads only abcc_default_post_status.

		$installed_version = '4.2.0';
		abcc_update_setting( 'abcc_version', $installed_version );
	}

	if ( version_compare( get_option( 'abcc_version', '1.0.0' ), ABCC_VERSION, '<' ) ) {
		// Cron events are scheduled on activation only (since 4.4.0); re-check
		// on upgrade so installs that lost them recover without a reactivate.
		if ( ! wp_next_scheduled( 'abcc_daily_provider_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'abcc_daily_provider_health_check' );
		}
		if ( ! wp_next_scheduled( 'abcc_run_topic_schedules' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'abcc_run_topic_schedules' );
		}

		// The 'default' template slug is read-only in the UI, so upgrades can
		// refresh it — otherwise stored copies keep the wording of whatever
		// version first wrote them. Custom templates are never touched.
		$abcc_templates = abcc_get_setting( 'abcc_content_templates', array() );
		if ( is_array( $abcc_templates ) ) {
			$abcc_templates['default'] = abcc_get_default_content_template();
			abcc_update_setting( 'abcc_content_templates', $abcc_templates );
		}

		abcc_update_setting( 'abcc_version', ABCC_VERSION );
	}

	if ( version_compare( $start_version, ABCC_VERSION, '<' ) ) {
		abcc_queue_settings_migration_notice( $start_version, ABCC_VERSION );
	}
}
