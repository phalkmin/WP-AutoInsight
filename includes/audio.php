<?php
/**
 * Audio transcription functionality for WP-AutoInsight
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add transcribe button to audio attachment pages.
 *
 * @since 2.1.0
 * @return void
 */
function abcc_add_transcribe_button_to_media() {
	global $post;

	// Check if audio transcription is enabled.
	if ( ! get_option( 'abcc_enable_audio_transcription', true ) ) {
		return;
	}

	if ( ! isset( $post->post_mime_type ) || false === strpos( $post->post_mime_type, 'audio' ) ) {
		return;
	}

	// Check if this audio format is supported.
	$supported_formats = get_option( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
	$file_extension    = pathinfo( get_attached_file( $post->ID ), PATHINFO_EXTENSION );

	if ( ! in_array( strtolower( $file_extension ), $supported_formats, true ) ) {
		return;
	}
	?>
	<div class="misc-pub-section">
		<label><?php esc_html_e( 'AI Transcription:', 'automated-blog-content-creator' ); ?></label>
		<div style="margin-top: 8px;">
			<button type="button" class="button" id="abcc-transcribe-audio" data-id="<?php echo esc_attr( $post->ID ); ?>">
				<?php esc_html_e( 'Transcribe & Create Post', 'automated-blog-content-creator' ); ?>
			</button>
			<button type="button" class="button" id="abcc-transcribe-only" data-id="<?php echo esc_attr( $post->ID ); ?>">
				<?php esc_html_e( 'Transcribe Only', 'automated-blog-content-creator' ); ?>
			</button>
		</div>
		<div id="abcc-transcription-status" style="margin-top: 8px;"></div>
		<div id="abcc-transcription-result" style="margin-top: 8px; display: none;">
			<label for="abcc-transcript-text"><?php esc_html_e( 'Transcript:', 'automated-blog-content-creator' ); ?></label>
			<textarea id="abcc-transcript-text" rows="6" style="width: 100%; margin-top: 4px;" readonly></textarea>
			<div style="margin-top: 8px;">
				<button type="button" class="button button-primary" id="abcc-create-post-from-transcript">
					<?php esc_html_e( 'Create Post from Transcript', 'automated-blog-content-creator' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'attachment_submitbox_misc_actions', 'abcc_add_transcribe_button_to_media' );

/**
 * Handle audio transcription AJAX request.
 *
 * @since 2.1.0
 * @return void
 */
function abcc_handle_audio_transcription() {
	check_ajax_referer( 'abcc_admin_buttons', 'nonce' );

	if ( ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'automated-blog-content-creator' ) ) );
	}

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$create_post   = isset( $_POST['create_post'] ) ? (bool) $_POST['create_post'] : false;

	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'automated-blog-content-creator' ) ) );
	}

	try {
		// Audio transcription always uses OpenAI Whisper — fetch the OpenAI key directly
		// regardless of which text generation provider the user has selected.
		$api_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
		if ( empty( $api_key ) ) {
			throw new Exception( __( 'An OpenAI API key is required for audio transcription. Please add one in Advanced Settings.', 'automated-blog-content-creator' ) );
		}

		// Validate file.
		$file_path = get_attached_file( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			throw new Exception( __( 'File not found', 'automated-blog-content-creator' ) );
		}

		// Check file format.
		$supported_formats = get_option( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
		$file_extension    = pathinfo( $file_path, PATHINFO_EXTENSION );

		if ( ! in_array( strtolower( $file_extension ), $supported_formats, true ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: File extension */
					__( 'Unsupported file format: %s', 'automated-blog-content-creator' ),
					$file_extension
				)
			);
		}

		$file_size = filesize( $file_path );
		if ( $file_size > 25 * 1024 * 1024 ) {
			throw new Exception( esc_html__( 'File too large. Maximum size is 25MB.', 'automated-blog-content-creator' ) );
		}

		// Transcribe the audio.
		$transcript = abcc_transcribe_audio( $api_key, $file_path );

		if ( true === $create_post ) {
			// Create post from transcript using existing content generation.
			$post_id = abcc_create_post_from_audio_transcript( $transcript, $attachment_id );

			wp_send_json_success(
				array(
					'message'    => __( 'Post created successfully from audio transcription!', 'automated-blog-content-creator' ),
					'post_id'    => $post_id,
					'transcript' => $transcript,
					'edit_url'   => get_edit_post_link( $post_id, '' ),
				)
			);
		} else {
			// Return just the transcript.
			wp_send_json_success(
				array(
					'message'    => __( 'Audio transcribed successfully!', 'automated-blog-content-creator' ),
					'transcript' => $transcript,
				)
			);
		}
	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_transcribe_audio', 'abcc_handle_audio_transcription' );

/**
 * Handle creating post from existing transcript.
 *
 * @since 2.1.0
 * @return void
 */
function abcc_handle_create_post_from_transcript() {
	check_ajax_referer( 'abcc_admin_buttons', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'automated-blog-content-creator' ) ) );
	}

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$transcript    = isset( $_POST['transcript'] ) ? sanitize_textarea_field( wp_unslash( $_POST['transcript'] ) ) : '';

	if ( ! $attachment_id || empty( $transcript ) ) {
		wp_send_json_error( array( 'message' => __( 'Missing required data', 'automated-blog-content-creator' ) ) );
	}

	try {
		$post_id = abcc_create_post_from_audio_transcript( $transcript, $attachment_id );

		wp_send_json_success(
			array(
				'message'  => __( 'Post created successfully from transcript!', 'automated-blog-content-creator' ),
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, '' ),
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_create_post_from_transcript', 'abcc_handle_create_post_from_transcript' );

/**
 * Transcribe audio using OpenAI Whisper API.
 *
 * @since 2.1.0
 * @param string $api_key   The OpenAI API key.
 * @param string $file_path Path to the audio file.
 * @return string The transcribed text.
 * @throws Exception If transcription fails.
 */
function abcc_transcribe_audio( $api_key, $file_path ) {
	// Check file size (Whisper has a 25MB limit).
	$file_size = filesize( $file_path );
	if ( $file_size > 25 * 1024 * 1024 ) {
		throw new Exception( esc_html__( 'File too large. Maximum size is 25MB.', 'automated-blog-content-creator' ) );
	}

	// Use cURL for file upload to Whisper API.
	$curl = curl_init();
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_URL            => 'https://api.openai.com/v1/audio/transcriptions',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => array(
				'Authorization: Bearer ' . $api_key,
			),
			CURLOPT_POSTFIELDS     => array(
				'file'            => new CURLFile( $file_path ),
				'model'           => 'whisper-1',
				'response_format' => 'text',
				'language'        => get_option( 'abcc_transcription_language', 'en' ),
			),
			CURLOPT_TIMEOUT        => 300, // 5 minutes for large files.
		)
	);

	$response  = curl_exec( $curl );
	$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
	$error     = curl_error( $curl );
	curl_close( $curl );

	if ( ! empty( $error ) ) {
		throw new Exception(
			sprintf(
				/* translators: %s: cURL error message */
				esc_html__( 'Network error: %s', 'automated-blog-content-creator' ),
				esc_html( $error )
			)
		);
	}

	if ( 200 !== $http_code ) {
		$error_data = json_decode( $response, true );
		$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown error';

		throw new Exception(
			sprintf(
				/* translators: %1$d: HTTP status code, %2$s: Error message */
				esc_html__( 'Transcription failed. Status code: %1$d. Error: %2$s', 'automated-blog-content-creator' ),
				esc_html( $http_code ),
				esc_html( $error_msg )
			)
		);
	}

	return trim( $response );
}

