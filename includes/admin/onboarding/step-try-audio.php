<?php
/**
 * Onboarding step 4: discover the Record / audio path.
 *
 * Markup partial included by abcc_show_onboarding_page(). Extracted in 4.3.0.
 * No upload is forced here — this is a lightweight explainer with a direct
 * link to the Media ▸ Audio tab so users can explore at their own pace.
 * The primary Finish button calls abcc_onboarding_complete and advances to
 * the success screen.
 *
 * @package WP-AutoInsight
 * @since 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$audio_tab_url = add_query_arg(
	array(
		'page'   => 'automated-blog-content-creator-post',
		'tab'    => 'media',
		'subtab' => 'audio',
	),
	admin_url( 'admin.php' )
);
?>
	<div class="abcc-onboarding-step abcc-step-4" data-step="4" style="display:none;">
		<div class="abcc-step-content">
			<h2><?php esc_html_e( 'Turn recordings into posts', 'automated-blog-content-creator' ); ?></h2>
			<p><?php esc_html_e( 'WP-AutoInsight can transcribe audio recordings and turn them into fully formatted posts — great for podcasters, speakers, and note-takers.', 'automated-blog-content-creator' ); ?></p>
			<p><?php esc_html_e( 'Upload an audio file, let AI transcribe it, and get a draft ready to review. No microphone setup required right now.', 'automated-blog-content-creator' ); ?></p>

			<p>
				<a href="<?php echo esc_url( $audio_tab_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Go to Media ▸ Audio', 'automated-blog-content-creator' ); ?>
				</a>
			</p>

			<div class="abcc-step-actions">
				<button class="button button-link abcc-skip-onboarding" id="abcc-skip-onboarding-4"><?php esc_html_e( 'Skip to settings', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-secondary abcc-prev-step" data-goto="3"><?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-primary" id="abcc-onboarding-finish"><?php esc_html_e( 'Finish setup', 'automated-blog-content-creator' ); ?></button>
			</div>
		</div>
	</div>
