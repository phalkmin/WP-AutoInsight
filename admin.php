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
 * The function `abcc_add_subpages_to_menu` adds subpages for "Text Settings" and "Advanced Settings"
 * under the main menu page "WP-AutoInsight" in WordPress admin menu.
 */
function abcc_add_subpages_to_menu() {
	add_menu_page(
		__( 'WP-AutoInsight', 'automated-blog-content-creator' ),
		__( 'WP-AutoInsight', 'automated-blog-content-creator' ),
		'manage_options',
		'automated-blog-content-creator-post',
		'abcc_openai_text_settings_page'
	);

	add_submenu_page(
		'automated-blog-content-creator-post',
		__( 'Text Settings', 'automated-blog-content-creator' ),
		__( 'Text Settings', 'automated-blog-content-creator' ),
		'manage_options',
		'automated-blog-content-creator-post',
		'abcc_openai_text_settings_page'
	);

	add_submenu_page(
		'automated-blog-content-creator-post',
		__( 'Advanced Settings', 'automated-blog-content-creator' ),
		__( 'Advanced Settings', 'automated-blog-content-creator' ),
		'manage_options',
		'abcc-openai-advanced-settings',
		'abcc_openai_blog_post_options_page'
	);
}
add_action( 'admin_menu', 'abcc_add_subpages_to_menu' );

/**
 * The function abcc_get_ai_model_options() returns an array of AI model options filtered by
 * available API keys and limited to three models per service (cheap, medium, premium).
 *
 * @return array of AI model options grouped by available providers
 */
function abcc_get_ai_model_options() {
	$options = array();

	// Check for OpenAI API key
	$openai_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	if ( ! empty( $openai_key ) ) {
		$options['openai'] = array(
			'group'   => 'OpenAI Models',
			'options' => array(
				// Cheap option
				'gpt-3.5-turbo'   => array(
					'name'        => 'GPT-3.5 Turbo',
					'description' => 'Fast and cost-effective',
					'cost_tier'   => '1',
				),
				// Medium option
				'gpt-4o'          => array(
					'name'        => 'GPT-4o',
					'description' => 'Fast, intelligent, flexible GPT model',
					'cost_tier'   => '2',
				),
				// Premium option
				'gpt-4.5-preview' => array(
					'name'        => 'GPT-4.5 Preview',
					'description' => 'Largest and most capable GPT model',
					'cost_tier'   => '3',
				),
			),
		);
	}

	// Check for Claude API key
	$claude_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
	if ( ! empty( $claude_key ) ) {
		$options['claude'] = array(
			'group'   => 'Claude Models',
			'options' => array(
				// Cheap option
				'claude-3-haiku-20240307'    => array(
					'name'        => 'Claude 3 Haiku',
					'description' => 'Fast and cost-effective',
					'cost_tier'   => '1',
				),
				// Medium option
				'claude-3.5-sonnet-20241022' => array(
					'name'        => 'Claude 3.5 Sonnet',
					'description' => 'Improved balanced performance',
					'cost_tier'   => '2',
				),
				// Premium option
				'claude-3.7-sonnet-20250219' => array(
					'name'        => 'Claude 3.7 Sonnet',
					'description' => 'Latest premium model with advanced capabilities',
					'cost_tier'   => '3',
				),
			),
		);
	}

	// Check for Gemini API key
	$gemini_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
	if ( ! empty( $gemini_key ) ) {
		$options['gemini'] = array(
			'group'   => 'Google Gemini Models',
			'options' => array(
				// Cheap option
				'gemini-1.5-flash'         => array(
					'name'        => 'Gemini 1.5 Flash',
					'description' => 'Fast and versatile performance across diverse tasks',
					'cost_tier'   => '1',
				),
				// Medium option
				'gemini-1.5-pro'           => array(
					'name'        => 'Gemini 1.5 Pro',
					'description' => 'Complex reasoning with 2M token context window',
					'cost_tier'   => '2',
				),
				// Premium option
				'gemini-2.0-pro-exp-02-05' => array(
					'name'        => 'Gemini 2.0 Pro',
					'description' => 'Most powerful Gemini model with advanced reasoning',
					'cost_tier'   => '3',
				),
			),
		);
	}

	return $options;
}


