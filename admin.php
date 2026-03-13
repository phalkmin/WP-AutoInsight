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
	$options = array();

	// Check for OpenAI API key.
	$openai_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	// Check for Claude API key.
	$claude_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
	// Check for Gemini API key.
	$gemini_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
	// Check for Perplexity API key.
	$perplexity_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : get_option( 'perplexity_api_key', '' );

	if ( ! empty( $openai_key ) ) {
		$options['openai'] = array(
			'group'   => 'OpenAI Models',
			'options' => array(
				// Economy option.
				'gpt-4.1-mini-2025-04-14' => array(
					'name'          => 'GPT-4.1 Mini',
					'description'   => 'Fast and affordable with 1M context window',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0002,
				),
				// Standard option.
				'gpt-4.1-2025-04-14'      => array(
					'name'          => 'GPT-4.1',
					'description'   => 'Excellent coding and instruction following',
					'cost_tier'     => '2',
					'cost_per_post' => 0.003,
				),
				// Premium option.
				'o4-mini-2025-04-16'      => array(
					'name'          => 'o4-mini',
					'description'   => 'Advanced reasoning model for complex tasks',
					'cost_tier'     => '3',
					'cost_per_post' => 0.0004,
				),
			),
		);
	}

	if ( ! empty( $claude_key ) ) {
		$options['claude'] = array(
			'group'   => 'Claude Models',
			'options' => array(
				// Economy option.
				'claude-haiku-4-5-20251001'  => array(
					'name'          => 'Claude Haiku 4.5',
					'description'   => 'Fastest model with near-frontier intelligence',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0003,
				),
				// Standard option.
				'claude-sonnet-4-5-20250929' => array(
					'name'          => 'Claude Sonnet 4.5',
					'description'   => 'Best for complex agents and coding tasks',
					'cost_tier'     => '2',
					'cost_per_post' => 0.004,
				),
				// Premium option.
				'claude-opus-4-5-20251101'   => array(
					'name'          => 'Claude Opus 4.5',
					'description'   => 'Maximum intelligence with practical performance',
					'cost_tier'     => '3',
					'cost_per_post' => 0.015,
				),
			),
		);
	}

	if ( ! empty( $gemini_key ) ) {
		$options['gemini'] = array(
			'group'   => 'Google Gemini Models',
			'options' => array(
				// Economy option.
				'gemini-2.5-flash-lite' => array(
					'name'          => 'Gemini 2.5 Flash-Lite',
					'description'   => 'Fastest and most budget-friendly model',
					'cost_tier'     => '1',
					'cost_per_post' => 0.0001,
				),
				// Standard option.
				'gemini-2.5-flash'      => array(
					'name'          => 'Gemini 2.5 Flash',
					'description'   => 'Best price-performance with thinking capabilities',
					'cost_tier'     => '2',
					'cost_per_post' => 0.0002,
				),
				// Premium option.
				'gemini-2.5-pro'        => array(
					'name'          => 'Gemini 2.5 Pro',
					'description'   => 'Most advanced reasoning model for complex problems',
					'cost_tier'     => '3',
					'cost_per_post' => 0.002,
				),
			),
		);
	}

	if ( ! empty( $perplexity_key ) ) {
		$options['perplexity'] = array(
			'group'   => 'Perplexity Models',
			'options' => array(
				'sonar'               => array(
					'name'          => 'Sonar',
					'description'   => 'Fast web-grounded search with citations',
					'cost_tier'     => '1',
					'cost_per_post' => 0.001,
				),
				'sonar-pro'           => array(
					'name'          => 'Sonar Pro',
					'description'   => 'Deeper context with 2x more search results',
					'cost_tier'     => '2',
					'cost_per_post' => 0.005,
				),
				'sonar-reasoning-pro' => array(
					'name'          => 'Sonar Reasoning Pro',
					'description'   => 'Advanced multi-step reasoning with citations',
					'cost_tier'     => '3',
					'cost_per_post' => 0.01,
				),
			),
		);
	}

	// Apply filter for advanced users to override estimates.
	return apply_filters( 'abcc_model_cost_estimate', $options );
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
 * Displays a dropdown of categories in the WordPress admin.
 *
 * @since 1.0.0
 * @param array $selected_categories Array of category IDs that should be selected by default.
 * @return void
 */
