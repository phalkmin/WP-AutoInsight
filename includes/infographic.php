<?php
/**
 * Infographic generation functionality
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add meta box with infographic button to post edit screen.
 */
function abcc_add_infographic_meta_box() {
	$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );

	foreach ( $selected_post_types as $post_type ) {
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
add_action( 'add_meta_boxes', 'abcc_add_infographic_meta_box' );

/**
 * Infographic meta box callback function.
 */
function abcc_infographic_meta_box_callback( $post ) {
	// Only show for posts with content
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
		<div id="abcc-infographic-status" style="margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 3px; display: none;">
			<span class="dashicons dashicons-update"></span>
			<span id="abcc-infographic-status-text"><?php esc_html_e( 'Processing...', 'automated-blog-content-creator' ); ?></span>
		</div>
	</div>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		console.log('AI Infographic Tools loaded');
		
		$('#abcc-create-infographic').on('click', function(e) {
			e.preventDefault();
			
			const $button = $(this);
			const $status = $('#abcc-infographic-status');
			const $statusText = $('#abcc-infographic-status-text');
			const postId = $('#post_ID').val();
			
			if (!confirm('<?php echo esc_js( __( 'Create an infographic for this post?', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}
			
			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'automated-blog-content-creator' ) ); ?>');
			$status.show();
			$statusText.text('<?php echo esc_js( __( 'Generating infographic...', 'automated-blog-content-creator' ) ); ?>');
			
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
						$statusText.html('<?php echo esc_js( __( 'Success! ', 'automated-blog-content-creator' ) ); ?>' + 
							'<a href="' + response.data.attachment_url + '" target="_blank"><?php echo esc_js( __( 'View', 'automated-blog-content-creator' ) ); ?></a> | ' +
							'<a href="' + ajaxurl.replace('admin-ajax.php', 'upload.php?item=' + response.data.attachment_id) + '"><?php echo esc_js( __( 'Edit', 'automated-blog-content-creator' ) ); ?></a>');
						$status.css('background', '#d4edda');
					} else {
						$statusText.text('<?php echo esc_js( __( 'Error: ', 'automated-blog-content-creator' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'automated-blog-content-creator' ) ); ?>'));
						$status.css('background', '#f8d7da');
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Create Infographic', 'automated-blog-content-creator' ) ); ?>');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', xhr.responseText);
					$statusText.text('<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
					$status.css('background', '#f8d7da');
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Create Infographic', 'automated-blog-content-creator' ) ); ?>');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * Handle infographic generation AJAX request
 */
function abcc_handle_create_infographic() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'abcc_infographic_post_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed', 'automated-blog-content-creator' ) ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post or permission denied', 'automated-blog-content-creator' ) ) );
	}

	try {
		// Get post content
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( __( 'Post not found', 'automated-blog-content-creator' ) );
		}

		// Get API settings
		$api_key = abcc_check_api_key();
		$model   = get_option( 'prompt_select', 'gpt-3.5-turbo' );

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'API key not configured', 'automated-blog-content-creator' ) );
		}

		// Step 1: Generate infographic description
		$description_prompt = sprintf(
			"You are a professional infographic creator. Analyze this blog post content and create a detailed visual description for an infographic that summarizes the key points:\n\n" .
			"Title: %s\n\n" .
			"Content: %s\n\n" .
			"Create a visual description that includes:\n" .
			"- Main visual elements and layout\n" .
			"- Key data points or statistics to highlight\n" .
			"- Color scheme suggestions\n" .
			"- Icons or symbols to use\n" .
			'Keep it concise and focused on visual elements.',
			$post->post_title,
			wp_strip_all_tags( $post->post_content )
		);

		// Generate description using existing content generation function
		$description_result = abcc_generate_content( $api_key, $description_prompt, $model, 300 );

		if ( ! $description_result || empty( $description_result ) ) {
			throw new Exception( __( 'Failed to generate infographic description', 'automated-blog-content-creator' ) );
		}

		// Combine description lines
		$infographic_description = implode( ' ', array_filter( $description_result ) );

		// Step 2: Generate image from description
		$image_prompt = 'Create an infographic based on this description: ' . $infographic_description . '. Style: modern, clean, professional infographic design.';

		// Use existing image generation infrastructure
		$image_service = abcc_determine_image_service( $model );

		if ( empty( $image_service['service'] ) ) {
			throw new Exception( __( 'No image generation service available', 'automated-blog-content-creator' ) );
		}

		$image_url = false;

		// Generate image using determined service
		if ( 'openai' === $image_service['service'] ) {
			$images = abcc_openai_generate_images( $image_service['api_key'], $image_prompt, 1, '1792x1024' );
			if ( ! empty( $images ) && is_array( $images ) ) {
				$image_url = $images[0];
			}
		} elseif ( 'stability' === $image_service['service'] ) {
			$image_url = abcc_stability_generate_images( $image_prompt, 1, $image_service['api_key'] );
		}

		if ( ! $image_url ) {
			throw new Exception( __( 'Failed to generate infographic image', 'automated-blog-content-creator' ) );
		}

		// Step 3: Save to media library
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download and attach the image
		$attachment_id = media_sideload_image( $image_url, $post_id, 'Infographic for: ' . $post->post_title, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new Exception( $attachment_id->get_error_message() );
		}

		// Add metadata to identify as infographic
		update_post_meta( $attachment_id, '_abcc_infographic', true );
		update_post_meta( $attachment_id, '_abcc_infographic_post_id', $post_id );

		// Get the attachment URL
		$attachment_url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success(
			array(
				'message'        => __( 'Infographic created successfully!', 'automated-blog-content-creator' ),
				'attachment_id'  => $attachment_id,
				'attachment_url' => $attachment_url,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_create_infographic', 'abcc_handle_create_infographic' );