/**
 * The function `abcc_category_dropdown` is used to display a dropdown of categories in the WordPress admin
 * for the OpenAI blog post generator plugin.
 *
 * @param array $selected_categories An array of category IDs that should be selected by default.
 */
function abcc_category_dropdown( $selected_categories = array() ) {
	$categories = get_categories( array( 'hide_empty' => 0 ) );
	echo '<select id="openai_selected_categories" class="wpai-category-select" name="openai_selected_categories[]" multiple style="width:100%;">';
	foreach ( $categories as $category ) {
		$selected = in_array( $category->term_id, $selected_categories, true ) ? ' selected="selected"' : '';
		echo '<option value="' . esc_attr( $category->term_id ) . '"' . esc_html( $selected ) . '>' . esc_html( $category->name ) . '</option>';
	}
	echo '</select>';
}

/**
 * The function `abcc_openai_text_settings_page` is used to display and handle settings for an OpenAI
 * blog post generator, including options for keywords, tone selection, and category selection.
 */
function abcc_openai_text_settings_page() {
	if ( isset( $_POST['submit'], $_POST['abcc_openai_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['abcc_openai_nonce'] ), 'abcc_openai_generate_post' ) ) {
		$keywords            = isset( $_POST['openai_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_keywords'] ) ) : '';
		$selected_categories = isset( $_POST['openai_selected_categories'] ) ? array_map( 'intval', $_POST['openai_selected_categories'] ) : array();

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

		update_option( 'openai_keywords', wp_unslash( $keywords ) );
		update_option( 'openai_selected_categories', $selected_categories );
	}

	$selected_categories = get_option( 'openai_selected_categories', array() );
	$keywords            = get_option( 'openai_keywords', '' );
	$schedule_info       = get_openai_event_schedule();
	$tone                = get_option( 'openai_tone', '' );
	$custom_tone_value   = get_option( 'custom_tone', '' );

	if ( $schedule_info ) {
		echo '<div class="notice notice-info">
				<p>Posts are scheduled to be automatically published <strong>' . esc_html( $schedule_info['schedule'] ) . '</strong> and the next execution will be on ' . esc_html( $schedule_info['next_run'] ) . '.</p>
			  </div>';
	} else {
		echo '<div class="notice notice-info">
				<p>There are no scheduled posts to be published.</p>
			  </div>';
	}

	$tones = array(
		'default'  => 'Use a default tone',
		'business' => 'Business-oriented',
		'academic' => 'Academic',
		'funny'    => 'Funny',
		'epic'     => 'Epic',
		'personal' => 'Personal',
		'custom'   => 'Custom',
	);
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'OpenAI Blog Post Generator', 'automated-blog-content-creator' ); ?></h1>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div class="meta-box-sortables ui-sortable">
						<form method="post" action="">
							<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
							<div class="postbox">
								<h2 class="hndle ui-sortable-handle">
									<?php echo esc_html__( 'Settings', 'automated-blog-content-creator' ); ?>
								</h2>
								<div class="inside">
									<table class="form-table">
										<tr>
											<th scope="row"><label for="openai_keywords">
													<?php echo esc_html__( 'Subjects / Keywords that blog posts should be about:', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<textarea rows="4" cols="50" id="openai_keywords" name="openai_keywords"><?php echo esc_textarea( $keywords ); ?></textarea>
												<p class="description">Write a list of keywords that will be used on the prompt. Do note write the whole prompt.</p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="openai_tone">
													<?php echo esc_html__( 'Tone you want to use:', 'automated-blog-content-creator' ); ?>
												</label>
											</th>
											<td>
												<?php foreach ( $tones as $value => $label ) : ?>
													<input type="radio" id="<?php echo esc_attr( $value ); ?>"
														name="openai_tone" value="<?php echo esc_attr( $value ); ?>" <?php checked( $tone, $value ); ?>>
													<label
														for="<?php echo esc_attr( $value ); ?>"><?php echo esc_attr( $label ); ?></label>
												<?php endforeach; ?>
												<input type="text" id="custom_tone" name="custom_tone"
													value="<?php echo esc_attr( $custom_tone_value ); ?>"
													style="<?php echo ( 'custom' === $tone ) ? '' : 'display:none;'; ?>">
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="openai_selected_categories">
													<?php echo esc_html__( 'Select Categories:', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<?php abcc_category_dropdown( $selected_categories ); ?>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="openai_generate_seo">
													<?php echo esc_html__( 'Generate SEO Metadata:', 'automated-blog-content-creator' ); ?>
												</label>
											</th>
											<td>
												<input type="checkbox" id="openai_generate_seo" 
														name="openai_generate_seo" 
														<?php checked( get_option( 'openai_generate_seo', true ) ); ?>>
												<p class="description">
													<?php esc_html_e( 'Generate SEO metadata using AI (requires a compatible SEO plugin like Yoast)', 'automated-blog-content-creator' ); ?>
												</p>
											</td>
										</tr>
									</table>
								</div>
							</div>
							<p class="submit">
								<input type="submit" name="submit" id="submit" class="button button-primary"
									value="<?php echo esc_attr__( 'Save', 'automated-blog-content-creator' ); ?>">
								<button type="button" name="generate-post" id="generate-post"
									class="button button-secondary"><?php echo esc_attr__( 'Create post manually', 'automated-blog-content-creator' ); ?></button>
							</p>
						</form>
					</div>
				</div>
			</div>
			<br class="clear">
		</div>
	</div>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
			jQuery(".wpai-category-select").select2();
		});
	</script>
	<?php
}

