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

$post_types = get_post_types( array( 'public' => true ), 'objects' );
unset( $post_types['attachment'] );
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
		<p class="description"><?php esc_html_e( 'At least one post type is required.', 'automated-blog-content-creator' ); ?></p>

		<h2><?php esc_html_e( 'Content Defaults', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="openai_char_limit"><?php esc_html_e( 'Content Length', 'automated-blog-content-creator' ); ?></label>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'How long a post should be. The model is asked to aim for this many words and to finish inside it. Longer posts cost more: the estimate under the slider follows your choice.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td class="abcc-cost-scope">
					<input type="range" id="openai_char_limit" name="openai_char_limit"
						data-autosave-key="openai_char_limit"
						data-abcc-cost-length
						data-abcc-words-display="#abcc-char-limit-display"
						min="100" max="4000" step="100"
						value="<?php echo esc_attr( $char_limit ); ?>">
					<span id="abcc-char-limit-display" data-template="<?php /* translators: %s: approximate word count */ echo esc_attr__( 'about %s words', 'automated-blog-content-creator' ); ?>">
						<?php
						printf(
							/* translators: %s: approximate word count */
							esc_html__( 'about %s words', 'automated-blog-content-creator' ),
							esc_html( (string) abcc_get_word_target( (int) $char_limit ) )
						);
						?>
					</span>
					<?php abcc_render_cost_estimate( (int) $char_limit, '', 'cost' ); ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'New Post Status', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'How generated posts are saved. Draft is recommended — it lets you review content before it goes live.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<?php $abcc_post_status = abcc_sanitize_post_status( abcc_get_setting( 'abcc_default_post_status', 'draft' ) ); ?>
					<label>
						<input type="radio" name="abcc_default_post_status" value="draft"
							data-autosave-key="abcc_default_post_status"
							<?php checked( 'draft', $abcc_post_status ); ?>>
						<?php esc_html_e( 'Save as draft for review', 'automated-blog-content-creator' ); ?>
					</label>
					<br>
					<label>
						<input type="radio" name="abcc_default_post_status" value="publish"
							data-autosave-key="abcc_default_post_status"
							<?php checked( 'publish', $abcc_post_status ); ?>>
						<?php esc_html_e( 'Publish immediately', 'automated-blog-content-creator' ); ?>
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
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Answer-focused structure', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Structures posts for AI answer engines. It does not promise rankings or citations, and search engines need no special markup for their AI features.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_answer_engine_mode"
							data-autosave-key="abcc_answer_engine_mode"
							<?php checked( abcc_resolve_answer_mode() ); ?>>
						<?php esc_html_e( 'Use question headings, key takeaways and a short FAQ', 'automated-blog-content-creator' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Question headings answered up front, three key takeaways and a short FAQ, all inside the post length. Best for how-to and reference posts; leave it off for opinion, news and announcements. Not applied to posts under about 400 words. Topics can override this.', 'automated-blog-content-creator' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save General Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
