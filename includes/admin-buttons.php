<?php
/**
 * Admin buttons functionality.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add meta box with rewrite button to post edit screen.
 */
function abcc_add_rewrite_meta_box() {
	$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );

	foreach ( $selected_post_types as $post_type ) {
		add_meta_box(
			'abcc-rewrite-meta-box',
			__( 'AI Content Tools', 'automated-blog-content-creator' ),
			'abcc_rewrite_meta_box_callback',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'abcc_add_rewrite_meta_box' );

/**
 * Meta box callback function.
 */
function abcc_rewrite_meta_box_callback( $post ) {
	// Add nonce field.
	wp_nonce_field( 'abcc_rewrite_post_nonce', 'abcc_rewrite_nonce' );
	?>
	<div style="padding: 10px 0;">
		<button type="button" id="abcc-rewrite-post" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
			<?php _e( 'Rewrite with AI', 'automated-blog-content-creator' ); ?>
		</button>
		<div id="abcc-rewrite-status" style="margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 3px; display: none;">
			<span class="dashicons dashicons-update"></span>
			<span id="abcc-status-text"><?php _e( 'Processing...', 'automated-blog-content-creator' ); ?></span>
		</div>
	</div>


	<script type="text/javascript">
	jQuery(document).ready(function($) {
		console.log('AI Content Tools loaded');
		
		$('#abcc-rewrite-post').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $status = $('#abcc-rewrite-status');
			const $statusText = $('#abcc-status-text');
			const postId = $('#post_ID').val();
			
			if (!confirm('<?php echo esc_js( __( 'Are you sure you want to rewrite this post? This will replace the current content.', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}
			
			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Rewriting...', 'automated-blog-content-creator' ) ); ?>');
			$status.show();
			$statusText.text('<?php echo esc_js( __( 'Analyzing content...', 'automated-blog-content-creator' ) ); ?>');
			
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
						$statusText.text('<?php echo esc_js( __( 'Success! Reloading page...', 'automated-blog-content-creator' ) ); ?>');
						$status.css('background', '#d4edda');
						
						// Reload after short delay.
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						$statusText.text('<?php echo esc_js( __( 'Error: ', 'automated-blog-content-creator' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'automated-blog-content-creator' ) ); ?>'));
						$status.css('background', '#f8d7da');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Rewrite with AI', 'automated-blog-content-creator' ) ); ?>');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', xhr.responseText);
					$statusText.text('<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
					$status.css('background', '#f8d7da');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Rewrite with AI', 'automated-blog-content-creator' ) ); ?>');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * Add "Create Post" button to the posts list screen.
 */
function abcc_add_create_post_button() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== $screen->base ) {
		return;
	}

	$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );
	if ( ! in_array( $screen->post_type, $selected_post_types, true ) ) {
		return;
	}
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Add button next to "Add New" button.
		$('.page-title-action').after(
			'<a href="#" id="abcc-create-post" class="page-title-action" data-post-type="<?php echo esc_attr( $screen->post_type ); ?>">' +
			'<?php esc_html_e( 'Create AI Post', 'automated-blog-content-creator' ); ?>' +
			'</a>'
		);
		
		// Handle button click.
		$(document).on('click', '#abcc-create-post', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const postType = $button.data('post-type') || 'post';
			
			if (!confirm('<?php echo esc_js( __( 'Create a new post using AI?', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}
			
			$button.text('<?php echo esc_js( __( 'Creating...', 'automated-blog-content-creator' ) ); ?>').css('pointer-events', 'none');
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abcc_create_post',
					nonce: '<?php echo wp_create_nonce( 'abcc_admin_buttons' ); ?>',
					post_type: postType
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						// Redirect to edit the new post.
						if (response.data.post_id) {
							window.location.href = '<?php echo admin_url( 'post.php?action=edit&post=' ); ?>' + response.data.post_id;
						}
					} else {
						alert(response.data.message || '<?php echo esc_js( __( 'An error occurred', 'automated-blog-content-creator' ) ); ?>');
					}
				},
				error: function() {
					alert('<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
				},
				complete: function() {
					$button.text('<?php echo esc_js( __( 'Create AI Post', 'automated-blog-content-creator' ) ); ?>').css('pointer-events', 'auto');
				}
			});
		});
	});
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'abcc_add_create_post_button' );

/**
 * Handle the AJAX request for rewriting a post.
 */
function abcc_handle_rewrite_post() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'abcc_rewrite_post_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed', 'automated-blog-content-creator' ) ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'automated-blog-content-creator' ) ) );
	}

	try {
		// Get the post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( __( 'Post not found', 'automated-blog-content-creator' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new Exception( __( 'Permission denied', 'automated-blog-content-creator' ) );
		}

		// Get settings.
		$api_key       = abcc_check_api_key();
		$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );
		$tone          = get_option( 'openai_tone', 'default' );
		$char_limit    = get_option( 'openai_char_limit', 200 );

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'API key not configured', 'automated-blog-content-creator' ) );
		}

		// Build rewrite prompt.
		$prompt = sprintf(
			"Rewrite this blog post to improve its SEO and readability while maintaining the core message.\n\n" .
			"Original Title: %s\n\n" .
			"Original Content:\n%s\n\n" .
			"Instructions:\n" .
			"- Keep the same tone and style\n" .
			"- Improve readability and structure\n" .
			"- Use proper HTML formatting (<h2>, <h3>, <p> tags)\n" .
			"- Maintain all key points and information\n" .
			"- Make it more engaging and SEO-friendly\n" .
			'- Use clear headings and better paragraph structure',
			$post->post_title,
			wp_strip_all_tags( $post->post_content )
		);

		// Generate new content.
		$result = abcc_generate_content(
			$api_key,
			$prompt,
			$prompt_select,
			$char_limit
		);

		if ( false === $result ) {
			throw new Exception( __( 'Failed to generate new content', 'automated-blog-content-creator' ) );
		}

		// Process the content.
		$content_array = array_filter(
			$result,
			function ( $line ) {
				return ! empty( trim( $line ) );
			}
		);

		$format_content = abcc_create_blocks( $content_array );
		$post_content   = abcc_gutenberg_blocks( $format_content );

		// Update the post.
		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_kses_post( $post_content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			throw new Exception( $updated->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Post rewritten successfully!', 'automated-blog-content-creator' ),
				'post_id' => $post_id,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
			)
		);
	}
}
add_action( 'wp_ajax_abcc_rewrite_post', 'abcc_handle_rewrite_post' );