/**
 * Ensure the selected AI model is valid based on available API keys.
 * If the current model is no longer available, select a default from available options.
 */
function abcc_validate_selected_model() {
	$current_model    = get_option( 'prompt_select', 'gpt-3.5-turbo' );
	$available_models = abcc_get_ai_model_options();

	$model_available = false;
	foreach ( $available_models as $provider => $group ) {
		if ( isset( $group['options'][ $current_model ] ) ) {
			$model_available = true;
			break;
		}
	}

	if ( ! $model_available && ! empty( $available_models ) ) {
		$first_provider = reset( $available_models );
		$first_model    = key( $first_provider['options'] );
		update_option( 'prompt_select', $first_model );

		// Add an admin notice to inform the user
		add_action( 'admin_notices', 'abcc_model_changed_notice' );
	}
}

/**
 * Display admin notice when the model has been automatically changed.
 */
function abcc_model_changed_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p><?php _e( 'The previously selected AI model is no longer available. A default model has been selected based on your available API keys.', 'automated-blog-content-creator' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_init', 'abcc_validate_selected_model' );


/**
 * The function abcc_openai_blog_post_options_page() is used to display and handle options for an
 * OpenAI blog post generator plugin in WordPress.
 */
function abcc_openai_blog_post_options_page() {
	if ( isset( $_POST['submit'], $_POST['abcc_openai_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['abcc_openai_nonce'] ), 'abcc_openai_generate_post' ) ) {
		$api_key                    = isset( $_POST['openai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_api_key'] ) ) : '';
		$gemini_api_key             = isset( $_POST['gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) : '';
		$claude_api_key             = isset( $_POST['claude_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['claude_api_key'] ) ) : '';
		$stability_api_key          = isset( $_POST['stability_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stability_api_key'] ) ) : '';
		$openai_custom_endpoint     = isset( $_POST['openai_custom_endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['openai_custom_endpoint'] ) ) : '';
		$auto_create                = isset( $_POST['openai_auto_create'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_auto_create'] ) ) : '';
		$prompt_select              = isset( $_POST['prompt_select'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt_select'] ) ) : '';
		$char_limit                 = isset( $_POST['openai_char_limit'] ) ? absint( $_POST['openai_char_limit'] ) : 0;
		$openai_email_notifications = isset( $_POST['openai_email_notifications'] );
		$openai_generate_images     = isset( $_POST['openai_generate_images'] );
		$preferred_image_service    = isset( $_POST['preferred_image_service'] ) ? sanitize_text_field( $_POST['preferred_image_service'] ) : 'auto';

		update_option( 'openai_api_key', wp_unslash( $api_key ) );
		update_option( 'gemini_api_key', wp_unslash( $gemini_api_key ) );
		update_option( 'claude_api_key', wp_unslash( $claude_api_key ) );
		update_option( 'stability_api_key', wp_unslash( $stability_api_key ) );
		update_option( 'openai_custom_endpoint', $openai_custom_endpoint );
		update_option( 'openai_generate_images', $openai_generate_images );
		update_option( 'openai_auto_create', $auto_create );
		update_option( 'prompt_select', $prompt_select );
		update_option( 'openai_char_limit', $char_limit );
		update_option( 'openai_email_notifications', $openai_email_notifications );
		update_option( 'preferred_image_service', $preferred_image_service );

		abcc_validate_selected_model();

	}

	$api_key                    = get_option( 'openai_api_key', '' );
	$gemini_api_key             = get_option( 'gemini_api_key', '' );
	$claude_api_key             = get_option( 'claude_api_key', '' );
	$stability_api_key          = get_option( 'stability_api_key', '' );
	$openai_custom_endpoint     = get_option( 'openai_custom_endpoint', '' );
	$auto_create                = get_option( 'openai_auto_create', 'none' );
	$prompt_select              = get_option( 'prompt_select', 'openai' );
	$char_limit                 = get_option( 'openai_char_limit', 200 );
	$openai_email_notifications = get_option( 'openai_email_notifications', false );

	$schedule_info = get_openai_event_schedule();

	if ( $schedule_info ) {
		echo '<div class="notice notice-info">
				<p>Posts are scheduled to be automatically published <strong>' . esc_html( $schedule_info['schedule'] ) . '</strong> and the next execution will be on ' . esc_html( $schedule_info['next_run'] ) . '.</p>
			  </div>';
	} else {
		echo '<div class="notice notice-info">
				<p>There are no scheduled posts to be published.</p>
			  </div>';
	}
	?>

	<div class="wrap">
		<h1><?php echo esc_html__( 'OpenAI Blog Post Generator', 'automated-blog-content-creator' ); ?></h1>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div class="meta-box-sortables ui-sortable">
						<form method="post" action="">
							<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
							<div class="postbox">
								<h2 class="hndle ui-sortable-handle">
									<?php echo esc_html__( 'Settings', 'automated-blog-content-creator' ); ?>
								</h2>
								<div class="inside">
									<table class="form-table">
										<?php if ( ! defined( 'OPENAI_API' ) ) : ?>
											<tr>
												<th scope="row"><label for="openai_api_key">
														<?php echo esc_html__( 'OpenAI API key:', 'automated-blog-content-creator' ); ?>
													</label></th>
												<td>
													<input type="text" id="openai_api_key" name="openai_api_key"
														value="<?php echo esc_attr( $api_key ); ?>"
														class="regular-text"><small>for extra security, it's advised to include
														it on wp-config.php, using <em>define( 'OPENAI_API', '' );</em></small>
												</td>
											</tr>
										<?php else : ?>
											<tr><th colspan="2">
												<strong>Your OpenAI API key is already set in wp-config.php.</strong>
											</th></tr>
										<?php endif; ?>

										<?php if ( ! defined( 'GEMINI_API' ) ) : ?>
											<tr>
												<th scope="row"><label for="gemini_api_key">
														<?php echo esc_html__( 'Gemini API key:', 'automated-blog-content-creator' ); ?>
													</label></th>
												<td>
													<input type="text" id="gemini_api_key" name="gemini_api_key"
														value="<?php echo esc_attr( $gemini_api_key ); ?>"
														class="regular-text"><small>for extra security, it's advised to include
														it on wp-config.php, using <em>define( 'GEMINI_API', '' );</em></small>
												</td>
											</tr>
										<?php else : ?>
											<tr><th colspan="2">
												<strong>Your Gemini API key is already set in wp-config.php.</strong>
											</th></tr>
										<?php endif; ?>

										<?php if ( ! defined( 'CLAUDE_API' ) ) : ?>
											<tr>
												<th scope="row"><label for="claude_api_key">
														<?php echo esc_html__( 'Claude API key:', 'automated-blog-content-creator' ); ?>
													</label></th>
												<td>
													<input type="text" id="claude_api_key" name="claude_api_key"
														value="<?php echo esc_attr( $claude_api_key ); ?>"
														class="regular-text"><small>for extra security, it's advised to include
														it on wp-config.php, using <em>define( 'CLAUDE_API', '' );</em></small>
												</td>
											</tr>
										<?php else : ?>
											<tr><th colspan="2">
												<strong>Your Claude API key is already set in wp-config.php.</strong>
											</th></tr>
										<?php endif; ?>

										<?php if ( ! defined( 'STABILITY_API' ) ) : ?>
											<tr>
												<th scope="row"><label for="stability_api_key">
														<?php echo esc_html__( 'Stability AI API key (fallback for image generation):', 'automated-blog-content-creator' ); ?>
													</label></th>
												<td>
													<input type="text" id="stability_api_key" name="stability_api_key"
														value="<?php echo esc_attr( $stability_api_key ); ?>"
														class="regular-text"><small>for extra security, it's advised to include
														it on wp-config.php, using <em>define( 'STABILITY_API', '' );</em></small>
												</td>
											</tr>
										<?php else : ?>
											<tr><th colspan="2">
												<strong>Your Stability AI API key is already set in wp-config.php.</strong>
											</th></tr>
										<?php endif; ?>
										<!--
										<tr>
											<th scope="row"><label for="openai_custom_endpoint">
													<?php echo esc_html__( 'Custom OpenAI-compatible Endpoint:', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<input type="url" id="openai_custom_endpoint" name="openai_custom_endpoint"
													value="<?php echo esc_attr( $openai_custom_endpoint ); ?>"
													class="regular-text" placeholder="https://api.example.com/v1">
												<p class="description">Optional: Enter a custom endpoint URL that's compatible with OpenAI's API format</p>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="openai_custom_image_endpoint">
												<?php echo esc_html__( 'Custom Image Generation Endpoint:', 'automated-blog-content-creator' ); ?>
											</label></th>
											<td>
												<input type="url" id="openai_custom_image_endpoint" name="openai_custom_image_endpoint"
													value="<?php echo esc_attr( get_option( 'openai_custom_image_endpoint', '' ) ); ?>"
													class="regular-text" placeholder="https://api.example.com/v1/images">
												<p class="description">Optional: Enter a custom endpoint URL for image generation API</p>
											</td>
										</tr>
										-->
										<tr>
											<th scope="row"><label for="prompt_select">
												<?php echo esc_html__( 'AI Model Selection', 'automated-blog-content-creator' ); ?>
											</label></th>
											<td>
												<select id="prompt_select" name="prompt_select" class="abcc-model-select">
													<?php
													$current_model = get_option( 'prompt_select', 'gpt-3.5-turbo' );
													$model_options = abcc_get_ai_model_options();

													foreach ( $model_options as $provider => $group ) {
														echo '<optgroup label="' . esc_attr( $group['group'] ) . '">';
														foreach ( $group['options'] as $model_id => $model ) {
															echo '<option value="' . esc_attr( $model_id ) . '" ' .
																selected( $current_model, $model_id, false ) .
																' data-cost-tier="' . esc_attr( $model['cost_tier'] ) . '">' .
																esc_html( $model['name'] ) . ' - ' . esc_html( $model['description'] ) .
																'</option>';
														}
														echo '</optgroup>';
													}
													?>
												</select>
												<p class="description model-cost-indicator"></p>
											</td>
										</tr>
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
												</select>
												<p class="description">
													<?php esc_html_e( 'Choose how to handle image generation for different text models', 'automated-blog-content-creator' ); ?>
												</p>
											</td>
										</tr>
										<tr>
											<th scope="row"><label for="openai_auto_create">
													<?php echo esc_html__( 'Schedule post creation?', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<select id="openai_auto_create" name="openai_auto_create">
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
												<p class="description">You can disable the automatic creation of posts or schedule as you wish</p>
											</td>
										</tr>

										<tr>
											<th scope="row"><label for="openai_char_limit">
													<?php echo esc_html__( 'Max Token Limit', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<input type="number" id="openai_char_limit" name="openai_char_limit"
													value="<?php echo esc_attr( $char_limit ); ?>" min="1">
												<p class="description">The maximum number of tokens (words and characters) will be used by the AI during post generation. Range: 1-4096</p>
												<p class="token-estimate"></p>
											</td>
										</tr>

										<tr>
											<th scope="row"><label for="openai_email_notifications">
													<?php echo esc_html__( 'Enable Email Notifications:', 'automated-blog-content-creator' ); ?>
												</label></th>
											<td>
												<input type="checkbox" id="openai_email_notifications"
													name="openai_email_notifications" <?php checked( $openai_email_notifications ); ?>>
												<p class="description">
													<?php esc_html_e( 'Receive email notifications when a new post is created.', 'automated-blog-content-creator' ); ?>
												</p>
											</td>
										</tr>
									</table>
								</div>
							</div>
							<p class="submit">
								<input type="submit" name="submit" id="submit" class="button button-primary"
									value="<?php echo esc_attr__( 'Save', 'automated-blog-content-creator' ); ?>">
							</p>
							<!--
							<button type="button" id="refresh-models" class="button button-secondary">
								<?php esc_html_e( 'Refresh Available Models', 'automated-blog-content-creator' ); ?>
							</button>
							-->
						</form>
					</div>
				</div>
			</div>
			<br class="clear">
			I can update this plugin faster with your help 👉🏼👈🏼
			<a href='https://ko-fi.com/U7U1LM8AP' target='_blank'><img height='36' style='border:0px;height:36px;' src='https://storage.ko-fi.com/cdn/kofi3.png?v=3' border='0' alt='Buy Me a Coffee at ko-fi.com' /></a>
		</div>
	</div>

	<style>
.cost-indicator {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	margin-right: 5px;
}
.cost-tier-1 { background-color: #28a745; }
.cost-tier-2 { background-color: #ffc107; }
.cost-tier-3 { background-color: #dc3545; }
</style>

<script>
jQuery(document).ready(function($) {
	function updateCostIndicator() {
		const selectedOption = $('#prompt_select option:selected');
		const costTier = selectedOption.data('cost-tier');
		const indicators = {
			'1': '💰 Most affordable',
			'2': '💰💰 Moderate cost',
			'3': '💰💰💰 Premium tier'
		};
		
		$('.model-cost-indicator').html(`
			<span class="cost-indicator cost-tier-${costTier}"></span>
			${indicators[costTier]}
		`);
	}
	
	$('#prompt_select').change(updateCostIndicator);
	updateCostIndicator();
});
</script>

	<?php
}


function abcc_update_token_limit_description() {
	$model       = get_option( 'prompt_select', 'gpt-3.5-turbo' );
	$max_context = abcc_get_model_context_window( $model );

	// translators: %1$s is the model name, %2$d is the maximum number of tokens
	return sprintf(
		__( 'Maximum tokens for content generation (1 token ≈ 4 characters). Current model (%1$s) maximum: %2$d tokens. Note: The actual content length will be less as some tokens are used by the prompt.', 'automated-blog-content-creator' ),
		$model,
		$max_context
	);
}


add_action(
	'wp_ajax_abcc_estimate_tokens',
	function () {
		check_ajax_referer( 'abcc_openai_generate_post', 'abcc_openai_nonce' );

		$char_limit = absint( $_POST['char_limit'] );
		$model      = sanitize_text_field( $_POST['model'] );

		$available_tokens = abcc_calculate_available_tokens(
			abcc_build_content_prompt( array(), 'default', array(), $char_limit ),
			$char_limit,
			$model
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					// translators: %d is the number of available tokens
					__( 'Estimated available tokens: %d (after prompt tokens)', 'automated-blog-content-creator' ),
					$available_tokens
				),
			)
		);
	}
);
