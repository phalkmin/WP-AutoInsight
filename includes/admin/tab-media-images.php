<?php
/**
 * Media sub-tab: Images
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preferred_service = abcc_get_setting( 'preferred_image_service', 'auto' );
$openai_size       = abcc_get_setting( 'abcc_openai_image_size', '1024x1024' );
$openai_quality    = abcc_get_setting( 'abcc_openai_image_quality', 'standard' );
$gemini_model      = abcc_get_setting( 'abcc_gemini_image_model', 'gemini-2.5-flash-image' );
$gemini_size       = abcc_get_setting( 'abcc_gemini_image_size', '2K' );
$stability_size    = abcc_get_setting( 'abcc_stability_image_size', '1024x1024' );
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="images">

		<h2><?php esc_html_e( 'Featured Image Generation', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Generate Featured Images', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'When enabled, a featured image is automatically generated and attached to every new post.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="openai_generate_images" id="openai_generate_images"
							data-autosave-key="openai_generate_images"
							<?php checked( abcc_get_setting( 'openai_generate_images', true ) ); ?>>
						<?php esc_html_e( 'Generate featured image for each post', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Preferred Image Provider', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( '"Auto" selects the best image provider based on your text model. OpenAI uses DALL-E 3, Google uses Gemini image generation, Stability AI is always available as a fallback.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<fieldset>
						<?php
						$image_providers = array(
							'auto'      => __( 'Auto (follows text model)', 'automated-blog-content-creator' ),
							'openai'    => __( 'DALL-E (OpenAI)', 'automated-blog-content-creator' ),
							'gemini'    => __( 'Gemini (Google)', 'automated-blog-content-creator' ),
							'stability' => __( 'Stability AI', 'automated-blog-content-creator' ),
						);
						foreach ( $image_providers as $value => $label ) :
							?>
							<label class="abcc-label-block">
								<input type="radio" name="preferred_image_service" value="<?php echo esc_attr( $value ); ?>"
									data-autosave-key="preferred_image_service"
									<?php checked( $preferred_service, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Provider Options', 'automated-blog-content-creator' ); ?>
			<span class="description"><?php esc_html_e( '— showing settings for the active provider only', 'automated-blog-content-creator' ); ?></span>
		</h3>

		<div class="abcc-image-provider-options" id="abcc-provider-options-openai" <?php echo ( 'openai' !== $preferred_service && 'auto' !== $preferred_service ) ? 'style="display:none;"' : ''; ?>>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="abcc_openai_image_size"><?php esc_html_e( 'DALL-E Image Size', 'automated-blog-content-creator' ); ?></label>
						<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'DALL-E 3 supported resolutions.', 'automated-blog-content-creator' ) ) ); ?>
					</th>
					<td>
						<select id="abcc_openai_image_size" name="abcc_openai_image_size" data-autosave-key="abcc_openai_image_size">
							<option value="1024x1024" <?php selected( $openai_size, '1024x1024' ); ?>><?php esc_html_e( '1024×1024 (Square)', 'automated-blog-content-creator' ); ?></option>
							<option value="1792x1024" <?php selected( $openai_size, '1792x1024' ); ?>><?php esc_html_e( '1792×1024 (Wide)', 'automated-blog-content-creator' ); ?></option>
							<option value="1024x1792" <?php selected( $openai_size, '1024x1792' ); ?>><?php esc_html_e( '1024×1792 (Tall)', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_openai_image_quality"><?php esc_html_e( 'DALL-E Image Quality', 'automated-blog-content-creator' ); ?></label>
					</th>
					<td>
						<select id="abcc_openai_image_quality" name="abcc_openai_image_quality" data-autosave-key="abcc_openai_image_quality">
							<option value="standard" <?php selected( $openai_quality, 'standard' ); ?>><?php esc_html_e( 'Standard', 'automated-blog-content-creator' ); ?></option>
							<option value="hd" <?php selected( $openai_quality, 'hd' ); ?>><?php esc_html_e( 'HD', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div class="abcc-image-provider-options" id="abcc-provider-options-gemini" <?php echo 'gemini' !== $preferred_service ? 'style="display:none;"' : ''; ?>>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="abcc_gemini_image_model"><?php esc_html_e( 'Gemini Image Model', 'automated-blog-content-creator' ); ?></label>
					</th>
					<td>
						<select id="abcc_gemini_image_model" name="abcc_gemini_image_model" data-autosave-key="abcc_gemini_image_model">
							<option value="gemini-2.5-flash-image" <?php selected( $gemini_model, 'gemini-2.5-flash-image' ); ?>><?php esc_html_e( 'Flash (faster)', 'automated-blog-content-creator' ); ?></option>
							<option value="gemini-2.5-pro-image" <?php selected( $gemini_model, 'gemini-2.5-pro-image' ); ?>><?php esc_html_e( 'Pro (higher quality)', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="abcc_gemini_image_size"><?php esc_html_e( 'Gemini Image Resolution', 'automated-blog-content-creator' ); ?></label>
					</th>
					<td>
						<select id="abcc_gemini_image_size" name="abcc_gemini_image_size" data-autosave-key="abcc_gemini_image_size">
							<option value="1K" <?php selected( $gemini_size, '1K' ); ?>>1K</option>
							<option value="2K" <?php selected( $gemini_size, '2K' ); ?>>2K</option>
							<option value="4K" <?php selected( $gemini_size, '4K' ); ?>>4K</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div class="abcc-image-provider-options" id="abcc-provider-options-stability" <?php echo 'stability' !== $preferred_service ? 'style="display:none;"' : ''; ?>>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="abcc_stability_image_size"><?php esc_html_e( 'Stability AI Image Size', 'automated-blog-content-creator' ); ?></label>
					</th>
					<td>
						<select id="abcc_stability_image_size" name="abcc_stability_image_size" data-autosave-key="abcc_stability_image_size">
							<option value="1024x1024" <?php selected( $stability_size, '1024x1024' ); ?>><?php esc_html_e( '1024×1024 (Square)', 'automated-blog-content-creator' ); ?></option>
							<option value="1536x1024" <?php selected( $stability_size, '1536x1024' ); ?>><?php esc_html_e( '1536×1024 (Wide)', 'automated-blog-content-creator' ); ?></option>
							<option value="1024x1536" <?php selected( $stability_size, '1024x1536' ); ?>><?php esc_html_e( '1024×1536 (Tall)', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Alt Text', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Auto-generated alt text format: "{post title} – {primary keyword}". Improves SEO and accessibility.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_auto_alt_text" data-autosave-key="abcc_auto_alt_text"
							<?php checked( abcc_get_setting( 'abcc_auto_alt_text', true ) ); ?>>
						<?php esc_html_e( 'Auto-generate alt text ({title} – {keyword})', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Image Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