function abcc_category_dropdown( $selected_categories = array() ) {
	$categories = get_categories( array( 'hide_empty' => 0 ) );
	echo '<select id="openai_selected_categories" class="wpai-category-select" name="openai_selected_categories[]" multiple style="width:100%;">';
	foreach ( $categories as $category ) {
		$selected = in_array( $category->term_id, $selected_categories, true ) ? ' selected="selected"' : '';
		echo '<option value="' . esc_attr( $category->term_id ) . '"' . esc_attr( $selected ) . '>' . esc_html( $category->name ) . '</option>';
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
				$keywords            = isset( $_POST['openai_keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['openai_keywords'] ) ) : '';
				$selected_categories = isset( $_POST['openai_selected_categories'] ) ? array_map( 'intval', $_POST['openai_selected_categories'] ) : array();
				$selected_post_types = isset( $_POST['abcc_selected_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_selected_post_types'] ) ) : array( 'post' );

				if ( isset( $_POST['openai_tone'] ) ) {
					$openai_tone = sanitize_text_field( wp_unslash( $_POST['openai_tone'] ) );
					if ( 'custom' === $openai_tone ) {
						$custom_tone = isset( $_POST['custom_tone'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_tone'] ) ) : '';
						update_option( 'custom_tone', $custom_tone );
					} else {
						update_option( 'custom_tone', '' );
					}
					update_option( 'openai_tone', $openai_tone );
				}

				$openai_generate_seo = isset( $_POST['openai_generate_seo'] );
				$abcc_draft_first    = isset( $_POST['abcc_draft_first'] );
				update_option( 'openai_generate_seo', $openai_generate_seo );
				update_option( 'abcc_draft_first', $abcc_draft_first );
				update_option( 'openai_keywords', $keywords );
				update_option( 'openai_selected_categories', $selected_categories );
				update_option( 'abcc_selected_post_types', $selected_post_types );
				break;

			case 'model-settings':
				$selected_model = isset( $_POST['selected_model'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_model'] ) ) : '';
				if ( ! empty( $selected_model ) ) {
					update_option( 'prompt_select', $selected_model );
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

				update_option( 'openai_api_key', $api_key );
				update_option( 'gemini_api_key', $gemini_api_key );
				update_option( 'claude_api_key', $claude_api_key );
				update_option( 'stability_api_key', $stability_api_key );
				update_option( 'perplexity_api_key', $perplexity_api_key );
				update_option( 'abcc_perplexity_citation_style', $perplexity_citation_style );
				update_option( 'abcc_perplexity_recency_filter', $perplexity_recency_filter );
				update_option( 'openai_auto_create', $auto_create );
				update_option( 'openai_char_limit', $char_limit );
				update_option( 'openai_email_notifications', $openai_email_notifications );
				update_option( 'openai_generate_images', $openai_generate_images );
				update_option( 'preferred_image_service', $preferred_image_service );
				update_option( 'abcc_gemini_image_model', $gemini_image_model );
				update_option( 'abcc_gemini_image_size', $gemini_image_size );
				update_option( 'abcc_openai_image_size', $openai_image_size );
				update_option( 'abcc_openai_image_quality', $openai_image_quality );
				update_option( 'abcc_stability_image_size', $stability_image_size );

				abcc_schedule_openai_event();

				// Trigger inline validation on save.
				add_action( 'admin_footer', 'abcc_trigger_inline_api_validation' );

				break;

			case 'audio-settings':
				$enable_audio           = isset( $_POST['abcc_enable_audio_transcription'] );
				$supported_formats      = isset( $_POST['abcc_supported_audio_formats'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['abcc_supported_audio_formats'] ) ) : array();
				$transcription_language = isset( $_POST['abcc_transcription_language'] ) ? sanitize_text_field( wp_unslash( $_POST['abcc_transcription_language'] ) ) : 'en';

				update_option( 'abcc_enable_audio_transcription', $enable_audio );
				update_option( 'abcc_supported_audio_formats', $supported_formats );
				update_option( 'abcc_transcription_language', $transcription_language );
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

	$selected_categories = get_option( 'openai_selected_categories', array() );
	$keywords            = get_option( 'openai_keywords', '' );
	$schedule_info       = get_openai_event_schedule();
	$tone                = get_option( 'openai_tone', '' );
	$custom_tone_value   = get_option( 'custom_tone', '' );
	$current_tab         = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'text-settings';

	// Add admin styles.
	wp_enqueue_style( 'wpai-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), '3.3.0' );
	wp_enqueue_script( 'abcc-ui-script', plugins_url( 'js/abcc-ui.js', __FILE__ ), array( 'jquery' ), '3.3.0', true );
	wp_enqueue_script( 'wpai-admin-scripts', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery', 'abcc-ui-script' ), '3.3.0', true );

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
				<div class="tab-pane active">
					<?php if ( $schedule_info ) : ?>
						<div class="notice notice-info">
							<?php
							$time_diff = human_time_diff( time(), $schedule_info['timestamp'] );
							printf(
								// Translators: %1$s is the human-readable time difference, %2$s is the next run date.
								'<p>' . esc_html__( 'Next post scheduled in %1$s — %2$s. %3$s', 'automated-blog-content-creator' ) . '</p>',
								'<strong>' . esc_html( $time_diff ) . '</strong>',
								'<strong>' . esc_html( $schedule_info['next_run'] ) . '</strong>',
								'<a href="?page=automated-blog-content-creator-post&tab=advanced-settings#scheduling-settings">' . esc_html__( 'Change schedule →', 'automated-blog-content-creator' ) . '</a>'
							);
							?>
						</div>
					<?php else : ?>
						<div class="notice notice-info">
							<p><?php esc_html_e( 'There are no scheduled posts to be published.', 'automated-blog-content-creator' ); ?></p>
						</div>
					<?php endif; ?>

					<form method="post" action="">
						<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Keywords', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'One topic per line. Each line generates one blog post.', 'automated-blog-content-creator' ) ) ); ?></th>
								<td>
									<textarea name="openai_keywords" rows="5" class="large-text"><?php echo esc_textarea( $keywords ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Enter one keyword or topic per line. The AI will generate content based on these topics.', 'automated-blog-content-creator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Categories', 'automated-blog-content-creator' ); ?></th>
								<td>
									<?php abcc_category_dropdown( $selected_categories ); ?>
									<p class="description"><?php esc_html_e( 'Select categories for the generated posts', 'automated-blog-content-creator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Target Post Types', 'automated-blog-content-creator' ); ?></th>
								<td>
									<?php
									$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );
									$post_types          = get_post_types( array( 'public' => true ), 'objects' );
									echo '<select id="abcc_selected_post_types" class="wpai-post-type-select" name="abcc_selected_post_types[]" multiple style="width:100%;">';
									foreach ( $post_types as $post_type ) {
										$selected = in_array( $post_type->name, $selected_post_types, true ) ? ' selected="selected"' : '';
										echo '<option value="' . esc_attr( $post_type->name ) . '"' . esc_attr( $selected ) . '>' . esc_html( $post_type->label ) . '</option>';
									}
									echo '</select>';
									?>
									<p class="description"><?php esc_html_e( 'Select which post types should have the "Create Post" button', 'automated-blog-content-creator' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Tone', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Controls the writing style of your generated content.', 'automated-blog-content-creator' ) ) ); ?></th>
								<td>
									<select name="openai_tone" id="openai_tone">
										<option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional & formal', 'automated-blog-content-creator' ); ?></option>
										<option value="casual" <?php selected( $tone, 'casual' ); ?>><?php esc_html_e( 'Conversational & relaxed', 'automated-blog-content-creator' ); ?></option>
										<option value="friendly" <?php selected( $tone, 'friendly' ); ?>><?php esc_html_e( 'Warm & approachable', 'automated-blog-content-creator' ); ?></option>
										<option value="custom" <?php selected( $tone, 'custom' ); ?>><?php esc_html_e( 'Custom (define your own)', 'automated-blog-content-creator' ); ?></option>
									</select>
									<div id="custom_tone_container" style="display: <?php echo $tone === 'custom' ? 'block' : 'none'; ?>; margin-top: 10px;">
										<input type="text" name="custom_tone" value="<?php echo esc_attr( $custom_tone_value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom tone', 'automated-blog-content-creator' ); ?>">
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Workflow Settings', 'automated-blog-content-creator' ); ?></th>
								<td>
									<label for="abcc_draft_first">
										<input type="checkbox" name="abcc_draft_first" id="abcc_draft_first" value="1" <?php checked( get_option( 'abcc_draft_first', true ) ); ?>>
										<?php esc_html_e( 'Always save as draft for review before publishing', 'automated-blog-content-creator' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'When enabled, new posts will be created as drafts. Recommended for quality control.', 'automated-blog-content-creator' ); ?></p>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
						<div id="abcc-manual-generation-status" style="margin: 10px 0;"></div>
						<button type="button" name="generate-post" id="generate-post" class="button button-secondary">
							<?php echo esc_attr__( 'Create post manually', 'automated-blog-content-creator' ); ?>
						</button>
					</form>

					<hr style="margin: 40px 0 20px;">
					<h2><?php esc_html_e( 'Content History', 'automated-blog-content-creator' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post Title', 'automated-blog-content-creator' ); ?></th>
								<th><?php esc_html_e( 'Date Generated', 'automated-blog-content-creator' ); ?></th>
								<th><?php esc_html_e( 'Model Used', 'automated-blog-content-creator' ); ?></th>
								<th><?php esc_html_e( 'Status', 'automated-blog-content-creator' ); ?></th>
							</tr>
						</thead>
											<tbody>
												<?php
												$history_posts = get_posts(
													array(
														'post_type'      => 'any',
														'post_status'    => 'any',
														'posts_per_page' => 10,
														'meta_key'       => '_abcc_generated',
														'meta_value'     => '1',
														'orderby'        => 'date',
														'order'          => 'DESC',
													)
												);
												if ( $history_posts ) :
													foreach ( $history_posts as $h_post ) :
														$h_model = get_post_meta( $h_post->ID, '_abcc_model', true );
														?>
															<tr>
																<td>
																	<strong><a href="<?php echo esc_url( get_edit_post_link( $h_post->ID ) ); ?>"><?php echo esc_html( $h_post->post_title ); ?></a></strong>
																</td>
																<td><?php echo esc_html( get_the_date( '', $h_post ) ); ?></td>
																<td><code><?php echo esc_html( $h_model ? $h_model : 'n/a' ); ?></code></td>
																<td>
																				<?php
																				$status_obj = get_post_status_object( get_post_status( $h_post ) );
																				echo esc_html( $status_obj->label );
																				?>
																</td>
															</tr>
														<?php
														endforeach;
													else :
														?>
								<tr>
									<td colspan="4"><?php esc_html_e( 'No content history found.', 'automated-blog-content-creator' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=all&post_type=post&meta_key=_abcc_generated&meta_value=1' ) ); ?>">
							<?php esc_html_e( 'View all generated posts', 'automated-blog-content-creator' ); ?>
						</a>
					</p>
				</div>
				<?php elseif ( $current_tab === 'model-settings' ) : ?>
				<div class="tab-pane active">
					<form method="post" action="">
								<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
						<div class="model-selector">
								<?php
								$model_options          = abcc_get_ai_model_options();
								$current_selected_model = get_option( 'prompt_select', 'gpt-4.1-mini' );
								foreach ( $model_options as $provider => $provider_data ) :
									?>
								<div class="model-provider-section">
									<h3><?php echo esc_html( $provider_data['group'] ); ?></h3>
									<div class="model-cards">
										<?php foreach ( $provider_data['options'] as $model_id => $model_data ) : ?>
											<div class="model-card cost-tier-<?php echo esc_attr( $model_data['cost_tier'] ); ?>">
												<input type="radio" name="selected_model" id="model_<?php echo esc_attr( $model_id ); ?>" value="<?php echo esc_attr( $model_id ); ?>" <?php checked( $current_selected_model, $model_id ); ?>>
												<label for="model_<?php echo esc_attr( $model_id ); ?>">
													<h4><?php echo esc_html( $model_data['name'] ); ?></h4>
													<p><?php echo esc_html( $model_data['description'] ); ?></p>
													<div class="cost-indicator">
														<?php
														$cost_levels = array(
															'1' => 'Economy',
															'2' => 'Standard',
															'3' => 'Premium',
														);
														echo esc_html( $cost_levels[ $model_data['cost_tier'] ] );

														if ( ! empty( $model_data['cost_per_post'] ) ) {
															printf( ' &bull; ~$%s per post', esc_html( number_format( $model_data['cost_per_post'], 4 ) ) );
														}
														?>
													</div>
												</label>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
								<?php endforeach; ?>
						</div>
								<?php submit_button( __( 'Save Model Settings', 'automated-blog-content-creator' ) ); ?>
					</form>
				</div>
				<?php elseif ( $current_tab === 'advanced-settings' ) : ?>
	<div class="tab-pane active">
		<form method="post" action="">
					<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
			
			<h2><?php esc_html_e( 'API Configuration', 'automated-blog-content-creator' ); ?></h2>
			<table class="form-table">
					<?php if ( ! defined( 'OPENAI_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="openai_api_key">
							<?php echo esc_html__( 'OpenAI API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at platform.openai.com', 'automated-blog-content-creator' ) ) ); ?>
						</label></th>
						<td>
							<input type="password" id="openai_api_key" name="openai_api_key"
								value="<?php echo esc_attr( get_option( 'openai_api_key', '' ) ); ?>"
								class="regular-text">
							<span class="api-validation-status" data-provider="openai">
								<?php
								$last_v = get_transient( 'abcc_last_validation_openai' );
								if ( $last_v ) {
									echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] );
								}
								?>
							</span>
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'OPENAI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your OpenAI API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
						<span class="api-validation-status" data-provider="openai"></span>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'GEMINI_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="gemini_api_key">
							<?php echo esc_html__( 'Gemini API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at aistudio.google.com', 'automated-blog-content-creator' ) ) ); ?>
						</label></th>
						<td>
							<input type="password" id="gemini_api_key" name="gemini_api_key"
								value="<?php echo esc_attr( get_option( 'gemini_api_key', '' ) ); ?>"
								class="regular-text">
							<span class="api-validation-status" data-provider="gemini">
								<?php
								$last_v = get_transient( 'abcc_last_validation_gemini' );
								if ( $last_v ) {
									echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] );
								}
								?>
							</span>
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'GEMINI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Gemini API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
						<span class="api-validation-status" data-provider="gemini"></span>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'CLAUDE_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="claude_api_key">
							<?php echo esc_html__( 'Claude API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at console.anthropic.com', 'automated-blog-content-creator' ) ) ); ?>
						</label></th>
						<td>
							<input type="password" id="claude_api_key" name="claude_api_key"
								value="<?php echo esc_attr( get_option( 'claude_api_key', '' ) ); ?>"
								class="regular-text">
							<span class="api-validation-status" data-provider="claude">
								<?php
								$last_v = get_transient( 'abcc_last_validation_claude' );
								if ( $last_v ) {
									echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] );
								}
								?>
							</span>
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'CLAUDE_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Claude API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
						<span class="api-validation-status" data-provider="claude"></span>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'PERPLEXITY_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="perplexity_api_key">
							<?php echo esc_html__( 'Perplexity API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at perplexity.ai/settings/api', 'automated-blog-content-creator' ) ) ); ?>
						</label></th>
						<td>
							<input type="password" id="perplexity_api_key" name="perplexity_api_key"
								value="<?php echo esc_attr( get_option( 'perplexity_api_key', '' ) ); ?>"
								class="regular-text">
							<span class="api-validation-status" data-provider="perplexity">
								<?php
								$last_v = get_transient( 'abcc_last_validation_perplexity' );
								if ( $last_v ) {
									echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] );
								}
								?>
							</span>
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'PERPLEXITY_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
					<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Perplexity API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
						<span class="api-validation-status" data-provider="perplexity"></span>
					</th></tr>
					<?php endif; ?>

					<?php if ( ! defined( 'STABILITY_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="stability_api_key">
							<?php echo esc_html__( 'Stability AI API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at platform.stability.ai', 'automated-blog-content-creator' ) ) ); ?>
						</label></th>
						<td>
							<input type="password" id="stability_api_key" name="stability_api_key"
								value="<?php echo esc_attr( get_option( 'stability_api_key', '' ) ); ?>"
								class="regular-text">
							<span class="api-validation-status" data-provider="stability">
								<?php
								$last_v = get_transient( 'abcc_last_validation_stability' );
								if ( $last_v ) {
									echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] );
								}
								?>
							</span>
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'STABILITY_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Stability AI API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
						<span class="api-validation-status" data-provider="stability"></span>
					</th></tr>
				<?php endif; ?>
			</table>

			<h2><?php esc_html_e( 'Perplexity Settings', 'automated-blog-content-creator' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="abcc_perplexity_citation_style">
							<?php echo esc_html__( 'Citation Style:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<?php $citation_style = get_option( 'abcc_perplexity_citation_style', 'inline' ); ?>
						<select id="abcc_perplexity_citation_style" name="abcc_perplexity_citation_style">
							<option value="inline" <?php selected( $citation_style, 'inline' ); ?>>
								<?php esc_html_e( 'Inline hyperlinks', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="references" <?php selected( $citation_style, 'references' ); ?>>
								<?php esc_html_e( 'References section at bottom', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="both" <?php selected( $citation_style, 'both' ); ?>>
								<?php esc_html_e( 'Both inline + references section', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'How source citations from Perplexity appear in generated posts.', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_perplexity_recency_filter">
							<?php echo esc_html__( 'Source Recency Filter:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<?php $recency = get_option( 'abcc_perplexity_recency_filter', '' ); ?>
						<select id="abcc_perplexity_recency_filter" name="abcc_perplexity_recency_filter">
							<option value="" <?php selected( $recency, '' ); ?>>
								<?php esc_html_e( 'No filter (all time)', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="day" <?php selected( $recency, 'day' ); ?>>
								<?php esc_html_e( 'Last 24 hours', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="week" <?php selected( $recency, 'week' ); ?>>
								<?php esc_html_e( 'Last week', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="month" <?php selected( $recency, 'month' ); ?>>
								<?php esc_html_e( 'Last month', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="year" <?php selected( $recency, 'year' ); ?>>
								<?php esc_html_e( 'Last year', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Limit Perplexity sources to recent content only.', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Image Generation', 'automated-blog-content-creator' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="openai_generate_images">
							<?php echo esc_html__( 'Generate Featured Images:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" id="openai_generate_images" 
								name="openai_generate_images" 
								<?php checked( get_option( 'openai_generate_images', true ) ); ?>>
						<p class="description">
							<?php esc_html_e( 'Automatically generate featured images for posts using AI', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_openai_image_size">
							<?php echo esc_html__( 'OpenAI Image Size:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'DALL-E 3 supported resolutions.', 'automated-blog-content-creator' ) ) ); ?>
						</label>
					</th>
					<td>
						<?php $openai_size = get_option( 'abcc_openai_image_size', '1024x1024' ); ?>
						<select id="abcc_openai_image_size" name="abcc_openai_image_size">
							<option value="1024x1024" <?php selected( $openai_size, '1024x1024' ); ?>>1024 x 1024 (Square)</option>
							<option value="1792x1024" <?php selected( $openai_size, '1792x1024' ); ?>>1792 x 1024 (Wide)</option>
							<option value="1024x1792" <?php selected( $openai_size, '1024x1792' ); ?>>1024 x 1792 (Tall)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_openai_image_quality">
							<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'HD quality costs more but produces better detail.', 'automated-blog-content-creator' ) ) ); ?>
							<?php echo esc_html__( 'OpenAI Image Quality:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<?php $openai_quality = get_option( 'abcc_openai_image_quality', 'standard' ); ?>
						<select id="abcc_openai_image_quality" name="abcc_openai_image_quality">
							<option value="standard" <?php selected( $openai_quality, 'standard' ); ?>><?php esc_html_e( 'Standard', 'automated-blog-content-creator' ); ?></option>
							<option value="hd" <?php selected( $openai_quality, 'hd' ); ?>><?php esc_html_e( 'HD', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_stability_image_size">
							<?php echo esc_html__( 'Stability AI Resolution:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'SDXL supported resolution presets.', 'automated-blog-content-creator' ) ) ); ?>
						</label>
					</th>
					<td>
						<?php $stability_size = get_option( 'abcc_stability_image_size', '1024x1024' ); ?>
						<select id="abcc_stability_image_size" name="abcc_stability_image_size">
							<option value="512x512" <?php selected( $stability_size, '512x512' ); ?>>512 x 512</option>
							<option value="768x768" <?php selected( $stability_size, '768x768' ); ?>>768 x 768</option>
							<option value="1024x1024" <?php selected( $stability_size, '1024x1024' ); ?>>1024 x 1024</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="preferred_image_service">
							<?php echo esc_html__( 'Image Generation Service:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<select id="preferred_image_service" name="preferred_image_service">
							<option value="auto" <?php selected( get_option( 'preferred_image_service', 'auto' ), 'auto' ); ?>>
								<?php esc_html_e( 'Automatic (based on text model)', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="openai" <?php selected( get_option( 'preferred_image_service' ), 'openai' ); ?>>
								<?php esc_html_e( 'Always use DALL-E', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="stability" <?php selected( get_option( 'preferred_image_service' ), 'stability' ); ?>>
								<?php esc_html_e( 'Always use Stability AI', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="gemini" <?php selected( get_option( 'preferred_image_service' ), 'gemini' ); ?>>
								<?php esc_html_e( 'Always use Gemini Nano Banana', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Choose how to handle image generation for different text models', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr id="gemini-image-settings" style="<?php echo 'gemini' === get_option( 'preferred_image_service' ) ? '' : 'display:none;'; ?>">
					<th scope="row">
						<label for="abcc_gemini_image_model">
							<?php echo esc_html__( 'Gemini Image Model:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<select id="abcc_gemini_image_model" name="abcc_gemini_image_model">
							<option value="gemini-2.5-flash-image" <?php selected( get_option( 'abcc_gemini_image_model', 'gemini-2.5-flash-image' ), 'gemini-2.5-flash-image' ); ?>>
								<?php esc_html_e( 'Nano Banana (Gemini 2.5 Flash) - Fast & Efficient', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="gemini-3-pro-image-preview" <?php selected( get_option( 'abcc_gemini_image_model' ), 'gemini-3-pro-image-preview' ); ?>>
								<?php esc_html_e( 'Nano Banana Pro (Gemini 3 Pro) - Premium Quality', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Nano Banana Pro offers higher quality and text rendering capabilities', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr id="gemini-image-size-settings" style="<?php echo 'gemini' === get_option( 'preferred_image_service' ) ? '' : 'display:none;'; ?>">
					<th scope="row">
						<label for="abcc_gemini_image_size">
							<?php echo esc_html__( 'Gemini Image Size:', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<select id="abcc_gemini_image_size" name="abcc_gemini_image_size">
							<option value="1K" <?php selected( get_option( 'abcc_gemini_image_size', '2K' ), '1K' ); ?>>
								<?php esc_html_e( '1K - Standard Quality', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="2K" <?php selected( get_option( 'abcc_gemini_image_size', '2K' ), '2K' ); ?>>
								<?php esc_html_e( '2K - High Quality (Recommended)', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="4K" <?php selected( get_option( 'abcc_gemini_image_size' ), '4K' ); ?>>
								<?php esc_html_e( '4K - Ultra High Quality', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Higher resolution images take longer to generate and use more API quota', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<script>
			jQuery(document).ready(function($) {
				$('#preferred_image_service').on('change', function() {
					if ($(this).val() === 'gemini') {
						$('#gemini-image-settings, #gemini-image-size-settings').show();
					} else {
						$('#gemini-image-settings, #gemini-image-size-settings').hide();
					}
				});
			});
			</script>

			<h2><?php esc_html_e( 'Automation & Scheduling', 'automated-blog-content-creator' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="openai_auto_create">
						<?php echo esc_html__( 'Schedule post creation:', 'automated-blog-content-creator' ); ?>
					</label></th>
					<td>
						<select id="openai_auto_create" name="openai_auto_create">
							<?php $auto_create = get_option( 'openai_auto_create', 'none' ); ?>
							<option value="none" <?php selected( $auto_create, 'none' ); ?>>
								<?php esc_html_e( 'None', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="hourly" <?php selected( $auto_create, 'hourly' ); ?>>
								<?php esc_html_e( 'Hourly', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="daily" <?php selected( $auto_create, 'daily' ); ?>>
								<?php esc_html_e( 'Daily', 'automated-blog-content-creator' ); ?>
							</option>
							<option value="weekly" <?php selected( $auto_create, 'weekly' ); ?>>
								<?php esc_html_e( 'Weekly', 'automated-blog-content-creator' ); ?>
							</option>
						</select>
						<p class="description"><?php esc_html_e( 'You can disable the automatic creation of posts or schedule as you wish', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="openai_char_limit">
						<?php echo esc_html__( 'Content Length', 'automated-blog-content-creator' ); ?>
						<span class="dashicons dashicons-editor-help" title="<?php esc_attr_e( 'Higher values produce longer, more detailed content and use more API credits.', 'automated-blog-content-creator' ); ?>"></span>
					</label></th>
					<td>
						<input type="number" id="openai_char_limit" name="openai_char_limit"
							value="<?php echo esc_attr( get_option( 'openai_char_limit', 200 ) ); ?>" min="1">
						<p class="description"><?php esc_html_e( 'The maximum number of tokens (words and characters) will be used by the AI during post generation. Range: 1-4096', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="openai_email_notifications">
						<?php echo esc_html__( 'Enable Email Notifications:', 'automated-blog-content-creator' ); ?>
					</label></th>
					<td>
						<input type="checkbox" id="openai_email_notifications"
							name="openai_email_notifications" <?php checked( get_option( 'openai_email_notifications', false ) ); ?>>
						<p class="description">
							<?php esc_html_e( 'Receive email notifications when a new post is created.', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
			</table>
					<?php submit_button( __( 'Save Advanced Settings', 'automated-blog-content-creator' ) ); ?>
		</form>
	</div>
	<?php elseif ( $current_tab === 'about' ) : ?>
	<div class="tab-pane active">
		<div class="about-wp-autoinsight">
			<!-- Header Section -->
			<div class="about-header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 40px; border-radius: 12px; margin-bottom: 30px; text-align: center;">
				<h2 style="margin: 0 0 10px 0; font-size: 2.5em; font-weight: 300;"><?php esc_html_e( 'WP-AutoInsight', 'automated-blog-content-creator' ); ?></h2>
				<p style="margin: 0; font-size: 1.2em; opacity: 0.9;"><?php esc_html_e( 'Your Site, Your Rules. High-quality AI content without the SaaS markup.', 'automated-blog-content-creator' ); ?></p>
				<div style="margin-top: 20px;">
					<span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 0.9em;">
						<?php
						/* translators: %s: version number. */
						printf( esc_html__( 'Version %s (Faiz)', 'automated-blog-content-creator' ), '3.3.0' );
						?>
					</span>
				</div>
			</div>

			<div class="about-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
				<!-- Main Content -->
				<div class="about-main">
					<!-- Changelog -->
					<div class="about-section" style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e9ecef;">
						<h3 style="color: #2c3e50; margin-top: 0;">
							<span class="dashicons dashicons-clipboard" style="color: #2271b1;"></span>
							<?php esc_html_e( "What's New in v3.3", 'automated-blog-content-creator' ); ?>
						</h3>
						<ul style="list-style: none; padding: 0;">
							<li style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Draft-First Workflow:', 'automated-blog-content-creator' ); ?></strong>
								<?php esc_html_e( 'New option to always save generated content as drafts for review.', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Content History Log:', 'automated-blog-content-creator' ); ?></strong>
								<?php esc_html_e( 'Keep track of your generated posts directly from the settings page.', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Refined UI:', 'automated-blog-content-creator' ); ?></strong>
								<?php esc_html_e( 'Improved keyword input (textarea), better labels, and unified status indicators.', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 12px;">
								<strong><?php esc_html_e( 'Reliable Notifications:', 'automated-blog-content-creator' ); ?></strong>
								<?php esc_html_e( 'Fixed and improved email notifications for both scheduled and manual runs.', 'automated-blog-content-creator' ); ?>
							</li>
						</ul>
					</div>

					<!-- The Philosophy -->
					<div class="about-section" style="background: white; padding: 25px; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 25px;">
						<h3 style="color: #2c3e50; margin-top: 0;">
							<span class="dashicons dashicons-shield"></span>
							<?php esc_html_e( 'Our Philosophy', 'automated-blog-content-creator' ); ?>
						</h3>
						<p style="color: #495057; line-height: 1.6;">
							<?php esc_html_e( 'WP-AutoInsight is built on the belief that you should own your tools and your data. Unlike SaaS platforms that charge high monthly fees and lock your content behind a subscription, this plugin runs on your own infrastructure and uses your own API keys at cost.', 'automated-blog-content-creator' ); ?>
						</p>
						<p style="color: #495057; line-height: 1.6;">
							<?php esc_html_e( 'You pay only for what you use, directly to the AI providers, with no markup from us. Your site, your rules, your content.', 'automated-blog-content-creator' ); ?>
						</p>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="about-sidebar">
					<!-- Consultant Info -->
					<div class="about-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; text-align: center;">
						<div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
							<span class="dashicons dashicons-businessman" style="color: #475569; font-size: 2.5em; width: auto; height: auto;"></span>
						</div>
						<h3 style="margin: 0 0 5px 0; color: #1e293b;"><?php esc_html_e( 'Professional Services', 'automated-blog-content-creator' ); ?></h3>
						<p style="color: #64748b; margin: 0 0 15px 0; font-size: 0.9em;"><?php esc_html_e( 'Need help with content strategy or custom AI integrations?', 'automated-blog-content-creator' ); ?></p>
						<a href="mailto:phalkmin@protonmail.com?subject=Consulting%20Inquiry" class="button button-primary" style="width: 100%;">
							<?php esc_html_e( 'Work With Me', 'automated-blog-content-creator' ); ?>
						</a>
					</div>

					<!-- Support & Community -->
					<div class="about-card" style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 20px;">
						<h4 style="margin-top: 0; color: #1e293b;"><?php esc_html_e( 'Resources', 'automated-blog-content-creator' ); ?></h4>
						<ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9em;">
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-sos" style="font-size: 18px; width: 18px; height: 18px; color: #64748b; margin-right: 5px;"></span>
								<a href="https://wordpress.org/support/plugin/wp-autoinsight/" target="_blank" style="text-decoration: none;"><?php esc_html_e( 'Support Forum', 'automated-blog-content-creator' ); ?></a>
							</li>
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-star-filled" style="font-size: 18px; width: 18px; height: 18px; color: #64748b; margin-right: 5px;"></span>
								<a href="https://wordpress.org/plugins/wp-autoinsight/#reviews" target="_blank" style="text-decoration: none;"><?php esc_html_e( 'Rate the Plugin', 'automated-blog-content-creator' ); ?></a>
							</li>
							<li>
								<span class="dashicons dashicons-admin-site-alt3" style="font-size: 18px; width: 18px; height: 18px; color: #64748b; margin-right: 5px;"></span>
								<a href="https://github.com/phalkmin/wp-autoinsight" target="_blank" style="text-decoration: none;"><?php esc_html_e( 'Source Code', 'automated-blog-content-creator' ); ?></a>
							</li>
						</ul>
					</div>

					<!-- Credits -->
					<div class="about-card" style="background: #f1f5f9; padding: 15px; border-radius: 8px; font-size: 0.85em; color: #475569;">
						<p style="margin: 0;">
							<?php
							/* translators: %s: developer name. */
							printf( esc_html__( 'Developed with passion by %s.', 'automated-blog-content-creator' ), '<strong>Paulo H. Alkmin</strong>' );
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php elseif ( $current_tab === 'audio-settings' ) : ?>
	<div class="tab-pane active">
		<h2><?php esc_html_e( 'Audio Transcription Settings', 'automated-blog-content-creator' ); ?></h2>
		<p><?php esc_html_e( 'Configure audio transcription and automatic post creation from audio files.', 'automated-blog-content-creator' ); ?></p>
		
		<form method="post" action="">
			<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="abcc_enable_audio_transcription">
							<?php esc_html_e( 'Enable Audio Transcription', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" id="abcc_enable_audio_transcription" 
							name="abcc_enable_audio_transcription" 
							<?php checked( get_option( 'abcc_enable_audio_transcription', true ) ); ?>>
						<p class="description">
							<?php esc_html_e( 'Allow transcribing audio files and converting them to blog posts using OpenAI Whisper', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_supported_audio_formats">
							<?php esc_html_e( 'Supported Audio Formats', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<?php
						$supported_formats = get_option( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
						$available_formats = array(
							'mp3'  => 'MP3',
							'wav'  => 'WAV',
							'mp4'  => 'MP4',
							'm4a'  => 'M4A',
							'webm' => 'WebM',
							'flac' => 'FLAC',
						);

						foreach ( $available_formats as $format => $label ) {
							$checked = in_array( $format, $supported_formats, true ) ? 'checked' : '';
							printf(
								'<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="abcc_supported_audio_formats[]" value="%s" %s> %s</label>',
								esc_attr( $format ),
								esc_attr( $checked ),
								esc_html( $label )
							);
						}
						?>
						<p class="description">
							<?php esc_html_e( 'Select which audio formats to enable for transcription. OpenAI Whisper supports most common audio formats.', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_transcription_language">
							<?php esc_html_e( 'Transcription Language', 'automated-blog-content-creator' ); ?>
						</label>
					</th>
					<td>
						<select id="abcc_transcription_language" name="abcc_transcription_language">
							<?php
							$current_lang = get_option( 'abcc_transcription_language', 'en' );
							$languages    = array(
								'en'   => 'English',
								'es'   => 'Spanish',
								'fr'   => 'French',
								'de'   => 'German',
								'it'   => 'Italian',
								'pt'   => 'Portuguese',
								'ru'   => 'Russian',
								'ja'   => 'Japanese',
								'ko'   => 'Korean',
								'zh'   => 'Chinese',
								'auto' => 'Auto-detect',
							);

							foreach ( $languages as $code => $name ) {
								printf(
									'<option value="%s"%s>%s</option>',
									esc_attr( $code ),
									selected( $current_lang, $code, false ),
									esc_html( $name )
								);
							}
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select the primary language for transcription. Auto-detect will let OpenAI determine the language.', 'automated-blog-content-creator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Usage Information', 'automated-blog-content-creator' ); ?>
					</th>
					<td>
						<div style="background: #f0f0f1; padding: 15px; border-radius: 5px;">
							<h4 style="margin-top: 0;"><?php esc_html_e( 'How to use Audio Transcription:', 'automated-blog-content-creator' ); ?></h4>
							<ol>
								<li><?php esc_html_e( 'Upload an audio file to your Media Library', 'automated-blog-content-creator' ); ?></li>
								<li><?php esc_html_e( 'Go to the audio file\'s edit page', 'automated-blog-content-creator' ); ?></li>
								<li><?php esc_html_e( 'Click "Transcribe & Create Post" in the publish box', 'automated-blog-content-creator' ); ?></li>
								<li><?php esc_html_e( 'The AI will transcribe the audio and create a formatted blog post', 'automated-blog-content-creator' ); ?></li>
							</ol>
							<p><strong><?php esc_html_e( 'Note:', 'automated-blog-content-creator' ); ?></strong> <?php esc_html_e( 'Audio files must be under 25MB (OpenAI Whisper limit). Longer files may take several minutes to process.', 'automated-blog-content-creator' ); ?></p>
						</div>
					</td>
				</tr>
			</table>
			<?php submit_button( esc_html__( 'Save Audio Settings', 'automated-blog-content-creator' ) ); ?>
		</form>
	</div>
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
	$current_model    = get_option( 'prompt_select', '' );
	$available_models = abcc_get_ai_model_options();

	// No API keys configured, nothing to validate.
	if ( empty( $available_models ) ) {
		return;
	}

	// No model set yet (fresh install) — silently assign the first available, no notice.
	if ( empty( $current_model ) ) {
		$first_provider = reset( $available_models );
		$first_model    = key( $first_provider['options'] );
		update_option( 'prompt_select', $first_model );
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
		update_option( 'prompt_select', $first_model );
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
