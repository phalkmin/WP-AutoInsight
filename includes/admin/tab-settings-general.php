<?php
/**
 * Settings sub-tab: General
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types          = get_post_types( array( 'public' => true ), 'objects' );
$selected_post_types = abcc_get_setting( 'abcc_selected_post_types', array( 'post' ) );
$char_limit          = abcc_get_setting( 'openai_char_limit', 200 );
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="general">

		<h2><?php esc_html_e( 'Post Types', 'automated-blog-content-creator' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Show AI generation tools on these post types.', 'automated-blog-content-creator' ); ?>
			<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Controls which post type edit screens show the "Generate with AI" meta boxes and buttons.', 'automated-blog-content-creator' ) ) ); ?>
		</p>
		<fieldset class="abcc-mb-20">
			<?php foreach ( $post_types as $pt ) : ?>
				<label class="abcc-label-block">
					<input type="checkbox" name="abcc_selected_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>"
						<?php checked( in_array( $pt->name, $selected_post_types, true ) ); ?>>
					<?php echo esc_html( $pt->label ); ?> <code><?php echo esc_html( $pt->name ); ?></code>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<h2><?php esc_html_e( 'Content Defaults', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="openai_char_limit"><?php esc_html_e( 'Content Length', 'automated-blog-content-creator' ); ?></label>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Target length in tokens. 200 ≈ 150 words; 800 ≈ 600 words; 2000 ≈ 1500 words. Higher values cost more per post.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<input type="range" id="openai_char_limit" name="openai_char_limit"
						data-autosave-key="openai_char_limit"
						min="100" max="4000" step="100"
						value="<?php echo esc_attr( $char_limit ); ?>"
						oninput="document.getElementById('abcc-char-limit-display').textContent = this.value + ' tokens'">
					<span id="abcc-char-limit-display"><?php echo esc_html( $char_limit ); ?> <?php esc_html_e( 'tokens', 'automated-blog-content-creator' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Draft First', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Save all generated posts as drafts. Recommended — lets you review content before it goes live.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_draft_first"
							data-autosave-key="abcc_draft_first"
							<?php checked( abcc_get_setting( 'abcc_draft_first', true ) ); ?>>
						<?php esc_html_e( 'Always save generated content as draft', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'SEO Metadata', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Generate SEO title, description, and focus keyword for each post using the active SEO plugin (Yoast or RankMath).', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="openai_generate_seo"
							data-autosave-key="openai_generate_seo"
							<?php checked( abcc_get_setting( 'openai_generate_seo', true ) ); ?>>
						<?php esc_html_e( 'Generate SEO metadata automatically', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save General Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
