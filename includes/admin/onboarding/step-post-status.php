<?php
/**
 * Onboarding step 2: default post status.
 *
 * Markup partial included by abcc_show_onboarding_page(). Extracted in 4.3.0.
 *
 * @package WP-AutoInsight
 * @since 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<div class="abcc-onboarding-step abcc-step-2" data-step="2" style="display:none;">
		<div class="abcc-step-content">
			<h2><?php esc_html_e( 'How should new posts be saved?', 'automated-blog-content-creator' ); ?></h2>
			<p><?php esc_html_e( 'You can change this anytime in Settings.', 'automated-blog-content-creator' ); ?></p>

			<fieldset class="abcc-onboarding-status">
				<label class="abcc-onboarding-radio">
					<input type="radio" name="abcc_onboarding_status" value="draft" checked>
					<strong><?php esc_html_e( 'Save as drafts', 'automated-blog-content-creator' ); ?></strong>
					<span><?php esc_html_e( 'Recommended for agencies — review before publishing.', 'automated-blog-content-creator' ); ?></span>
				</label>
				<label class="abcc-onboarding-radio">
					<input type="radio" name="abcc_onboarding_status" value="publish">
					<strong><?php esc_html_e( 'Publish immediately', 'automated-blog-content-creator' ); ?></strong>
					<span><?php esc_html_e( 'Posts go live as soon as they are generated.', 'automated-blog-content-creator' ); ?></span>
				</label>
			</fieldset>

			<div class="abcc-onboarding-language">
				<label for="abcc_onboarding_language">
					<strong><?php esc_html_e( 'Content language', 'automated-blog-content-creator' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'The language generated posts are written in. Defaults to your site language.', 'automated-blog-content-creator' ); ?></p>
				<select id="abcc_onboarding_language" name="abcc_onboarding_language">
					<?php $abcc_language = abcc_sanitize_content_language( abcc_get_setting( 'abcc_content_language', 'site' ) ); ?>
					<?php foreach ( abcc_get_content_language_choices() as $abcc_value => $abcc_label ) : ?>
						<option value="<?php echo esc_attr( $abcc_value ); ?>" <?php selected( $abcc_language, $abcc_value ); ?>>
							<?php echo esc_html( $abcc_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="abcc-step-actions">
				<button class="button button-link abcc-skip-onboarding" id="abcc-skip-onboarding-2"><?php esc_html_e( 'Skip to settings', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-secondary abcc-prev-step" data-goto="1"><?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-primary abcc-next-step" data-step="2" data-goto="3"><?php esc_html_e( 'Continue', 'automated-blog-content-creator' ); ?></button>
			</div>
		</div>
	</div>
