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

			<div class="abcc-step-actions">
				<button class="button button-link abcc-skip-onboarding" id="abcc-skip-onboarding-2"><?php esc_html_e( 'Skip to settings', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-secondary abcc-prev-step" data-goto="1"><?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-primary abcc-next-step" data-step="2" data-goto="3"><?php esc_html_e( 'Continue', 'automated-blog-content-creator' ); ?></button>
			</div>
		</div>
	</div>
