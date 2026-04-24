<?php
/**
 * File: admin.php
 *
 * This file contains functions related to the administration settings
 * of the WP-AutoInsight plugin, including menu pages and options.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Triggers inline API key validation via JavaScript.
 *
 * @since 3.4.0
 * @return void
 */
function abcc_trigger_inline_api_validation() {
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			if (typeof abccValidateAPIKeys === 'function') {
				abccValidateAPIKeys();
			}
		});
	</script>
	<?php
}

/**
 * Adds subpages for "Text Settings" and "Advanced Settings" under the main menu page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function abcc_add_subpages_to_menu() {
	add_menu_page(
		__( 'WP-AutoInsight', 'automated-blog-content-creator' ),
		__( 'WP-AutoInsight', 'automated-blog-content-creator' ),
		'manage_options',
		'automated-blog-content-creator-post',
		'abcc_openai_text_settings_page'
	);
}
add_action( 'admin_menu', 'abcc_add_subpages_to_menu' );

/**
 * Returns an array of AI model options filtered by available API keys.
 *
 * @since 1.0.0
 * @return array Array of AI model options grouped by available providers.
 */
function abcc_get_ai_model_options() {
	return abcc_get_available_text_model_options();
}

/**
 * Returns HTML for a contextual help tooltip.
 *
 * @since 3.4.0
 * @param string $text The tooltip text.
 * @return string Tooltip HTML.
 */
function abcc_get_tooltip_html( $text ) {
	return wp_kses(
		sprintf(
			'<span class="wpai-tooltip" data-tooltip="%1$s"><span class="dashicons dashicons-editor-help"></span></span>',
			esc_attr( $text )
		),
		array(
			'span' => array(
				'class'        => array(),
				'data-tooltip' => array(),
			),
		)
	);
}

/**
 * Returns the current primary tab slug.
 *
 * @return string
 */
function abcc_get_current_tab() {
	$allowed = array( 'dashboard', 'content', 'media', 'connections', 'settings' );
	$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
}

/**
 * Returns the current sub-tab slug.
 *
 * @param array  $allowed Allowed sub-tab slugs for this primary tab.
 * @param string $default Default sub-tab slug.
 * @return string
 */
function abcc_get_current_subtab( $allowed, $default ) {
	$subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $subtab, $allowed, true ) ? $subtab : $default;
}

/**
 * Renders the WooCommerce-style sub-tab navigation row.
 *
 * @param string $primary_tab The current primary tab slug.
 * @param array  $subtabs     Array of [ 'slug' => 'Label' ].
 * @param string $current     The active sub-tab slug.
 * @return void
 */
