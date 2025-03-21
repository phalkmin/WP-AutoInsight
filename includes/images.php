<?php
/**
 * Image generation functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates a featured image using AI services.
 *
 * @param string $text_model The text model being used
 * @param array  $keywords Keywords for image generation
 * @param array  $category_names Category names for context
 * @return string|false Image URL on success, false on failure
 */
function abcc_generate_featured_image( $text_model, $keywords, $category_names = array() ) {
	try {
		// Check if image generation is enabled
		if ( ! get_option( 'openai_generate_images', true ) ) {
			return false;
		}

		// Build image prompt
		$prompt = abcc_build_image_prompt( $keywords, $category_names );

		// Determine which service to use
		$image_service = abcc_determine_image_service( $text_model );

		if ( empty( $image_service['service'] ) ) {
			error_log( 'No available image generation service' );
			return false;
		}

		error_log(
			sprintf(
				'Attempting to generate image using %s service with prompt: %s',
				$image_service['service'],
				$prompt
			)
		);

		// Generate image using determined service
		switch ( $image_service['service'] ) {
			case 'openai':
				$images = abcc_openai_generate_images( $image_service['api_key'], $prompt, 1 );
				if ( ! empty( $images ) && is_array( $images ) ) {
					return $images[0];
				}
				break;

			case 'stability':
				$result = abcc_stability_generate_images( $prompt, 1, $image_service['api_key'] );
				if ( $result ) {
					return $result;
				}
				break;
		}

		error_log( 'Image generation failed for selected service' );
		return false;

	} catch ( Exception $e ) {
		error_log( 'Image Generation Error: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Sets the featured image for a post.
 *
 * @param int    $post_id Post ID
 * @param string $image_url Image URL
 * @return int|false Attachment ID on success, false on failure
 */
function abcc_set_featured_image( $post_id, $image_url ) {
	try {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download and attach the image
		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new Exception( $attachment_id->get_error_message() );
		}

		// Set as featured image
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;

	} catch ( Exception $e ) {
		error_log( 'Featured Image Error: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Builds the image generation prompt.
 *
 * @param array $keywords Keywords for the image
 * @param array $category_names Category names for context
 * @return string
 */
function abcc_build_image_prompt( $keywords, $category_names ) {
	$prompt_parts = array();

	// Add keywords
	if ( ! empty( $keywords ) ) {
		$prompt_parts[] = implode( ', ', array_map( 'sanitize_text_field', $keywords ) );
	}

	// Add categories for context
	if ( ! empty( $category_names ) ) {
		$prompt_parts[] = 'Related to: ' . implode( ', ', $category_names );
	}

	// Add style guidance
	$prompt_parts[] = 'Create a high-quality, professional image suitable for a blog post';

	return implode( '. ', $prompt_parts );
}
