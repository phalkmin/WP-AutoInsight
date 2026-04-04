<?php
/**
 * File: admin.php
 *
 * This file contains functions related to the administration settings
 * of the WP-AutoInsight plugin, including menu pages and options.
 *
 * @package WP-AutoInsight
 */

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

	if ( isset( $_POST['abcc_openai_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['abcc_openai_nonce'] ), 'abcc_openai_generate_post' ) ) {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'text-settings';

		switch ( $current_tab ) {
			case 'text-settings':
				// Handle Keyword Groups.
				$keyword_groups = array();
				if ( isset( $_POST['abcc_group_name'] ) && is_array( $_POST['abcc_group_name'] ) ) {
					foreach ( $_POST['abcc_group_name'] as $index => $name ) {
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
					foreach ( $_POST['abcc_template_slug'] as $index => $slug ) {
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

				$selected_post_types = isset( $_POST['abcc_selected_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_selected_post_types'] ) ) : array( 'post' );

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

				$openai_generate_seo = isset( $_POST['openai_generate_seo'] );
				$abcc_draft_first    = isset( $_POST['abcc_draft_first'] );
				abcc_update_setting( 'openai_generate_seo', $openai_generate_seo );
				abcc_update_setting( 'abcc_draft_first', $abcc_draft_first );
				abcc_update_setting( 'abcc_selected_post_types', $selected_post_types );
				break;

			case 'model-settings':
				$selected_model = isset( $_POST['selected_model'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_model'] ) ) : '';
				if ( ! empty( $selected_model ) ) {
					abcc_update_setting( 'prompt_select', $selected_model );
					abcc_validate_selected_model();
				}
				break;

			case 'advanced-settings':
				$api_key                    = isset( $_POST['openai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_api_key'] ) ) : '';
				$gemini_api_key             = isset( $_POST['gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) : '';
				$claude_api_key             = isset( $_POST['claude_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['claude_api_key'] ) ) : '';
				$stability_api_key          = isset( $_POST['stability_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stability_api_key'] ) ) : '';
				$perplexity_api_key         = isset( $_POST['perplexity_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['perplexity_api_key'] ) ) : '';
				$perplexity_citation_style  = isset( $_POST['abcc_perplexity_citation_style'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_perplexity_citation_style'] ) ) : 'inline';
				$perplexity_recency_filter  = isset( $_POST['abcc_perplexity_recency_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_perplexity_recency_filter'] ) ) : '';
				$auto_create                = isset( $_POST['openai_auto_create'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_auto_create'] ) ) : '';
				$char_limit                 = isset( $_POST['openai_char_limit'] ) ? absint( $_POST['openai_char_limit'] ) : 200;
				$openai_email_notifications = isset( $_POST['openai_email_notifications'] );
				$openai_generate_images     = isset( $_POST['openai_generate_images'] );
				$preferred_image_service    = isset( $_POST['preferred_image_service'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_image_service'] ) ) : 'auto';
				$gemini_image_model         = isset( $_POST['abcc_gemini_image_model'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_gemini_image_model'] ) ) : 'gemini-2.5-flash-image';
				$gemini_image_size          = isset( $_POST['abcc_gemini_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_gemini_image_size'] ) ) : '2K';
				$openai_image_size          = isset( $_POST['abcc_openai_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_openai_image_size'] ) ) : '1024x1024';
				$openai_image_quality       = isset( $_POST['abcc_openai_image_quality'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_openai_image_quality'] ) ) : 'standard';
				$stability_image_size       = isset( $_POST['abcc_stability_image_size'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_stability_image_size'] ) ) : '1024x1024';

				abcc_update_setting( 'openai_api_key', $api_key );
				abcc_update_setting( 'gemini_api_key', $gemini_api_key );
				abcc_update_setting( 'claude_api_key', $claude_api_key );
				abcc_update_setting( 'stability_api_key', $stability_api_key );
				abcc_update_setting( 'perplexity_api_key', $perplexity_api_key );
				abcc_update_setting( 'abcc_perplexity_citation_style', $perplexity_citation_style );
				abcc_update_setting( 'abcc_perplexity_recency_filter', $perplexity_recency_filter );
				abcc_update_setting( 'openai_auto_create', $auto_create );
				abcc_update_setting( 'openai_char_limit', $char_limit );
				abcc_update_setting( 'openai_email_notifications', $openai_email_notifications );
				abcc_update_setting( 'openai_generate_images', $openai_generate_images );
				abcc_update_setting( 'preferred_image_service', $preferred_image_service );
				abcc_update_setting( 'abcc_gemini_image_model', $gemini_image_model );
				abcc_update_setting( 'abcc_gemini_image_size', $gemini_image_size );
				abcc_update_setting( 'abcc_openai_image_size', $openai_image_size );
				abcc_update_setting( 'abcc_openai_image_quality', $openai_image_quality );
				abcc_update_setting( 'abcc_stability_image_size', $stability_image_size );

				abcc_schedule_openai_event();

				// Trigger inline validation on save.
				add_action( 'admin_footer', 'abcc_trigger_inline_api_validation' );

				break;

			case 'audio-settings':
				$enable_audio           = isset( $_POST['abcc_enable_audio_transcription'] );
				$supported_formats      = isset( $_POST['abcc_supported_audio_formats'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_supported_audio_formats'] ) ) : array();
				$transcription_language = isset( $_POST['abcc_transcription_language'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_transcription_language'] ) ) : 'en';

				abcc_update_setting( 'abcc_enable_audio_transcription', $enable_audio );
				abcc_update_setting( 'abcc_supported_audio_formats', $supported_formats );
				abcc_update_setting( 'abcc_transcription_language', $transcription_language );
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
	$current_tab       = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'text-settings';

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
			'i18n'        => array(
				/* translators: %d: number of posts to generate */
				'generateNPosts' => __( 'Generate %d Posts', 'automated-blog-content-creator' ),
				'copied'         => __( 'Copied', 'automated-blog-content-creator' ),
			),
		)
	);

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<nav class="nav-tab-wrapper">
			<a href="?page=automated-blog-content-creator-post&tab=text-settings" class="nav-tab <?php echo $current_tab === 'text-settings' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Content Settings', 'automated-blog-content-creator' ); ?>
			</a>
			<a href="?page=automated-blog-content-creator-post&tab=advanced-settings" class="nav-tab <?php echo $current_tab === 'advanced-settings' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Advanced Settings', 'automated-blog-content-creator' ); ?>
			</a>
			<a href="?page=automated-blog-content-creator-post&tab=model-settings" class="nav-tab <?php echo $current_tab === 'model-settings' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'AI Models', 'automated-blog-content-creator' ); ?>
			</a>
			<a href="?page=automated-blog-content-creator-post&tab=audio-settings" class="nav-tab <?php echo $current_tab === 'audio-settings' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Audio Transcription', 'automated-blog-content-creator' ); ?>
			</a>
			<a href="?page=automated-blog-content-creator-post&tab=about" class="nav-tab <?php echo $current_tab === 'about' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'About', 'automated-blog-content-creator' ); ?>
			</a>
		</nav>

		<div class="tab-content">
			<?php if ( $current_tab === 'text-settings' ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-content.php'; ?>
			<?php elseif ( $current_tab === 'model-settings' ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-models.php'; ?>
			<?php elseif ( $current_tab === 'advanced-settings' ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-advanced.php'; ?>
			<?php elseif ( $current_tab === 'about' ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-about.php'; ?>
			<?php elseif ( $current_tab === 'audio-settings' ) : ?>
				<?php include plugin_dir_path( __FILE__ ) . 'includes/admin/tab-audio.php'; ?>
			<?php endif; ?>

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