function abcc_render_subtab_nav( $primary_tab, $subtabs, $current ) {
	$page = 'automated-blog-content-creator-post';
	echo '<ul class="abcc-subtab-nav">';
	$items = array();
	foreach ( $subtabs as $slug => $label ) {
		$url     = esc_url(
			add_query_arg(
				array(
					'page'   => $page,
					'tab'    => $primary_tab,
					'subtab' => $slug,
				)
			)
		);
		$class   = $slug === $current ? ' class="current"' : '';
		$items[] = '<li><a href="' . $url . '"' . $class . '>' . esc_html( $label ) . '</a></li>';
	}
	echo implode( ' <li class="abcc-subtab-sep">|</li> ', $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</ul>';
}

/**
 * Displays a single-select category dropdown.
 *
 * @param int    $selected_category The selected category ID.
 * @param string $name              The name attribute for the select field.
 * @return void
 */
function abcc_category_dropdown_single( $selected_category = 0, $name = 'abcc_category' ) {
	$categories = get_categories( array( 'hide_empty' => 0 ) );
	echo '<select name="' . esc_attr( $name ) . '" class="abcc-category-select">';
	echo '<option value="0">' . esc_html__( 'Default', 'automated-blog-content-creator' ) . '</option>';
	foreach ( $categories as $category ) {
		$selected = selected( $selected_category, $category->term_id, false );
		echo '<option value="' . esc_attr( $category->term_id ) . '"' . wp_kses_post( $selected ) . '>' . esc_html( $category->name ) . '</option>';
	}
	echo '</select>';
}

/**
 * Displays and handles settings for the blog post generator.
 *
 * @since 1.0.0
 * @return void
 */
function abcc_openai_text_settings_page() {

	// Check if this is a new user who needs onboarding
	if ( ! get_option( 'abcc_onboarding_completed', false ) && ! abcc_has_any_api_key() ) {
		abcc_show_onboarding_page();
		return;
	}

	// Handle settings export (GET request with nonce).
	if ( isset( $_GET['abcc_export_settings'] ) && current_user_can( 'manage_options' ) ) {
		$nonce_value = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( wp_verify_nonce( $nonce_value, 'abcc_export_settings' ) ) {
			$schema   = abcc_get_settings_schema();
			$exported = array();
			foreach ( $schema['settings'] as $key => $def ) {
				$exported[ $key ] = abcc_get_setting( $key, $def['default'] );
			}
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="wp-autoinsight-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
			echo wp_json_encode( $exported, JSON_PRETTY_PRINT );
			exit;
		}
	}

	if ( isset( $_POST['abcc_openai_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['abcc_openai_nonce'] ), 'abcc_openai_generate_post' ) ) {
		$current_tab = abcc_get_current_tab();

		switch ( $current_tab ) {
			case 'content':
				// Handle Keyword Groups.
				$keyword_groups = array();
				if ( isset( $_POST['abcc_group_name'] ) && is_array( $_POST['abcc_group_name'] ) ) {
					foreach ( wp_unslash( $_POST['abcc_group_name'] ) as $index => $name ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						$keywords_raw     = isset( $_POST['abcc_group_keywords'][ $index ] ) ? sanitize_textarea_field( wp_unslash( $_POST['abcc_group_keywords'][ $index ] ) ) : '';
						$keywords_array   = array_filter( array_map( 'trim', explode( "\n", $keywords_raw ) ) );
						$keyword_groups[] = array(
							'name'     => sanitize_text_field( wp_unslash( $name ) ),
							'keywords' => $keywords_array,
							'category' => isset( $_POST['abcc_group_category'][ $index ] ) ? absint( $_POST['abcc_group_category'][ $index ] ) : 0,
							'template' => isset( $_POST['abcc_group_template'][ $index ] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_group_template'][ $index ] ) ) : 'default',
						);
					}
				}
				update_option( 'abcc_keyword_groups', $keyword_groups );

				// Handle Content Templates.
				$content_templates = array();
				if ( isset( $_POST['abcc_template_slug'] ) && is_array( $_POST['abcc_template_slug'] ) ) {
					foreach ( wp_unslash( $_POST['abcc_template_slug'] ) as $index => $slug ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
						$template_slug = sanitize_key( wp_unslash( $slug ) );
						// Default template is read-only — never overwrite it from POST data.
						if ( empty( $template_slug ) || 'default' === $template_slug ) {
							continue;
						}
						$content_templates[ $template_slug ] = array(
							'name'   => isset( $_POST['abcc_template_name'][ $index ] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_template_name'][ $index ] ) ) : '',
							'prompt' => isset( $_POST['abcc_template_prompt'][ $index ] ) ? sanitize_textarea_field( wp_unslash( $_POST['abcc_template_prompt'][ $index ] ) ) : '',
						);
					}
				}
				// Ensure default template always exists.
				if ( ! isset( $content_templates['default'] ) ) {
					$content_templates['default'] = abcc_get_default_content_template();
				}
				abcc_update_setting( 'abcc_content_templates', $content_templates );

				if ( isset( $_POST['openai_tone'] ) ) {
					$openai_tone = sanitize_text_field( wp_unslash( $_POST['openai_tone'] ) );
					if ( 'custom' === $openai_tone ) {
						$custom_tone = isset( $_POST['custom_tone'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_tone'] ) ) : '';
						abcc_update_setting( 'custom_tone', $custom_tone );
					} else {
						abcc_update_setting( 'custom_tone', '' );
					}
					abcc_update_setting( 'openai_tone', $openai_tone );
				}
				break;

			case 'connections':
				$subtab = isset( $_POST['abcc_subtab'] ) ? sanitize_key( wp_unslash( $_POST['abcc_subtab'] ) ) : 'api-keys';
				if ( 'api-keys' === $subtab ) {
					// Save API keys for all providers using the registry.
					foreach ( abcc_get_provider_ids() as $provider_id ) {
						$key_field = $provider_id . '_api_key';
						if ( isset( $_POST[ $key_field ] ) ) {
							$api_key = sanitize_text_field( wp_unslash( $_POST[ $key_field ] ) );
							abcc_set_provider_saved_api_key( $provider_id, $api_key );
						}
					}

					// Save selected model.
					$selected_model = isset( $_POST['selected_model'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_model'] ) ) : '';
					if ( ! empty( $selected_model ) ) {
						abcc_update_setting( 'prompt_select', $selected_model );
						abcc_validate_selected_model();
					}

					// Perplexity options (also handled via auto-save, but accept form fallback).
					if ( isset( $_POST['abcc_perplexity_citation_style'] ) ) {
						abcc_update_setting( 'abcc_perplexity_citation_style', sanitize_text_field( wp_unslash( $_POST['abcc_perplexity_citation_style'] ) ) );
					}
					if ( isset( $_POST['abcc_perplexity_recency_filter'] ) ) {
						abcc_update_setting( 'abcc_perplexity_recency_filter', sanitize_text_field( wp_unslash( $_POST['abcc_perplexity_recency_filter'] ) ) );
					}

					add_action( 'admin_footer', 'abcc_trigger_inline_api_validation' );
				} elseif ( 'scheduling' === $subtab ) {
					$auto_create  = isset( $_POST['openai_auto_create'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_auto_create'] ) ) : '';
					$email_notifs = isset( $_POST['openai_email_notifications'] );

					abcc_update_setting( 'openai_auto_create', $auto_create );
					abcc_update_setting( 'openai_email_notifications', $email_notifs );
					abcc_schedule_openai_event();
				}
				break;

			case 'media':
				$subtab = isset( $_POST['abcc_subtab'] ) ? sanitize_key( wp_unslash( $_POST['abcc_subtab'] ) ) : 'images';
				if ( 'images' === $subtab ) {
					$openai_generate_images  = isset( $_POST['openai_generate_images'] );
					$preferred_image_service = isset( $_POST['preferred_image_service'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_image_service'] ) ) : 'auto';
					$gemini_image_model      = isset( $_POST['abcc_gemini_image_model'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_gemini_image_model'] ) ) : 'gemini-2.5-flash-image';
					$gemini_image_size       = isset( $_POST['abcc_gemini_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_gemini_image_size'] ) ) : '2K';
					$openai_image_size       = isset( $_POST['abcc_openai_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_openai_image_size'] ) ) : '1024x1024';
					$openai_image_quality    = isset( $_POST['abcc_openai_image_quality'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_openai_image_quality'] ) ) : 'standard';
					$stability_image_size    = isset( $_POST['abcc_stability_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_stability_image_size'] ) ) : '1024x1024';
					$auto_alt_text           = isset( $_POST['abcc_auto_alt_text'] );

					abcc_update_setting( 'openai_generate_images', $openai_generate_images );
					abcc_update_setting( 'preferred_image_service', $preferred_image_service );
					abcc_update_setting( 'abcc_gemini_image_model', $gemini_image_model );
					abcc_update_setting( 'abcc_gemini_image_size', $gemini_image_size );
					abcc_update_setting( 'abcc_openai_image_size', $openai_image_size );
					abcc_update_setting( 'abcc_openai_image_quality', $openai_image_quality );
					abcc_update_setting( 'abcc_stability_image_size', $stability_image_size );
					abcc_update_setting( 'abcc_auto_alt_text', $auto_alt_text );
				} elseif ( 'audio' === $subtab ) {
					$enable_audio           = isset( $_POST['abcc_enable_audio_transcription'] );
					$supported_formats      = isset( $_POST['abcc_supported_audio_formats'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_supported_audio_formats'] ) ) : array();
					$transcription_language = isset( $_POST['abcc_transcription_language'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_transcription_language'] ) ) : 'en';

					abcc_update_setting( 'abcc_enable_audio_transcription', $enable_audio );
					abcc_update_setting( 'abcc_supported_audio_formats', $supported_formats );
					abcc_update_setting( 'abcc_transcription_language', $transcription_language );
				} elseif ( 'infographics' === $subtab ) {
					$enable_infographics  = isset( $_POST['abcc_enable_infographics'] );
					$infographic_provider = isset( $_POST['abcc_infographic_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_infographic_provider'] ) ) : 'auto';

					abcc_update_setting( 'abcc_enable_infographics', $enable_infographics );
					abcc_update_setting( 'abcc_infographic_provider', $infographic_provider );
				}
				break;

			case 'settings':
				$subtab = isset( $_POST['abcc_subtab'] ) ? sanitize_key( wp_unslash( $_POST['abcc_subtab'] ) ) : 'general';

				if ( 'general' === $subtab ) {
					$selected_post_types = isset( $_POST['abcc_selected_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_selected_post_types'] ) ) : array( 'post' );
					$char_limit          = isset( $_POST['openai_char_limit'] ) ? absint( $_POST['openai_char_limit'] ) : 200;
					$draft_first         = isset( $_POST['abcc_draft_first'] );
					$generate_seo        = isset( $_POST['openai_generate_seo'] );

					abcc_update_setting( 'abcc_selected_post_types', $selected_post_types );
					abcc_update_setting( 'openai_char_limit', $char_limit );
					abcc_update_setting( 'abcc_draft_first', $draft_first );
					abcc_update_setting( 'openai_generate_seo', $generate_seo );

				} elseif ( 'permissions' === $subtab ) {
					$allowed_roles   = isset( $_POST['abcc_allowed_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_allowed_roles'] ) ) : array();
					$allowed_roles[] = 'administrator';
					abcc_update_setting( 'abcc_allowed_roles', array_unique( $allowed_roles ) );

				} elseif ( 'advanced' === $subtab ) {
					if ( isset( $_POST['abcc_action'] ) ) {
						$action = sanitize_key( wp_unslash( $_POST['abcc_action'] ) );
						if ( 'reset_settings' === $action ) {
							$schema = abcc_get_settings_schema();
							foreach ( $schema['settings'] as $key => $def ) {
								abcc_update_setting( $key, $def['default'] );
							}
						} elseif ( 'delete_history' === $action ) {
							$jobs = get_posts(
								array(
									'post_type'      => ABCC_Job::POST_TYPE,
									'posts_per_page' => -1,
									'fields'         => 'ids',
								)
							);
							foreach ( $jobs as $id ) {
								wp_delete_post( $id, true );
							}
						}
					}
				}
				break;
		}

		// Add success message for all tabs.
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'automated-blog-content-creator' ) . '</p></div>';
			}
		);
	}

	$schedule_info     = abcc_get_openai_event_schedule();
	$tone              = abcc_get_setting( 'openai_tone', '' );
	$custom_tone_value = abcc_get_setting( 'custom_tone', '' );
	$keyword_groups    = abcc_get_setting( 'abcc_keyword_groups', array() );
	$content_templates = abcc_get_setting( 'abcc_content_templates', array() );
	$current_tab       = abcc_get_current_tab();

	// Add admin styles.
	wp_enqueue_style( 'wpai-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), ABCC_VERSION );
	// abcc-ui-script is already registered globally by ABCC_Plugin::enqueue_scripts().
	wp_enqueue_script( 'wpai-admin-scripts', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery', 'abcc-ui-script' ), ABCC_VERSION, true );
	wp_localize_script(
		'wpai-admin-scripts',
		'abccAdmin',
		array(
			'nonce'       => wp_create_nonce( 'abcc_openai_generate_post' ),
			'buttonNonce' => wp_create_nonce( 'abcc_admin_buttons' ),
			'adminUrl'    => admin_url( 'post.php?' ),
			'i18n'        => array(
				/* translators: %d: number of posts to generate */
				'generateNPosts' => __( 'Generate %d Posts', 'automated-blog-content-creator' ),
				'copied'         => __( 'Copied', 'automated-blog-content-creator' ),
				/* translators: shown in auto-save indicator while saving */
				'saving'         => __( 'Saving\u2026', 'automated-blog-content-creator' ),
				/* translators: shown in auto-save indicator after successful save */
				'saved'          => __( 'Saved ✓', 'automated-blog-content-creator' ),
				/* translators: shown in auto-save indicator when saving fails */
				'saveFailed'     => __( 'Save failed ✗', 'automated-blog-content-creator' ),
				'queued'         => __( 'Queued', 'automated-blog-content-creator' ),
				/* translators: shown while a bulk post is being generated */
				'generating'     => __( 'Generating…', 'automated-blog-content-creator' ),
				'done'           => __( 'Done', 'automated-blog-content-creator' ),
				'failed'         => __( 'Failed', 'automated-blog-content-creator' ),
				'viewPost'       => __( 'View post', 'automated-blog-content-creator' ),
			),
		)
	);

	$page_slug = 'automated-blog-content-creator-post';
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div id="abcc-autosave-indicator" class="abcc-autosave-indicator" aria-live="polite"></div>

		<nav class="nav-tab-wrapper">
			<?php
			$primary_tabs = array(
				'dashboard'   => __( 'Dashboard', 'automated-blog-content-creator' ),
				'content'     => __( 'Content', 'automated-blog-content-creator' ),
				'media'       => __( 'Media', 'automated-blog-content-creator' ),
				'connections' => __( 'Connections', 'automated-blog-content-creator' ),
				'settings'    => __( 'Settings', 'automated-blog-content-creator' ),
			);
			foreach ( $primary_tabs as $slug => $label ) :
				$url   = esc_url(
					add_query_arg(
						array(
							'page' => $page_slug,
							'tab'  => $slug,
						)
					)
				);
				$class = $current_tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
				printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
			endforeach;
			?>
		</nav>

		<div class="tab-content">
			<?php
			if ( 'dashboard' === $current_tab ) {
				include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-dashboard.php';
			} elseif ( 'content' === $current_tab ) {
				include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-content.php';
			} elseif ( 'media' === $current_tab ) {
				include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-media.php';
			} elseif ( 'connections' === $current_tab ) {
				include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-connections.php';
			} elseif ( 'settings' === $current_tab ) {
				include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-settings.php';
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Ensure the selected AI model is valid based on available API keys.
 * If the current model is no longer available, select a default from available options.
 */
function abcc_validate_selected_model() {
	$current_model    = abcc_get_setting( 'prompt_select', '' );
	$available_models = abcc_get_ai_model_options();

	// No API keys configured, nothing to validate.
	if ( empty( $available_models ) ) {
		return;
	}

	// No model set yet (fresh install) — silently assign the first available, no notice.
	if ( empty( $current_model ) ) {
		$first_provider = reset( $available_models );
		$first_model    = key( $first_provider['options'] );
		abcc_update_setting( 'prompt_select', $first_model );
		return;
	}

	// Verify the stored model still exists in available options.
	$model_available = false;
	foreach ( $available_models as $group ) {
		if ( isset( $group['options'][ $current_model ] ) ) {
			$model_available = true;
			break;
		}
	}

	if ( ! $model_available ) {
		$first_provider = reset( $available_models );
		$first_model    = key( $first_provider['options'] );
		abcc_update_setting( 'prompt_select', $first_model );
		add_action( 'admin_notices', 'abcc_model_changed_notice' );
	}
}

/**
 * Display admin notice when the model has been automatically changed.
 */
function abcc_model_changed_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( 'The previously selected AI model is no longer available. A default model has been selected based on your available API keys.', 'automated-blog-content-creator' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_init', 'abcc_validate_selected_model' );
