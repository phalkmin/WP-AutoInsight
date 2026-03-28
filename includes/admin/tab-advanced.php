<?php
/**
 * Tab: Advanced Settings
 *
 * @package WP-AutoInsight
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		
		<h2><?php esc_html_e( 'API Configuration', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
				<?php if ( defined( 'OPENAI_API' ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'Your OpenAI API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status" data-provider="openai"></span>
				</th></tr>
			<?php elseif ( ! empty( abcc_get_wp_ai_credential( 'openai' ) ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'OpenAI: connected via WordPress AI Connectors.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status verified" data-provider="openai">&#10003; <?php esc_html_e( 'Active', 'automated-blog-content-creator' ); ?></span>
					<p class="description"><?php esc_html_e( 'This key is managed by WordPress. To change it, go to Settings &rarr; AI Connectors.', 'automated-blog-content-creator' ); ?></p>
				</th></tr>
			<?php else : ?>
				<tr>
					<th scope="row"><label for="openai_api_key">
						<?php echo esc_html__( 'OpenAI API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at platform.openai.com', 'automated-blog-content-creator' ) ) ); ?>
					</label></th>
					<td>
						<input type="password" id="openai_api_key" name="openai_api_key"
							value="<?php echo esc_attr( get_option( 'openai_api_key', '' ) ); ?>"
							class="regular-text">
						<?php $last_v = get_transient( 'abcc_last_validation_openai' ); ?>
						<span class="api-validation-status<?php echo $last_v ? ' ' . esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ) : ''; ?>" data-provider="openai">
							<?php
							if ( $last_v ) {
								echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); }
							?>
						</span>
						<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'OPENAI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>

				<?php if ( defined( 'GEMINI_API' ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'Your Gemini API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status" data-provider="gemini"></span>
				</th></tr>
			<?php elseif ( ! empty( abcc_get_wp_ai_credential( 'gemini' ) ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'Google Gemini: connected via WordPress AI Connectors.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status verified" data-provider="gemini">&#10003; <?php esc_html_e( 'Active', 'automated-blog-content-creator' ); ?></span>
					<p class="description"><?php esc_html_e( 'This key is managed by WordPress. To change it, go to Settings &rarr; AI Connectors.', 'automated-blog-content-creator' ); ?></p>
				</th></tr>
			<?php else : ?>
				<tr>
					<th scope="row"><label for="gemini_api_key">
						<?php echo esc_html__( 'Gemini API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at aistudio.google.com', 'automated-blog-content-creator' ) ) ); ?>
					</label></th>
					<td>
						<input type="password" id="gemini_api_key" name="gemini_api_key"
							value="<?php echo esc_attr( get_option( 'gemini_api_key', '' ) ); ?>"
							class="regular-text">
						<?php $last_v = get_transient( 'abcc_last_validation_gemini' ); ?>
						<span class="api-validation-status<?php echo $last_v ? ' ' . esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ) : ''; ?>" data-provider="gemini">
							<?php
							if ( $last_v ) {
								echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); }
							?>
						</span>
						<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'GEMINI_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>

				<?php if ( defined( 'CLAUDE_API' ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'Your Claude API key is already set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status" data-provider="claude"></span>
				</th></tr>
			<?php elseif ( ! empty( abcc_get_wp_ai_credential( 'claude' ) ) ) : ?>
				<tr><th colspan="2">
					<strong><?php esc_html_e( 'Claude (Anthropic): connected via WordPress AI Connectors.', 'automated-blog-content-creator' ); ?></strong>
					<span class="api-validation-status verified" data-provider="claude">&#10003; <?php esc_html_e( 'Active', 'automated-blog-content-creator' ); ?></span>
					<p class="description"><?php esc_html_e( 'This key is managed by WordPress. To change it, go to Settings &rarr; AI Connectors.', 'automated-blog-content-creator' ); ?></p>
				</th></tr>
			<?php else : ?>
				<tr>
					<th scope="row"><label for="claude_api_key">
						<?php echo esc_html__( 'Claude API key:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Get your key at console.anthropic.com', 'automated-blog-content-creator' ) ) ); ?>
					</label></th>
					<td>
						<input type="password" id="claude_api_key" name="claude_api_key"
							value="<?php echo esc_attr( get_option( 'claude_api_key', '' ) ); ?>"
							class="regular-text">
						<?php $last_v = get_transient( 'abcc_last_validation_claude' ); ?>
						<span class="api-validation-status<?php echo $last_v ? ' ' . esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ) : ''; ?>" data-provider="claude">
							<?php
							if ( $last_v ) {
								echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); }
							?>
						</span>
						<p class="description"><?php esc_html_e( 'For extra security, add to wp-config.php using define(\'CLAUDE_API\', \'your-key\');', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
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
						<?php $last_v = get_transient( 'abcc_last_validation_perplexity' ); ?>
						<span class="api-validation-status<?php echo $last_v ? ' ' . esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ) : ''; ?>" data-provider="perplexity">
							<?php
							if ( $last_v ) {
								echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); }
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
						<?php $last_v = get_transient( 'abcc_last_validation_stability' ); ?>
						<span class="api-validation-status<?php echo $last_v ? ' ' . esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ) : ''; ?>" data-provider="stability">
							<?php
							if ( $last_v ) {
								echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); }
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
					</label></th>
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
		<h2 id="scheduling-settings"><?php esc_html_e( 'Automation & Scheduling', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="openai_auto_create">
					<?php echo esc_html__( 'Schedule post creation:', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'How often the plugin should automatically generate and publish a new post using your keyword groups.', 'automated-blog-content-creator' ) ) ); ?>
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
					<?php echo esc_html__( 'Content Length', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Higher values produce longer, more detailed content and use more API credits.', 'automated-blog-content-creator' ) ) ); ?>
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

			<h2><?php esc_html_e( 'User Permissions', 'automated-blog-content-creator' ); ?></h2>
			<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'AI Prompting Capability', 'automated-blog-content-creator' ); ?></th>
				<td>
					<p>
						<?php
						$can_prompt = abcc_current_user_can_prompt();
						if ( $can_prompt ) {
							echo '<span class="dashicons dashicons-yes" style="color: #46b450;"></span> ' . esc_html__( 'You have permission to generate content.', 'automated-blog-content-creator' );
						} else {
							echo '<span class="dashicons dashicons-no" style="color: #dc3232;"></span> ' . esc_html__( 'You do not have permission to generate content.', 'automated-blog-content-creator' );
						}
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'In WordPress 7.0+, permissions are controlled via the native "prompt_ai" capability. By default, Administrators have this capability.', 'automated-blog-content-creator' ); ?>
					</p>
				</td>
			</tr>
			</table>
				<?php submit_button( __( 'Save Advanced Settings', 'automated-blog-content-creator' ) ); ?>
			</form>
			</div>
