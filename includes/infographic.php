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
 * Prompt for the infographic's visual description, in the content language.
 *
 * No ABCC_CONTENT_FORMAT_REQUIREMENTS: the output is a scene description,
 * not post HTML.
 *
 * @since 4.5.2
 * @param string $title        Post title.
 * @param string $content_text Plain-text post body, already bounded.
 * @return string
 */
function abcc_build_infographic_description_prompt( $title, $content_text ) {
	return sprintf(
		"You are a professional infographic creator. Analyze this blog post content and create a detailed visual description for an infographic that summarizes the key points:\n\n" .
		"Title: %1\$s\n\n" .
		"Content: %2\$s\n\n" .
		"Create a visual description that includes:\n" .
		"- Main visual elements and layout\n" .
		"- Key data points or statistics to highlight\n" .
		"- Color scheme suggestions\n" .
		"- Icons or symbols to use\n" .
		'Keep it concise and focused on visual elements. Write the description in %3$s.',
		$title,
		$content_text,
		abcc_resolve_content_language()
	);
}

/**
 * Register a generated infographic as an attachment of the post.
 *
 * Generated images live in our uploads dir; attaching from the path avoids
 * the loopback request that fails on local and basic-auth sites. The post's
 * featured image is never touched.
 *
 * @since 4.5.2
 * @param int    $post_id   Post ID.
 * @param string $image_url URL returned by the image provider.
 * @param string $title     Attachment title.
 * @return int|WP_Error Attachment ID.
 */
function abcc_attach_infographic_to_post( $post_id, $image_url, $title ) {
	$attachment_id = false;
	$uploads       = wp_upload_dir();

	if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $image_url, $uploads['baseurl'] ) ) {
		$local_path = $uploads['basedir'] . substr( $image_url, strlen( $uploads['baseurl'] ) );
		if ( ! file_exists( $local_path ) ) {
			return new WP_Error( 'abcc_infographic_missing_file', __( 'The infographic was generated but its file could not be found.', 'automated-blog-content-creator' ) );
		}
		$attachment_id = abcc_attach_local_image_to_post(
			$post_id,
			$local_path,
			'',
			array(
				'title'         => $title,
				'set_thumbnail' => false,
			)
		);
	} else {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
	}

	if ( ! $attachment_id ) {
		return new WP_Error( 'abcc_infographic_attach_failed', __( 'The infographic could not be added to the Media Library.', 'automated-blog-content-creator' ) );
	}

	update_post_meta( $attachment_id, '_abcc_infographic', true );
	update_post_meta( $attachment_id, '_abcc_infographic_post_id', (int) $post_id );

	return (int) $attachment_id;
}

/**
 * Handle infographic generation AJAX request.
 */
function abcc_handle_create_infographic() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'abcc_infographic_post_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed', 'automated-blog-content-creator' ) ) );
		return;
	}

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'automated-blog-content-creator' ) ) );
		return;
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post or permission denied', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		// Get post content.
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( __( 'Post not found', 'automated-blog-content-creator' ) );
		}

		// Get API settings.
		$api_key = abcc_check_api_key();
		$model   = abcc_get_setting( 'prompt_select', abcc_get_default_text_model() );

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'API key not configured', 'automated-blog-content-creator' ) );
		}

		// Step 1: Generate infographic description.
		$description_prompt = abcc_build_infographic_description_prompt(
			$post->post_title,
			abcc_bound_prompt_input( wp_strip_all_tags( $post->post_content ) )
		);

		$description_result = abcc_generate_content_detailed( $api_key, $description_prompt, $model, 300, array( 'source' => 'infographic' ) );

		if ( is_wp_error( $description_result['error'] ) || empty( $description_result['content'] ) ) {
			throw new Exception(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- plain-text message from the error formatter.
				abcc_format_generation_error(
					$description_result['error'],
					array(
						'provider' => abcc_get_provider_for_model( $model ),
						'model'    => $model,
					)
				)
			);
		}

		$infographic_description = implode( ' ', array_filter( array_map( 'trim', $description_result['content'] ), 'strlen' ) );

		// Step 2: Generate image from description.
		$image_prompt = 'Create an infographic based on this description: ' . $infographic_description . '. Style: modern, clean, professional infographic design.';

		// Use existing image generation infrastructure.
		$image_service = abcc_determine_image_service( $model );

		if ( empty( $image_service['service'] ) ) {
			throw new Exception( __( 'No image generation service available', 'automated-blog-content-creator' ) );
		}

		// Honor the infographic-specific provider override.
		$infographic_pref = abcc_get_setting( 'abcc_infographic_provider', 'auto' );
		if ( 'openai' === $infographic_pref ) {
			$override_key = abcc_get_provider_api_key( 'openai' );
			if ( ! empty( $override_key ) ) {
				$image_service['service'] = 'openai';
				$image_service['api_key'] = $override_key;
			}
		} elseif ( 'stability' === $infographic_pref ) {
			$override_key = abcc_get_provider_api_key( 'stability' );
			if ( ! empty( $override_key ) ) {
				$image_service['service'] = 'stability';
				$image_service['api_key'] = $override_key;
			}
		} elseif ( 'gemini' === $infographic_pref ) {
			$override_key = abcc_get_provider_api_key( 'gemini' );
			if ( ! empty( $override_key ) ) {
				$image_service['service'] = 'gemini';
				$image_service['api_key'] = $override_key;
			}
		}
		// 'auto' = leave abcc_determine_image_service()'s choice untouched.

		$image_url = false;
		abcc_set_last_image_error( '' );

		if ( 'openai' === $image_service['service'] ) {
			$images = abcc_openai_generate_images(
				$image_service['api_key'],
				$image_prompt,
				1,
				'1536x1024', // Infographics are landscape by design; the featured-image size setting doesn't apply.
				abcc_get_setting( 'abcc_openai_image_quality', 'medium' ),
				abcc_get_setting( 'abcc_openai_image_model' )
			);
			if ( ! empty( $images ) && is_array( $images ) ) {
				$image_url = $images[0];
			}
		} elseif ( 'stability' === $image_service['service'] ) {
			$image_url = abcc_stability_generate_images( $image_prompt, 1, $image_service['api_key'], abcc_get_setting( 'abcc_stability_image_size', '1024x1024' ) );
		} elseif ( 'gemini' === $image_service['service'] ) {
			$image_url = abcc_gemini_generate_images(
				$image_service['api_key'],
				$image_prompt,
				abcc_get_setting( 'abcc_gemini_image_model' ),
				abcc_get_setting( 'abcc_gemini_image_size', '2K' )
			);
		}

		if ( ! $image_url ) {
			$reason = abcc_get_last_image_error();
			throw new Exception( '' !== $reason ? $reason : __( 'Failed to generate infographic image', 'automated-blog-content-creator' ) );
		}

		// Step 3: Save to media library.
		$attachment_id = abcc_attach_infographic_to_post(
			$post_id,
			$image_url,
			sprintf(
				/* translators: %s: post title */
				__( 'Infographic for: %s', 'automated-blog-content-creator' ),
				$post->post_title
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			throw new Exception( $attachment_id->get_error_message() );
		}

		// Get the attachment URL.
		$attachment_url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success(
			array(
				'message'        => __( 'Infographic saved to the Media Library. It is attached to this post but not inserted into it.', 'automated-blog-content-creator' ),
				'attachment_id'  => $attachment_id,
				'attachment_url' => $attachment_url,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_create_infographic', 'abcc_handle_create_infographic' );
