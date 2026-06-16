<?php
/**
 * Onboarding step 3: first post generation.
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
			<!-- Step 3: First Post -->
			<div class="abcc-onboarding-step abcc-step-3">
				<div class="abcc-step-content">
					<h2><?php esc_html_e( 'Generate Your First Post', 'automated-blog-content-creator' ); ?></h2>
					<p><?php esc_html_e( 'Let\'s create your first AI-generated post to make sure everything is working perfectly.', 'automated-blog-content-creator' ); ?></p>

					<div class="abcc-first-post-preview">
						<div class="abcc-post-preview-card">
							<div class="abcc-post-icon">
								<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<h3><?php esc_html_e( 'Welcome Post', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'We\'ll create a "Hello World" style post to test your setup and show you how the plugin works.', 'automated-blog-content-creator' ); ?></p>
							<div class="abcc-post-features">
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'AI-generated content', 'automated-blog-content-creator' ); ?>
								</span>
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'SEO-optimized', 'automated-blog-content-creator' ); ?>
								</span>
								<?php if ( abcc_get_setting( 'openai_generate_images', true ) ) : ?>
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Featured image', 'automated-blog-content-creator' ); ?>
								</span>
							<?php endif; ?>
							</div>
						</div>
					</div>

					<div class="abcc-generation-status" id="abcc-generation-status" style="display: none;">
						<div class="abcc-loading-spinner"></div>
						<p id="abcc-generation-text"><?php esc_html_e( 'Generating your first post...', 'automated-blog-content-creator' ); ?></p>
					</div>

					<div class="abcc-step-actions">
						<button class="button button-secondary" id="abcc-prev-step-3">
							<?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?>
						</button>
						<button class="button button-primary button-hero" id="abcc-generate-first-post">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Generate First Post', 'automated-blog-content-creator' ); ?>
						</button>
					</div>
				</div>
			</div>
