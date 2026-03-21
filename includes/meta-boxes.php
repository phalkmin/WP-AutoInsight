<?php
/**
 * Consolidated meta boxes for the post edit screen.
 *
 * @package WP-AutoInsight
 * @since 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register all plugin meta boxes.
 */
function abcc_register_meta_boxes() {
	$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );

	foreach ( $selected_post_types as $post_type ) {
		// AI Content Tools (Rewrite).
		add_meta_box(
			'abcc-rewrite-meta-box',
			__( 'AI Content Tools', 'automated-blog-content-creator' ),
			'abcc_rewrite_meta_box_callback',
			$post_type,
			'side',
			'high'
		);

		// AI Infographic Tools.
		add_meta_box(
			'abcc-infographic-meta-box',
			__( 'AI Infographic Tools', 'automated-blog-content-creator' ),
			'abcc_infographic_meta_box_callback',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'abcc_register_meta_boxes' );

/**
 * Meta box callback for AI Content Tools (Rewrite).
 *
 * @param WP_Post $post The post object.
 */
function abcc_rewrite_meta_box_callback( $post ) {
	// Add nonce field.
	wp_nonce_field( 'abcc_rewrite_post_nonce', 'abcc_rewrite_nonce' );
	?>
	<div style="padding: 10px 0;">
		<button type="button" id="abcc-rewrite-post" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
			<?php esc_html_e( 'Rewrite with AI', 'automated-blog-content-creator' ); ?>
		</button>
		<div id="abcc-rewrite-status" style="margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 3px; display: none;"></div>
		
		<?php
		$social_excerpt = get_post_meta( $post->ID, '_abcc_social_excerpt', true );
		if ( ! empty( $social_excerpt ) ) :
			?>
			<div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
				<label style="font-weight: 600; display: block; margin-bottom: 5px;"><?php esc_html_e( 'AI Social Excerpt:', 'automated-blog-content-creator' ); ?></label>
				<textarea readonly style="width: 100%; background: #f9f9f9; font-size: 12px; color: #666;" rows="3"><?php echo esc_textarea( $social_excerpt ); ?></textarea>
			</div>
		<?php endif; ?>

		<?php
		$gen_params_json = get_post_meta( $post->ID, '_abcc_generation_params', true );
		if ( ! empty( $gen_params_json ) ) :
			$gen_params = json_decode( $gen_params_json, true );
			if ( is_array( $gen_params ) ) :
				$keywords_display = ! empty( $gen_params['keywords'] ) ? implode( ', ', (array) $gen_params['keywords'] ) : '—';
				$model_display    = ! empty( $gen_params['model'] ) ? $gen_params['model'] : '—';
				$template_display = ! empty( $gen_params['template'] ) ? $gen_params['template'] : 'default';
				?>
				<div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
					<label style="font-weight: 600; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Last Generation:', 'automated-blog-content-creator' ); ?></label>
					<table style="width: 100%; font-size: 12px; color: #666; border-collapse: collapse;">
						<tr>
							<td style="padding: 2px 0; font-weight: 600; width: 60px;"><?php esc_html_e( 'Model:', 'automated-blog-content-creator' ); ?></td>
							<td style="padding: 2px 0;"><?php echo esc_html( $model_display ); ?></td>
						</tr>
						<tr>
							<td style="padding: 2px 0; font-weight: 600;"><?php esc_html_e( 'Template:', 'automated-blog-content-creator' ); ?></td>
							<td style="padding: 2px 0;"><?php echo esc_html( $template_display ); ?></td>
						</tr>
						<tr>
							<td style="padding: 2px 0; font-weight: 600; vertical-align: top;"><?php esc_html_e( 'Keywords:', 'automated-blog-content-creator' ); ?></td>
							<td style="padding: 2px 0;"><?php echo esc_html( $keywords_display ); ?></td>
						</tr>
					</table>
					<button type="button" id="abcc-regenerate-from-meta"
						class="button button-secondary"
						style="width: 100%; margin-top: 8px;"
						data-post-id="<?php echo esc_attr( $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'abcc_openai_generate_post' ) ); ?>">
						<?php esc_html_e( 'Regenerate as New Draft', 'automated-blog-content-creator' ); ?>
					</button>
					<div id="abcc-regenerate-meta-status" style="margin-top: 8px; display: none;"></div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#abcc-rewrite-post').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $status = $('#abcc-rewrite-status');
			const postId = $('#post_ID').val();
			
			if (!confirm('<?php echo esc_js( __( 'Are you sure you want to rewrite this post? This will replace the current content.', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}
			
			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Rewriting...', 'automated-blog-content-creator' ) ); ?>');
			abcc.showStatus($status, '<?php echo esc_js( __( 'Analyzing content...', 'automated-blog-content-creator' ) ); ?>');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abcc_rewrite_post',
					post_id: postId,
					nonce: $('#abcc_rewrite_nonce').val()
				},
				success: function(response) {
					if (response.success) {
						abcc.showStatus($status, '<?php echo esc_js( __( 'Success! Reloading page...', 'automated-blog-content-creator' ) ); ?>', 'success');
						$status.css('background', '#d4edda');
						
						// Reload after short delay.
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						abcc.setError($status, '<?php echo esc_js( __( 'Error: ', 'automated-blog-content-creator' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'automated-blog-content-creator' ) ); ?>'));
						$status.css('background', '#f8d7da');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Rewrite with AI', 'automated-blog-content-creator' ) ); ?>');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', xhr.responseText);
					abcc.setError($status, '<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
					$status.css('background', '#f8d7da');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Rewrite with AI', 'automated-blog-content-creator' ) ); ?>');
				}
			});
		});

	$('#abcc-regenerate-from-meta').on('click', function() {
		const $button = $(this);
		const $status = $('#abcc-regenerate-meta-status');
		const postId  = $button.data('post-id');
		const nonce   = $button.data('nonce');

		if (!confirm('<?php echo esc_js( __( 'Regenerate this post? A new draft will be created with the same parameters.', 'automated-blog-content-creator' ) ); ?>')) {
			return;
		}

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Regenerating\u2026', 'automated-blog-content-creator' ) ); ?>');
		abcc.showStatus($status, '<?php echo esc_js( __( 'Generating new draft\u2026', 'automated-blog-content-creator' ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'abcc_regenerate_post',
				post_id: postId,
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					abcc.showStatus($status, '<?php echo esc_js( __( 'Done! Opening new draft\u2026', 'automated-blog-content-creator' ) ); ?>', 'success');
					setTimeout(function() {
						window.location.href = response.data.edit_url;
					}, 1000);
				} else {
					abcc.setError($status, response.data.message || '<?php echo esc_js( __( 'An error occurred.', 'automated-blog-content-creator' ) ); ?>');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Regenerate as New Draft', 'automated-blog-content-creator' ) ); ?>');
				}
			},
			error: function() {
				abcc.setError($status, '<?php echo esc_js( __( 'Network error occurred.', 'automated-blog-content-creator' ) ); ?>');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Regenerate as New Draft', 'automated-blog-content-creator' ) ); ?>');
			}
		});
	});
	});
	</script>
	<?php
}

