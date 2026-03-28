<?php
/**
 * Tab: About
 *
 * @package WP-AutoInsight
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
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
					printf( esc_html__( 'Version %s (Kabuto)', 'automated-blog-content-creator' ), '3.6.0' );
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
						<?php esc_html_e( "What's New in v3.6 (Kabuto)", 'automated-blog-content-creator' ); ?>
					</h3>
					<ul style="list-style: none; padding: 0;">
						<li style="margin-bottom: 12px;">
							<strong><?php esc_html_e( 'Bulk Generation:', 'automated-blog-content-creator' ); ?></strong>
							<?php esc_html_e( 'Generate multiple posts from a keyword list — what autoblogging SaaS tools charge $99/month for.', 'automated-blog-content-creator' ); ?>
						</li>
						<li style="margin-bottom: 12px;">
							<strong><?php esc_html_e( 'WordPress 7.0 Ready:', 'automated-blog-content-creator' ); ?></strong>
							<?php esc_html_e( 'Native Connectors support and prompt_ai capability — first content plugin with WP 7.0 integration.', 'automated-blog-content-creator' ); ?>
						</li>
						<li style="margin-bottom: 12px;">
							<strong><?php esc_html_e( 'Cleaner Admin:', 'automated-blog-content-creator' ); ?></strong>
							<?php esc_html_e( 'Settings extracted into focused partials — faster loads and the foundation for the v4.0 redesign.', 'automated-blog-content-creator' ); ?>
						</li>
						<li style="margin-bottom: 12px;">
							<strong><?php esc_html_e( 'Auditable Generation Log:', 'automated-blog-content-creator' ); ?></strong>
							<?php esc_html_e( 'Every post records its source (manual, scheduled, bulk, regenerate) so you can see exactly what ran and when.', 'automated-blog-content-creator' ); ?>
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
