<?php
/**
 * Onboarding success step.
 *
 * Markup partial included by abcc_show_onboarding_page(). Extracted verbatim
 * in 4.2.0 — bytes must stay identical to the pre-split render.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<!-- Success Step -->
			<div class="abcc-onboarding-step abcc-step-success" style="display: none;">
				<div class="abcc-step-content abcc-success-content">
					<div class="abcc-success-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<h2><?php esc_html_e( 'Congratulations! 🎉', 'automated-blog-content-creator' ); ?></h2>
					<p><?php esc_html_e( 'WP-AutoInsight is now set up and ready to help you create amazing content.', 'automated-blog-content-creator' ); ?></p>
					
					<div class="abcc-success-actions">
						<a href="?page=automated-blog-content-creator-post" class="button button-primary button-hero">
							<?php esc_html_e( 'Go to Dashboard', 'automated-blog-content-creator' ); ?>
						</a>
						<a href="?page=automated-blog-content-creator-post&tab=settings" class="button button-secondary">
							<?php esc_html_e( 'Go to Settings', 'automated-blog-content-creator' ); ?>
						</a>
					</div>

					<div class="abcc-next-steps">
						<h3><?php esc_html_e( 'What\'s Next?', 'automated-blog-content-creator' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Customize your keywords and categories', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Set up automated scheduling', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Try the audio transcription feature', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Generate infographics for existing posts', 'automated-blog-content-creator' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