/**
 * Create a post from audio transcript using existing WP-AutoInsight infrastructure.
 *
 * @since 2.1.0
 * @param string $transcript     The transcribed text.
 * @param int    $attachment_id  The audio attachment ID.
 * @return int The created post ID.
 * @throws Exception If post creation fails.
 */
function abcc_create_post_from_audio_transcript( $transcript, $attachment_id ) {
	// Generate title from transcript excerpt.
	$title = wp_trim_words( $transcript, 8, '...' );

	// Get audio URL for embedding.
	$audio_url = wp_get_attachment_url( $attachment_id );

	// Use existing content generation to enhance the transcript.
	$api_key       = abcc_check_api_key();
	$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );
	$char_limit    = get_option( 'openai_char_limit', 200 );

	// Create enhanced content prompt.
	$prompt = sprintf(
		'Transform this audio transcript into a well-structured blog post. Keep the original meaning and key points, but improve readability and add proper structure with headings.

Transcript: %s

Format requirements:
- Create an engaging title
- Add introduction paragraph
- Use <h2> headings for main sections  
- Use <h3> for subsections if needed
- Improve paragraph structure
- Add a conclusion
- Keep the tone conversational but polished',
		$transcript
	);

	// Generate enhanced content.
	$enhanced_content = abcc_generate_content( $api_key, $prompt, $prompt_select, $char_limit );

	if ( $enhanced_content ) {
		// Process the enhanced content.
		$content_array = array_filter(
			$enhanced_content,
			function ( $line ) {
				return ! empty( trim( $line ) );
			}
		);

		$format_content = abcc_create_blocks( $content_array );
		$post_content   = abcc_gutenberg_blocks( $format_content );

		// Extract title from enhanced content if available.
		foreach ( $enhanced_content as $line ) {
			if ( preg_match( '/<h1>(.*?)<\/h1>/', $line, $matches ) ) {
				$title = wp_strip_all_tags( $matches[1] );
				break;
			}
		}
	} else {
		// Fallback to basic transcript formatting.
		$post_content = '<!-- wp:paragraph --><p>' . esc_html( $transcript ) . '</p><!-- /wp:paragraph -->';
	}

	// Add audio player at the beginning.
	$audio_block = sprintf(
		'<!-- wp:audio {"id":%d} --><figure class="wp-block-audio"><audio controls src="%s"></audio></figure><!-- /wp:audio -->',
		$attachment_id,
		esc_url( $audio_url )
	);

	$final_content = $audio_block . "\n\n" . $post_content;

	// Create the post.
	$post_data = array(
		'post_title'    => sanitize_text_field( $title ),
		'post_content'  => wp_kses_post( $final_content ),
		'post_status'   => 'draft',
		'post_author'   => get_current_user_id(),
		'post_type'     => 'post',
		'post_category' => get_option( 'openai_selected_categories', array() ),
	);

	$post_id = wp_insert_post( $post_data, true );

	if ( is_wp_error( $post_id ) ) {
		throw new Exception( esc_html( $post_id->get_error_message() ) );
	}

	// Store transcript metadata.
	update_post_meta( $post_id, '_abcc_transcript_audio', $attachment_id );
	update_post_meta( $post_id, '_abcc_original_transcript', $transcript );

	// Generate featured image if enabled.
	if ( get_option( 'openai_generate_images', true ) ) {
		try {
			$keywords  = explode( ' ', wp_trim_words( $transcript, 10 ) );
			$image_url = abcc_generate_featured_image( $prompt_select, $keywords );
			if ( $image_url ) {
				$alt_text = get_the_title( $post_id );
				abcc_set_featured_image( $post_id, $image_url, $alt_text );
			}
		} catch ( Exception $e ) {
			error_log( 'Featured image generation failed for audio post: ' . $e->getMessage() );
		}
	}

	// Send notification if enabled.
	if ( true === get_option( 'openai_email_notifications', false ) ) {
		abcc_send_post_notification( $post_id );
	}

	return $post_id;
}

/**
 * Enqueue audio transcription scripts.
 *
 * @since 2.1.0
 * @param string $hook Current admin page hook.
 * @return void
 */
function abcc_enqueue_audio_scripts( $hook ) {
	if ( 'post.php' !== $hook ) {
		return;
	}

	global $post;
	if ( ! $post || false === strpos( $post->post_mime_type, 'audio' ) ) {
		return;
	}

	wp_enqueue_script(
		'abcc-audio-transcription',
		plugins_url( '/js/audio-transcriptions.js', __DIR__ ),
		array( 'jquery' ),
		ABCC_VERSION,
		true
	);

	wp_localize_script(
		'abcc-audio-transcription',
		'abccAudio',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'abcc_admin_buttons' ),
			'i18n'    => array(
				'transcribing' => __( 'Transcribing audio...', 'automated-blog-content-creator' ),
				'creating'     => __( 'Creating post...', 'automated-blog-content-creator' ),
				'error'        => __( 'An error occurred', 'automated-blog-content-creator' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'abcc_enqueue_audio_scripts' );