/**
 * Meta box callback for AI Infographic Tools.
 *
 * @param WP_Post $post The post object.
 */
function abcc_infographic_meta_box_callback( $post ) {
	// Only show for posts with content.
	if ( empty( trim( $post->post_content ) ) ) {
		echo '<p>' . esc_html__( 'Save the post with content first to generate an infographic.', 'automated-blog-content-creator' ) . '</p>';
		return;
	}

	// Add nonce field.
	wp_nonce_field( 'abcc_infographic_post_nonce', 'abcc_infographic_nonce' );
	?>
	<div style="padding: 10px 0;">
		<button type="button" id="abcc-create-infographic" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
			<?php esc_html_e( 'Create Infographic', 'automated-blog-content-creator' ); ?>
		</button>
		<div id="abcc-infographic-status" style="margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 3px; display: none;"></div>
	</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('#abcc-create-infographic').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $status = $('#abcc-infographic-status');
			const postId = $('#post_ID').val();
			
			if (!confirm('<?php echo esc_js( __( 'Create an infographic for this post?', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}
			
			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'automated-blog-content-creator' ) ); ?>');
			abcc.showStatus($status, '<?php echo esc_js( __( 'Generating infographic...', 'automated-blog-content-creator' ) ); ?>');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abcc_create_infographic',
					post_id: postId,
					nonce: $('#abcc_infographic_nonce').val()
				},
				success: function(response) {
					if (response.success) {
						abcc.showStatus($status, '<?php echo esc_js( __( 'Success! ', 'automated-blog-content-creator' ) ); ?>' + 
							'<a href="' + response.data.attachment_url + '" target="_blank"><?php echo esc_js( __( 'View', 'automated-blog-content-creator' ) ); ?></a> | ' +
							'<a href="' + ajaxurl.replace('admin-ajax.php', 'upload.php?item=' + response.data.attachment_id) + '"><?php echo esc_js( __( 'Edit', 'automated-blog-content-creator' ) ); ?></a>', 'success');
						$status.css('background', '#d4edda');
					} else {
						abcc.setError($status, '<?php echo esc_js( __( 'Error: ', 'automated-blog-content-creator' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'automated-blog-content-creator' ) ); ?>'));
						$status.css('background', '#f8d7da');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Create Infographic', 'automated-blog-content-creator' ) ); ?>');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', xhr.responseText);
					abcc.setError($status, '<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
					$status.css('background', '#f8d7da');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Create Infographic', 'automated-blog-content-creator' ) ); ?>');
				}
			});
		});
	});
	</script>
	<?php
}
