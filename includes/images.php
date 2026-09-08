<?php
/**
 * Image generation functions.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Generates a featured image using AI services.
 *
 * @param string $text_model The text model being used.
 * @param array  $keywords Keywords for image generation.
 * @param array  $category_names Category names for context.
 * @return string|false Image URL on success, false on failure.
 */
function abcc_generate_featured_image( $text_model, $keywords, $category_names = array() ) {
	try {
		// Check if image generation is enabled.
		if ( ! abcc_get_setting( 'openai_generate_images', true ) ) {
			return false;
		}

		// Build image prompt.
		$prompt = abcc_build_image_prompt( $keywords, $category_names );

		// Determine which service to use.
		$image_service = abcc_determine_image_service( $text_model );

		if ( empty( $image_service['service'] ) ) {
			abcc_debug_log( 'No available image generation service' );
			return false;
		}

		abcc_debug_log(
			sprintf(
				'Attempting to generate image using %s service with prompt: %s',
				$image_service['service'],
				$prompt
			)
		);

		// Generate image using determined service.
		switch ( $image_service['service'] ) {
			case 'openai':
				$openai_model   = abcc_get_setting( 'abcc_openai_image_model', 'gpt-image-1' );
				$openai_size    = abcc_get_setting( 'abcc_openai_image_size', '1024x1024' );
				$openai_quality = abcc_get_setting( 'abcc_openai_image_quality', 'medium' );
				$images         = abcc_openai_generate_images( $image_service['api_key'], $prompt, 1, $openai_size, $openai_quality, $openai_model );
				if ( ! empty( $images ) && is_array( $images ) ) {
					return $images[0];
				}
				break;

			case 'stability':
				$stability_size = abcc_get_setting( 'abcc_stability_image_size', '1024x1024' );
				$result         = abcc_stability_generate_images( $prompt, 1, $image_service['api_key'], $stability_size );
				if ( false !== $result ) {
					return $result;
				}
				break;

			case 'gemini':
				$gemini_image_model = abcc_get_setting( 'abcc_gemini_image_model', 'gemini-2.5-flash-image' );
				$gemini_image_size  = abcc_get_setting( 'abcc_gemini_image_size', '2K' );
				$result             = abcc_gemini_generate_images( $image_service['api_key'], $prompt, $gemini_image_model, $gemini_image_size );
				if ( false !== $result ) {
					return $result;
				}
				break;
		}

		abcc_debug_log( 'Image generation failed for selected service' );
		return false;

	} catch ( Exception $e ) {
		abcc_debug_log( 'Image Generation Error: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Record whether featured-image generation was attempted and how it went.
 *
 * @since 4.4.0
 * @param int    $post_id Post ID.
 * @param bool   $success Whether generation succeeded.
 * @param string $reason  Failure reason. Ignored on success.
 * @return void
 */
function abcc_record_image_generation_attempt( $post_id, $success, $reason = '' ) {
	update_post_meta(
		(int) $post_id,
		'_abcc_image_generation_attempted',
		$success ? '1' : ( '' !== $reason ? $reason : __( 'Unknown error', 'automated-blog-content-creator' ) )
	);
}

/**
 * Resolve the keywords and model to use when retrying a failed featured image.
 *
 * The original generation's inputs live in _abcc_generation_params on the
 * content post (_abcc_job_keywords only exists on job posts). Posts without
 * params fall back to the post title.
 *
 * @since 4.4.0
 * @param int $post_id Post ID.
 * @return array array( 'keywords' => string[], 'model' => string )
 */
function abcc_get_image_retry_context( $post_id ) {
	$post_id  = (int) $post_id;
	$keywords = array();
	$model    = abcc_get_setting( 'prompt_select', '' );

	$params = json_decode( (string) get_post_meta( $post_id, '_abcc_generation_params', true ), true );

	if ( is_array( $params ) ) {
		if ( ! empty( $params['focus_keyword'] ) ) {
			// The original image prompt used the focus keyword, not the full list.
			$keywords = array( (string) $params['focus_keyword'] );
		} elseif ( ! empty( $params['keywords'] ) ) {
			$keywords = array_values( array_filter( array_map( 'strval', (array) $params['keywords'] ), 'strlen' ) );
		}

		if ( ! empty( $params['model'] ) ) {
			$model = (string) $params['model'];
		}
	}

	if ( empty( $keywords ) ) {
		$title    = get_the_title( $post_id );
		$keywords = '' !== (string) $title ? array( (string) $title ) : array();
	}

	return array(
		'keywords' => $keywords,
		'model'    => $model,
	);
}

/**
 * Build the admin notice for a post whose featured image failed.
 *
 * @since 4.4.0
 * @param int $post_id Post ID.
 * @return string Notice HTML, or '' when there is nothing to report.
 */
function abcc_get_image_failure_notice_html( $post_id ) {
	$attempt = (string) get_post_meta( (int) $post_id, '_abcc_image_generation_attempted', true );

	if ( '' === $attempt || '1' === $attempt ) {
		return '';
	}

	return sprintf(
		'<div class="notice notice-warning is-dismissible abcc-image-failure"><p>%1$s <button type="button" class="button button-small abcc-retry-image" data-post-id="%2$d" data-nonce="%4$s">%3$s</button></p></div>',
		esc_html(
			sprintf(
				/* translators: %s: failure reason from the image provider */
				__( 'Could not generate a featured image — %s', 'automated-blog-content-creator' ),
				$attempt
			)
		),
		(int) $post_id,
		esc_html__( 'Retry', 'automated-blog-content-creator' ),
		esc_attr( wp_create_nonce( 'abcc_retry_image' ) )
	);
}

/**
 * Build featured image alt text.
 *
 * @param string $title           Post title.
 * @param string $primary_keyword Primary keyword.
 * @return string
 */
function abcc_build_featured_image_alt_text( $title, $primary_keyword ) {
	$title           = trim( (string) $title );
	$primary_keyword = trim( (string) $primary_keyword );

	if ( '' === $title || '' === $primary_keyword ) {
		return '';
	}

	return $title . ' - ' . $primary_keyword;
}

/**
 * Register a local image file as an attachment and set it as the featured image.
 *
 * media_sideload_image() HTTP-GETs a URL we just wrote to disk — a round trip to
 * the site's own frontend that fails on local, basic-auth, and firewalled
 * installs. Registering from the local path skips the network entirely.
 *
 * @since 4.4.0
 * @param int    $post_id  Target post.
 * @param string $path     Absolute path to an image file inside the uploads dir.
 * @param string $alt_text Optional alt text.
 * @return int|false Attachment ID, or false on failure.
 */
function abcc_attach_local_image_to_post( $post_id, $path, $alt_text = '' ) {
	if ( ! file_exists( $path ) ) {
		abcc_debug_log( 'Featured Image Error: local file missing at ' . $path );
		return false;
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$filetype = wp_check_filetype( basename( $path ), null );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
			'post_title'     => sanitize_file_name( pathinfo( $path, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$path,
		$post_id
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		abcc_debug_log( 'Featured Image Error: wp_insert_attachment failed for ' . $path );
		return false;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

	if ( ! empty( $alt_text ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
	}

	set_post_thumbnail( $post_id, $attachment_id );

	return (int) $attachment_id;
}

/**
 * Sets the featured image for a post.
 *
 * @param int    $post_id Post ID.
 * @param string $image_url Image URL.
 * @param string $alt_text Optional alt text.
 * @return int|false Attachment ID on success, false on failure.
 */
function abcc_set_featured_image( $post_id, $image_url, $alt_text = '' ) {
	try {
		// Local file: register directly, no self-HTTP.
		if ( 0 === strpos( $image_url, 'file://' ) || file_exists( $image_url ) ) {
			return abcc_attach_local_image_to_post( $post_id, str_replace( 'file://', '', $image_url ), $alt_text );
		}

		// Generated images live in our own uploads dir — map the URL back to a
		// path so attaching never needs a loopback HTTP request.
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $image_url, $uploads['baseurl'] ) ) {
			$local_path = $uploads['basedir'] . substr( $image_url, strlen( $uploads['baseurl'] ) );
			if ( file_exists( $local_path ) ) {
				return abcc_attach_local_image_to_post( $post_id, $local_path, $alt_text );
			}
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download and attach the image.
		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new Exception( $attachment_id->get_error_message() );
		}

		// Set alt text if provided.
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Set as featured image.
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;

	} catch ( Exception $e ) {
		abcc_debug_log( 'Featured Image Error: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Builds the image generation prompt.
 *
 * @param array $keywords Keywords for the image.
 * @param array $category_names Category names for context.
 * @return string The generated prompt.
 */
function abcc_build_image_prompt( $keywords, $category_names ) {
	$prompt_parts = array();

	// Add keywords.
	if ( ! empty( $keywords ) ) {
		$prompt_parts[] = implode( ', ', array_map( 'sanitize_text_field', $keywords ) );
	}

	// Add categories for context.
	if ( ! empty( $category_names ) ) {
		$prompt_parts[] = 'Related to: ' . implode( ', ', $category_names );
	}

	// Add style guidance.
	$prompt_parts[] = 'Create a high-quality, professional image suitable for a blog post';

	return implode( '. ', $prompt_parts );
}
