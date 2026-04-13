<?php
/**
 * Media sub-tab: Infographics
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="infographics">

		<h2><?php esc_html_e( 'Infographic Generation', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Enable Infographic Generation', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'When enabled, a "Create Infographic" button appears in the post editor sidebar for published posts.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_enable_infographics"
							data-autosave-key="abcc_enable_infographics"
							<?php checked( abcc_get_setting( 'abcc_enable_infographics', true ) ); ?>>
						<?php esc_html_e( 'Enable infographic generation feature', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Image Provider', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Which image provider to use for infographic generation. "Follow featured image setting" uses whatever is configured in the Images sub-tab.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<select name="abcc_infographic_provider" data-autosave-key="abcc_infographic_provider">
						<option value="auto" <?php selected( abcc_get_setting( 'abcc_infographic_provider', 'auto' ), 'auto' ); ?>>
							<?php esc_html_e( 'Follow Featured Image setting', 'automated-blog-content-creator' ); ?>
						</option>
						<option value="openai" <?php selected( abcc_get_setting( 'abcc_infographic_provider', 'auto' ), 'openai' ); ?>>
							<?php esc_html_e( 'DALL-E (OpenAI)', 'automated-blog-content-creator' ); ?>
						</option>
						<option value="stability" <?php selected( abcc_get_setting( 'abcc_infographic_provider', 'auto' ), 'stability' ); ?>>
							<?php esc_html_e( 'Stability AI', 'automated-blog-content-creator' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>

		<div class="abcc-how-it-works">
			<h3><?php esc_html_e( 'How it works', 'automated-blog-content-creator' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Open any published post in the editor.', 'automated-blog-content-creator' ); ?></li>
				<li><?php esc_html_e( 'Click "Create Infographic" in the post editor sidebar.', 'automated-blog-content-creator' ); ?></li>
				<li><?php esc_html_e( 'The AI analyzes the post and generates a visual infographic.', 'automated-blog-content-creator' ); ?></li>
				<li><?php esc_html_e( 'The result is saved to your Media Library.', 'automated-blog-content-creator' ); ?></li>
			</ol>
		</div>

		<?php submit_button( __( 'Save Infographic Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
