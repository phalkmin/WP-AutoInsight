<?php
/**
 * Consolidated meta boxes for the post edit screen.
 *
 * @package WP-AutoInsight
 * @since 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the single WP-AutoInsight Tools meta box.
 */
function abcc_register_meta_boxes() {
	if ( ! abcc_current_user_can_prompt() ) {
		return;
	}

	$selected_post_types = abcc_get_setting( 'abcc_selected_post_types', array( 'post' ) );

	foreach ( $selected_post_types as $post_type ) {
		add_meta_box(
			'abcc-ai-tools-meta-box',
			__( 'WP-AutoInsight Tools', 'automated-blog-content-creator' ),
			'abcc_ai_tools_meta_box_callback',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'abcc_register_meta_boxes' );

/**
 * Callback for the WP-AutoInsight Tools meta box.
 *
 * @param WP_Post $post The current post object.
 */
function abcc_ai_tools_meta_box_callback( $post ) {
	$has_content     = ! empty( trim( $post->post_content ) );
	$social_excerpt  = get_post_meta( $post->ID, '_abcc_social_excerpt', true );
	$gen_params_json = get_post_meta( $post->ID, '_abcc_generation_params', true );
	$gen_params      = ! empty( $gen_params_json ) ? json_decode( $gen_params_json, true ) : null;
	?>
	<div class="abcc-meta-box">

		<div class="abcc-meta-section">
			<h4 class="abcc-meta-section-title"><?php esc_html_e( 'Content', 'automated-blog-content-creator' ); ?></h4>

			<button type="button" id="abcc-rewrite-post"
				class="button button-secondary abcc-meta-btn"
				data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'abcc_rewrite_post_nonce' ) ); ?>">
				<?php esc_html_e( 'Rewrite with AI', 'automated-blog-content-creator' ); ?>
			</button>
			<div id="abcc-rewrite-status" class="abcc-meta-status"></div>

			<?php
			if ( is_array( $gen_params ) ) :
				$keywords_display = ! empty( $gen_params['keywords'] ) ? implode( ', ', (array) $gen_params['keywords'] ) : '—';
				$model_display    = ! empty( $gen_params['model'] ) ? $gen_params['model'] : '—';
				$template_display = ! empty( $gen_params['template'] ) ? $gen_params['template'] : 'default';
				?>
				<div class="abcc-meta-last-gen">
					<span class="abcc-meta-last-gen-label"><?php esc_html_e( 'Last Generation', 'automated-blog-content-creator' ); ?></span>
					<table class="abcc-meta-gen-table">
						<tr>
							<th><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></th>
							<td><?php echo esc_html( $model_display ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Template', 'automated-blog-content-creator' ); ?></th>
							<td><?php echo esc_html( $template_display ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Keywords', 'automated-blog-content-creator' ); ?></th>
							<td><?php echo esc_html( $keywords_display ); ?></td>
						</tr>
					</table>
					<button type="button" id="abcc-regenerate-from-meta"
						class="button button-secondary abcc-meta-btn"
						data-post-id="<?php echo esc_attr( $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'abcc_openai_generate_post' ) ); ?>">
						<?php esc_html_e( 'Regenerate as New Draft', 'automated-blog-content-creator' ); ?>
					</button>
					<div id="abcc-regenerate-meta-status" class="abcc-meta-status"></div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $social_excerpt ) ) : ?>
				<div class="abcc-meta-social-excerpt">
					<label class="abcc-meta-social-label"><?php esc_html_e( 'AI Social Excerpt', 'automated-blog-content-creator' ); ?></label>
					<textarea class="abcc-meta-social-textarea" readonly rows="3"><?php echo esc_textarea( $social_excerpt ); ?></textarea>
				</div>
			<?php endif; ?>
		</div>

		<hr class="abcc-meta-section-divider">

		<div class="abcc-meta-section">
			<h4 class="abcc-meta-section-title"><?php esc_html_e( 'Infographic', 'automated-blog-content-creator' ); ?></h4>

			<button type="button" id="abcc-create-infographic"
				class="button button-secondary abcc-meta-btn"
				data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'abcc_infographic_post_nonce' ) ); ?>"
				<?php disabled( ! $has_content ); ?>>
				<?php esc_html_e( 'Create Infographic', 'automated-blog-content-creator' ); ?>
			</button>
			<?php if ( ! $has_content ) : ?>
				<p class="abcc-meta-section-note">
					<?php esc_html_e( 'Save the post with content first.', 'automated-blog-content-creator' ); ?>
				</p>
			<?php endif; ?>
			<div id="abcc-infographic-status" class="abcc-meta-status"></div>
		</div>

	</div>
	<?php
}
