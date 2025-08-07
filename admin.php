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

	add_submenu_page(
		'automated-blog-content-creator-post',
		__( 'Text Settings', 'automated-blog-content-creator' ),
		__( 'Text Settings', 'automated-blog-content-creator' ),
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

	if ( ! empty( $openai_key ) ) {
		$options['openai'] = array(
			'group'   => 'OpenAI Models',
			'options' => array(
				// Cheap option.
				'gpt-3.5-turbo'       => array(
					'name'        => 'GPT-3.5 Turbo',
					'description' => 'Fast and cost-effective',
					'cost_tier'   => '1',
				),
				// Medium option.
				'gpt-4o'              => array(
					'name'        => 'GPT-4o',
					'description' => 'Fast, intelligent, flexible GPT model',
					'cost_tier'   => '2',
				),
				// Premium option.
				'gpt-4-turbo-preview' => array(
					'name'        => 'GPT-4 Turbo Preview',
					'description' => 'Largest and most capable GPT model',
					'cost_tier'   => '3',
				),
			),
		);
	}

	if ( ! empty( $claude_key ) ) {
		$options['claude'] = array(
			'group'   => 'Claude Models',
			'options' => array(
				// Cheap option.
				'claude-3-5-haiku-20241022'  => array(
					'name'        => 'Claude 3.5 Haiku',
					'description' => 'Fast and cost-effective',
					'cost_tier'   => '1',
				),
				// Medium option.
				'claude-3-7-sonnet-20250219' => array(
					'name'        => 'Claude 3.5 Sonnet',
					'description' => 'Improved balanced performance',
					'cost_tier'   => '2',
				),
				// Premium option.
				'claude-sonnet-4-20250514'   => array(
					'name'        => 'Claude Sonnet 4',
					'description' => 'Latest premium model with advanced capabilities',
					'cost_tier'   => '3',
				),
			),
		);
	}

	if ( ! empty( $gemini_key ) ) {
		$options['gemini'] = array(
			'group'   => 'Google Gemini Models',
			'options' => array(
				// Cheap option.
				'gemini-2.0-flash'       => array(
					'name'        => 'Gemini 2.0 Flash',
					'description' => 'Fast and versatile performance across diverse tasks',
					'cost_tier'   => '1',
				),
				// Medium option.
				'gemini-1.5-pro-latest'  => array(
					'name'        => 'Gemini 1.5 Pro',
					'description' => 'Complex reasoning with 2M token context window',
					'cost_tier'   => '2',
				),
				// Premium option.
				'gemini-2.5-pro-preview' => array(
					'name'        => 'Gemini 2.5 Pro',
					'description' => 'Most powerful Gemini model with advanced reasoning',
					'cost_tier'   => '3',
				),
			),
		);
	}

	return $options;
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
				update_option( 'openai_generate_seo', $openai_generate_seo );
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
				$auto_create                = isset( $_POST['openai_auto_create'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_auto_create'] ) ) : '';
				$char_limit                 = isset( $_POST['openai_char_limit'] ) ? absint( $_POST['openai_char_limit'] ) : 200;
				$openai_email_notifications = isset( $_POST['openai_email_notifications'] );
				$openai_generate_images     = isset( $_POST['openai_generate_images'] );
				$preferred_image_service    = isset( $_POST['preferred_image_service'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_image_service'] ) ) : 'auto';

				update_option( 'openai_api_key', $api_key );
				update_option( 'gemini_api_key', $gemini_api_key );
				update_option( 'claude_api_key', $claude_api_key );
				update_option( 'stability_api_key', $stability_api_key );
				update_option( 'openai_auto_create', $auto_create );
				update_option( 'openai_char_limit', $char_limit );
				update_option( 'openai_email_notifications', $openai_email_notifications );
				update_option( 'openai_generate_images', $openai_generate_images );
				update_option( 'preferred_image_service', $preferred_image_service );

				abcc_schedule_openai_event();

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
	wp_enqueue_style( 'wpai-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), '3.0.1' );
	wp_enqueue_script( 'wpai-admin-scripts', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), '3.0.1', true );

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
							printf(
								// Translators: %1$s is the schedule, %2$s is the next run date.
								'<p>' . esc_html__( 'Posts are scheduled to be automatically published %1$s and the next execution will be on %2$s.', 'automated-blog-content-creator' ) . '</p>',
								'<strong>' . esc_html( $schedule_info['schedule'] ) . '</strong>',
								esc_html( $schedule_info['next_run'] )
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
								<th scope="row"><?php esc_html_e( 'Keywords', 'automated-blog-content-creator' ); ?></th>
								<td>
									<input type="text" name="openai_keywords" value="<?php echo esc_attr( $keywords ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Categories', 'automated-blog-content-creator' ); ?></th>
								<td>
									<?php abcc_category_dropdown( $selected_categories ); ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Post Types', 'automated-blog-content-creator' ); ?></th>
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
								<th scope="row"><?php esc_html_e( 'Tone', 'automated-blog-content-creator' ); ?></th>
								<td>
									<select name="openai_tone" id="openai_tone">
										<option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional', 'automated-blog-content-creator' ); ?></option>
										<option value="casual" <?php selected( $tone, 'casual' ); ?>><?php esc_html_e( 'Casual', 'automated-blog-content-creator' ); ?></option>
										<option value="friendly" <?php selected( $tone, 'friendly' ); ?>><?php esc_html_e( 'Friendly', 'automated-blog-content-creator' ); ?></option>
										<option value="custom" <?php selected( $tone, 'custom' ); ?>><?php esc_html_e( 'Custom', 'automated-blog-content-creator' ); ?></option>
									</select>
									<div id="custom_tone_container" style="display: <?php echo $tone === 'custom' ? 'block' : 'none'; ?>; margin-top: 10px;">
										<input type="text" name="custom_tone" value="<?php echo esc_attr( $custom_tone_value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom tone', 'automated-blog-content-creator' ); ?>">
									</div>
								</td>
							</tr>
						</table>
						<?php submit_button(); ?>
						<button type="button" name="generate-post" id="generate-post" class="button button-secondary">
							<?php echo esc_attr__( 'Create post manually', 'automated-blog-content-creator' ); ?>
						</button>
					</form>
				</div>
				<?php elseif ( $current_tab === 'model-settings' ) : ?>
				<div class="tab-pane active">
					<form method="post" action="">
								<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
						<div class="model-selector">
								<?php
								$model_options          = abcc_get_ai_model_options();
								$current_selected_model = get_option( 'prompt_select', 'gpt-3.5-turbo' );
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
							<?php echo esc_html__( 'OpenAI API key:', 'automated-blog-content-creator' ); ?>
						</label></th>
						<td>
							<input type="text" id="openai_api_key" name="openai_api_key"
								value="<?php echo esc_attr( get_option( 'openai_api_key', '' ) ); ?>"
								class="regular-text">
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'OPENAI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your OpenAI API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'GEMINI_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="gemini_api_key">
							<?php echo esc_html__( 'Gemini API key:', 'automated-blog-content-creator' ); ?>
						</label></th>
						<td>
							<input type="text" id="gemini_api_key" name="gemini_api_key"
								value="<?php echo esc_attr( get_option( 'gemini_api_key', '' ) ); ?>"
								class="regular-text">
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'GEMINI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Gemini API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'CLAUDE_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="claude_api_key">
							<?php echo esc_html__( 'Claude API key:', 'automated-blog-content-creator' ); ?>
						</label></th>
						<td>
							<input type="text" id="claude_api_key" name="claude_api_key"
								value="<?php echo esc_attr( get_option( 'claude_api_key', '' ) ); ?>"
								class="regular-text">
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'CLAUDE_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Claude API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					</th></tr>
				<?php endif; ?>

					<?php if ( ! defined( 'STABILITY_API' ) ) : ?>
					<tr>
						<th scope="row"><label for="stability_api_key">
							<?php echo esc_html__( 'Stability AI API key (for image generation):', 'automated-blog-content-creator' ); ?>
						</label></th>
						<td>
							<input type="text" id="stability_api_key" name="stability_api_key"
								value="<?php echo esc_attr( get_option( 'stability_api_key', '' ) ); ?>"
								class="regular-text">
							<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'STABILITY_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<tr><th colspan="2">
						<strong><?php esc_html_e( 'Your Stability AI API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					</th></tr>
				<?php endif; ?>
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
			</table>

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
						<?php echo esc_html__( 'Max Token Limit', 'automated-blog-content-creator' ); ?>
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
			<div class="about-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 12px; margin-bottom: 30px; text-align: center;">
				<h2 style="margin: 0 0 10px 0; font-size: 2.5em; font-weight: 300;"><?php esc_html_e( 'WP-AutoInsight', 'automated-blog-content-creator' ); ?></h2>
				<p style="margin: 0; font-size: 1.2em; opacity: 0.9;"><?php esc_html_e( 'Revolutionizing Content Creation with AI', 'automated-blog-content-creator' ); ?></p>
				<div style="margin-top: 20px;">
					<span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 0.9em;">
						<?php esc_html_e( 'Version 3.0.0', 'automated-blog-content-creator' ); ?>
					</span>
				</div>
			</div>

			<div class="about-content" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
				<!-- Main Content -->
				<div class="about-main">
					<!-- What's New -->
					<div class="about-section" style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 25px;">
						<h3 style="color: #2c3e50; margin-top: 0;">
							<span class="dashicons dashicons-star-filled" style="color: #ffc107;"></span>
							<?php esc_html_e( "What's New in 3.0", 'automated-blog-content-creator' ); ?>
						</h3>
						<ul style="list-style: none; padding: 0;">
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-microphone" style="color: #28a745; margin-right: 8px;"></span>
								<?php esc_html_e( 'Audio Transcription with OpenAI Whisper', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-chart-bar" style="color: #17a2b8; margin-right: 8px;"></span>
								<?php esc_html_e( 'AI Infographic Generation', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-admin-appearance" style="color: #6f42c1; margin-right: 8px;"></span>
								<?php esc_html_e( 'Redesigned Admin Interface', 'automated-blog-content-creator' ); ?>
							</li>
							<li style="margin-bottom: 10px;">
								<span class="dashicons dashicons-superhero-alt" style="color: #fd7e14; margin-right: 8px;"></span>
								<?php esc_html_e( 'Latest AI Models (GPT-4, Claude Sonnet 4, Gemini 2.5)', 'automated-blog-content-creator' ); ?>
							</li>
						</ul>
					</div>

					<!-- Features Overview -->
					<div class="about-section" style="background: white; padding: 25px; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 25px;">
						<h3 style="color: #2c3e50; margin-top: 0;">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e( 'Key Features', 'automated-blog-content-creator' ); ?>
						</h3>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
							<div>
								<h4 style="color: #495057; margin-bottom: 10px;"><?php esc_html_e( 'AI Integration', 'automated-blog-content-creator' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px; color: #6c757d;">
									<li><?php esc_html_e( 'OpenAI (GPT-3.5, GPT-4, GPT-4o)', 'automated-blog-content-creator' ); ?></li>
									<li><?php esc_html_e( 'Claude (Haiku, Sonnet, Opus)', 'automated-blog-content-creator' ); ?></li>
									<li><?php esc_html_e( 'Google Gemini (Flash, Pro)', 'automated-blog-content-creator' ); ?></li>
								</ul>
							</div>
							<div>
								<h4 style="color: #495057; margin-bottom: 10px;"><?php esc_html_e( 'Content Tools', 'automated-blog-content-creator' ); ?></h4>
								<ul style="margin: 0; padding-left: 20px; color: #6c757d;">
									<li><?php esc_html_e( 'Automated Post Generation', 'automated-blog-content-creator' ); ?></li>
									<li><?php esc_html_e( 'SEO Optimization', 'automated-blog-content-creator' ); ?></li>
									<li><?php esc_html_e( 'Image & Infographic Creation', 'automated-blog-content-creator' ); ?></li>
								</ul>
							</div>
						</div>
					</div>

					<!-- Getting Started -->
					<div class="about-section" style="background: #e8f5e8; padding: 25px; border-radius: 8px; border-left: 4px solid #28a745;">
						<h3 style="color: #155724; margin-top: 0;">
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Getting Started', 'automated-blog-content-creator' ); ?>
						</h3>
						<ol style="color: #155724;">
							<li><?php esc_html_e( 'Get your API keys from OpenAI, Claude, or Gemini', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Configure them in Advanced Settings', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Set your keywords and tone in Content Settings', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Choose your AI model and start creating!', 'automated-blog-content-creator' ); ?></li>
						</ol>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="about-sidebar">
					<!-- Developer Info -->
					<div class="about-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center;">
						<div style="width: 80px; height: 80px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
							<span class="dashicons dashicons-admin-users" style="color: white; font-size: 2em;"></span>
						</div>
						<h3 style="margin: 0 0 10px 0; color: #2c3e50;"><?php esc_html_e( 'Paulo H. Alkmin', 'automated-blog-content-creator' ); ?></h3>
						<p style="color: #6c757d; margin: 0 0 15px 0; font-size: 0.9em;"><?php esc_html_e( 'AI & WordPress Consultant', 'automated-blog-content-creator' ); ?></p>
						<a href="mailto:phalkmin@protonmail.com" class="button button-secondary" style="margin-bottom: 10px; display: inline-block;">
							<span class="dashicons dashicons-email-alt"></span>
							<?php esc_html_e( 'Contact Me', 'automated-blog-content-creator' ); ?>
						</a>
					</div>

					<!-- Support Development -->
					<div class="about-card" style="background: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffeaa7; margin-bottom: 20px;">
						<h3 style="color: #856404; margin-top: 0; text-align: center;">
							<span class="dashicons dashicons-heart"></span>
							<?php esc_html_e( 'Support Development', 'automated-blog-content-creator' ); ?>
						</h3>
						<p style="color: #856404; text-align: center; margin-bottom: 15px; font-size: 0.9em;">
							<?php esc_html_e( 'Help keep this plugin free and constantly improving!', 'automated-blog-content-creator' ); ?>
						</p>
						<div style="text-align: center;">
							<a href="https://ko-fi.com/U7U1LM8AP" target="_blank" style="text-decoration: none;">
								<img src="https://storage.ko-fi.com/cdn/kofi3.png?v=3" alt="Buy Me a Coffee at ko-fi.com" style="height: 36px; border: 0;">
							</a>
						</div>
					</div>

					<!-- Review Request -->
					<div class="about-card" style="background: #d1ecf1; padding: 20px; border-radius: 8px; border: 1px solid #bee5eb; margin-bottom: 20px;">
						<h3 style="color: #0c5460; margin-top: 0; text-align: center;">
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Love WP-AutoInsight?', 'automated-blog-content-creator' ); ?>
						</h3>
						<p style="color: #0c5460; text-align: center; margin-bottom: 15px; font-size: 0.9em;">
							<?php esc_html_e( 'Your 5-star review helps other users discover this plugin!', 'automated-blog-content-creator' ); ?>
						</p>
						<div style="text-align: center;">
							<a href="https://wordpress.org/plugins/wp-autoinsight/#reviews" target="_blank" class="button button-primary">
								<?php esc_html_e( 'Write a Review', 'automated-blog-content-creator' ); ?>
							</a>
						</div>
					</div>

					<!-- Consulting Services -->
					<div class="about-card" style="background: #f8d7da; padding: 20px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px;">
						<h3 style="color: #721c24; margin-top: 0; text-align: center;">
							<span class="dashicons dashicons-businessman"></span>
							<?php esc_html_e( 'Need Custom AI Solutions?', 'automated-blog-content-creator' ); ?>
						</h3>
						<p style="color: #721c24; text-align: center; margin-bottom: 15px; font-size: 0.9em;">
							<?php esc_html_e( 'I offer AI integration and WordPress consulting services.', 'automated-blog-content-creator' ); ?>
						</p>
						<div style="text-align: center;">
							<a href="mailto:phalkmin@protonmail.com?subject=AI Consulting Inquiry" class="button">
								<?php esc_html_e( 'Get a Quote', 'automated-blog-content-creator' ); ?>
							</a>
						</div>
					</div>

					<!-- Quick Links -->
					<div class="about-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center;">
						<h3 style="color: #2c3e50; margin-top: 0;"><?php esc_html_e( 'Quick Links', 'automated-blog-content-creator' ); ?></h3>
						<ul style="list-style: none; padding: 0; margin: 0;">
							<li style="margin-bottom: 10px;">
								<a href="https://github.com/phalkmin/wp-autoinsight" target="_blank" style="text-decoration: none; color: #495057;">
									<span class="dashicons dashicons-admin-site-alt3"></span>
									<?php esc_html_e( 'GitHub Repository', 'automated-blog-content-creator' ); ?>
								</a>
							</li>
							<li style="margin-bottom: 10px;">
								<a href="https://wordpress.org/support/plugin/wp-autoinsight/" target="_blank" style="text-decoration: none; color: #495057;">
									<span class="dashicons dashicons-sos"></span>
									<?php esc_html_e( 'Support Forum', 'automated-blog-content-creator' ); ?>
								</a>
							</li>
							<li style="margin-bottom: 10px;">
								<a href="https://phalkmin.me" target="_blank" style="text-decoration: none; color: #495057;">
									<span class="dashicons dashicons-admin-home"></span>
									<?php esc_html_e( 'My Website', 'automated-blog-content-creator' ); ?>
								</a>
							</li>
							<li>
								<a href="mailto:phalkmin@protonmail.com" style="text-decoration: none; color: #495057;">
									<span class="dashicons dashicons-email"></span>
									<?php esc_html_e( 'Direct Contact', 'automated-blog-content-creator' ); ?>
								</a>
							</li>
						</ul>
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
									'<option value="%s" %s>%s</option>',
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

		// Add an admin notice to inform the user.
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
