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
 * Sanitize an audio output mode.
 *
 * @since 4.3.0
 * @param mixed $mode Raw mode value.
 * @return string 'transcript_plus_intro' | 'full_rewrite'
 */
function abcc_sanitize_audio_mode( $mode ) {
	return 'full_rewrite' === $mode ? 'full_rewrite' : 'transcript_plus_intro';
}

/**
 * Build the prompt for mode A (transcript + intro): a title and a short
 * introduction that frames the transcript that follows it.
 *
 * Does NOT add format/HTML instructions — those are appended globally via
 * ABCC_CONTENT_FORMAT_REQUIREMENTS.
 *
 * @since 4.3.0
 * @param string $transcript Raw transcript text.
 * @param array  $options    Reserved for future use (e.g. language).
 * @return string
 */
function abcc_audio_build_intro_prompt( $transcript, $options = array() ) {
	return sprintf(
		'You are preparing a blog post built around an audio transcript. ' .
		'Write a compelling post title on the first line, then a short (2-3 sentence) introduction ' .
		'that sets up the transcript for the reader. Do not summarize the whole transcript and do not ' .
		"repeat it — the full transcript will appear after your introduction.\n\nTRANSCRIPT:\n%s",
		$transcript
	);
}

/**
 * Build the prompt for mode B (full rewrite): turn a rough transcript into a
 * complete, well-structured blog post. Permissive by design — voice memos
 * and rough recordings benefit from freedom to restructure.
 *
 * Does NOT add format/HTML instructions — those are appended globally via
 * ABCC_CONTENT_FORMAT_REQUIREMENTS.
 *
 * @since 4.3.0
 * @param string $transcript Raw transcript text.
 * @param array  $options    Reserved for future use (e.g. language).
 * @return string
 */
function abcc_audio_build_rewrite_prompt( $transcript, $options = array() ) {
	return sprintf(
		'You are turning a rough audio transcript into a polished blog post. ' .
		'Write a post title on the first line, then the full article. You are free to restructure, ' .
		"reorder, expand, add headings, and clean up filler — keep the speaker's meaning and key points " .
		"but make it read as a written article, not a transcript.\n\nTRANSCRIPT (source material):\n%s",
		$transcript
	);
}

/**
 * Orchestrate post creation from an audio file in one of two modes.
 *
 * Mode A (transcript_plus_intro): Generates a title and short intro via AI, then
 * appends a Transcript heading and the raw transcript as a structured Gutenberg post.
 *
 * Mode B (full_rewrite): Generates a fully rewritten blog post from the transcript.
 * If generation fails or returns empty content, falls back silently to Mode A and
 * records the failure via the _abcc_audio_rewrite_failed post meta flag.
 *
 * @since 4.3.0
 * @param string $audio_path Server path to the audio file.
 * @param string $mode       'transcript_plus_intro' | 'full_rewrite'.
 * @param array  $options    Optional. Keys: attachment_id (int), title_fallback (string).
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function abcc_generate_post_from_audio( $audio_path, $mode, $options = array() ) {
	// Transcription is OpenAI Whisper only for now.
	$provider = 'openai';

	if ( ! abcc_provider_supports_audio_transcription( $provider ) ) {
		return new WP_Error(
			'abcc_audio_unsupported',
			__( 'The selected provider does not support audio transcription. Use OpenAI for audio.', 'automated-blog-content-creator' )
		);
	}

	$api_key = abcc_get_provider_api_key( $provider );
	if ( empty( $api_key ) ) {
		return new WP_Error(
			'abcc_audio_no_key',
			__( 'No OpenAI API key configured for transcription.', 'automated-blog-content-creator' )
		);
	}

	// Transcribe. Any failure → WP_Error, no post created.
	try {
		$transcript = abcc_transcribe_audio( $api_key, $audio_path );
	} catch ( Exception $e ) {
		return new WP_Error( 'abcc_audio_transcribe_failed', $e->getMessage() );
	}

	if ( ! is_string( $transcript ) || '' === trim( $transcript ) ) {
		return new WP_Error(
			'abcc_audio_transcribe_failed',
			__( 'Transcription returned no text.', 'automated-blog-content-creator' )
		);
	}

	$mode           = abcc_sanitize_audio_mode( $mode );
	$model          = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
	$char_limit     = (int) abcc_get_setting( 'openai_char_limit', 200 );
	$rewrite_failed = false;
	$title          = '';
	$blocks         = array();

	// Content generation may use a different provider than transcription
	// (transcription is OpenAI-only; the text model is the user's selection).
	// Resolve the key for the text model's provider so a Claude/Gemini model
	// is not called with the OpenAI key.
	$gen_key = abcc_check_api_key( $model );
	if ( empty( $gen_key ) ) {
		return new WP_Error(
			'abcc_audio_no_gen_key',
			__( 'No API key configured for the selected content model. Add one under Connections.', 'automated-blog-content-creator' )
		);
	}

	if ( 'full_rewrite' === $mode ) {
		$prompt = abcc_audio_build_rewrite_prompt( $transcript, $options );
		$raw    = abcc_generate_content( $gen_key, $prompt, $model, $char_limit );

		// Filter out blank lines; treat false or all-empty result as failure.
		$lines = is_array( $raw ) ? array_values(
			array_filter(
				$raw,
				function ( $line ) {
					return '' !== trim( (string) $line );
				}
			)
		) : array();

		if ( ! empty( $lines ) ) {
			$title  = abcc_audio_clean_title( array_shift( $lines ) );
			$blocks = abcc_create_blocks( $lines );
		} else {
			// Generation failed or returned empty content — fall back to Mode A silently.
			$rewrite_failed = true;
			$mode           = 'transcript_plus_intro';
		}
	}

	if ( 'transcript_plus_intro' === $mode ) {
		$prompt = abcc_audio_build_intro_prompt( $transcript, $options );
		$lines  = abcc_generate_content( $gen_key, $prompt, $model, $char_limit );

		if ( ! empty( $lines ) ) {
			$title = abcc_audio_clean_title( array_shift( $lines ) );
		}

		$intro_lines = ! empty( $lines ) ? $lines : array();

		// Build: intro paragraph(s) + Transcript heading + transcript body
		// (split into paragraphs so it isn't one wall-of-text block).
		$content_lines = array_merge(
			$intro_lines,
			array( '<h2>' . __( 'Transcript', 'automated-blog-content-creator' ) . '</h2>' ),
			abcc_audio_split_transcript_paragraphs( $transcript )
		);
		$blocks        = abcc_create_blocks( $content_lines );
	}

	if ( '' === $title ) {
		$title = ! empty( $options['title_fallback'] )
			? $options['title_fallback']
			: __( 'Untitled audio post', 'automated-blog-content-creator' );
	}

	// Prepend an audio player block when we know the source attachment, mirroring
	// the legacy transcribe-and-create path so audio posts remain playable.
	$post_content = abcc_gutenberg_blocks( $blocks );
	if ( ! empty( $options['attachment_id'] ) ) {
		$audio_url = wp_get_attachment_url( (int) $options['attachment_id'] );
		if ( $audio_url ) {
			$audio_block  = sprintf(
				'<!-- wp:audio {"id":%d} --><figure class="wp-block-audio"><audio controls src="%s"></audio></figure><!-- /wp:audio -->',
				(int) $options['attachment_id'],
				esc_url( $audio_url )
			);
			$post_content = $audio_block . "\n\n" . $post_content;
		}
	}

	$post_status = abcc_resolve_post_status( array( 'source' => 'audio' ) );

	$post_id = wp_insert_post(
		array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $post_content ),
			'post_status'  => $post_status,
			'post_type'    => 'post',
			'meta_input'   => array(
				'_abcc_generated'           => '1',
				'_abcc_model'               => $model,
				'_abcc_transcript_audio'    => isset( $options['attachment_id'] ) ? (int) $options['attachment_id'] : 0,
				'_abcc_original_transcript' => sanitize_textarea_field( $transcript ),
				'_abcc_audio_mode'          => $mode,
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	if ( $rewrite_failed ) {
		update_post_meta( $post_id, '_abcc_audio_rewrite_failed', '1' );
	}

	return $post_id;
}

/**
 * Clean a model-generated first line into a usable post title.
 *
 * Delegates to the shared title cleaner so audio titles get the same
 * treatment (HTML, list markers, markdown, quotes) as the rest of the plugin.
 *
 * @since 4.3.0
 * @param string $raw Raw first line from the generated content.
 * @return string
 */
function abcc_audio_clean_title( $raw ) {
	return abcc_clean_title_line( $raw );
}

/**
 * Split a raw transcript into paragraph lines for block creation.
 *
 * Whisper returns continuous prose; without splitting, the whole transcript
 * becomes a single paragraph block. Split on blank lines first, then on single
 * newlines, falling back to the whole string when there are no breaks.
 *
 * @since 4.3.0
 * @param string $transcript Raw transcript text.
 * @return array Non-empty paragraph strings.
 */
function abcc_audio_split_transcript_paragraphs( $transcript ) {
	$transcript = (string) $transcript;

	// Prefer blank-line paragraph breaks; fall back to single newlines.
	$parts = preg_split( '/\n\s*\n/', $transcript );
	if ( count( $parts ) < 2 ) {
		$parts = preg_split( '/\n/', $transcript );
	}

	$paragraphs = array();
	foreach ( (array) $parts as $part ) {
		$part = trim( $part );
		if ( '' !== $part ) {
			$paragraphs[] = $part;
		}
	}

	// Always return at least the original (trimmed) text.
	if ( empty( $paragraphs ) ) {
		$trimmed = trim( $transcript );
		if ( '' !== $trimmed ) {
			$paragraphs[] = $trimmed;
		}
	}

	return $paragraphs;
}

/**
 * Add transcribe button to audio attachment pages.
 *
 * @since 2.1.0
 * @return void
 */
function abcc_add_transcribe_button_to_media() {
	global $post;

	if ( ! abcc_current_user_can_prompt() ) {
		return;
	}

	// Check if audio transcription is enabled.
	if ( ! abcc_get_setting( 'abcc_enable_audio_transcription', true ) ) {
		return;
	}

	if ( ! isset( $post->post_mime_type ) || false === strpos( $post->post_mime_type, 'audio' ) ) {
		return;
	}

	// Check if this audio format is supported.
	$supported_formats = abcc_get_setting( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
	$file_extension    = pathinfo( get_attached_file( $post->ID ), PATHINFO_EXTENSION );

	if ( ! in_array( strtolower( $file_extension ), $supported_formats, true ) ) {
		return;
	}
	$default_mode    = abcc_get_setting( 'abcc_audio_default_mode', 'transcript_plus_intro' );
	$attached_file   = get_attached_file( $post->ID );
	$file_size_bytes = ( $attached_file && file_exists( $attached_file ) ) ? filesize( $attached_file ) : 0;
	?>
	<div class="misc-pub-section">
		<label><?php esc_html_e( 'AI Transcription:', 'automated-blog-content-creator' ); ?></label>
		<fieldset class="abcc-audio-mode" style="margin:8px 0;">
			<label style="display:block;">
				<input type="radio" name="abcc_audio_mode" value="transcript_plus_intro" <?php checked( $default_mode, 'transcript_plus_intro' ); ?>>
				<?php esc_html_e( 'Transcript + AI intro', 'automated-blog-content-creator' ); ?>
			</label>
			<label style="display:block;">
				<input type="radio" name="abcc_audio_mode" value="full_rewrite" <?php checked( $default_mode, 'full_rewrite' ); ?>>
				<?php esc_html_e( 'Full rewrite', 'automated-blog-content-creator' ); ?>
			</label>
		</fieldset>
		<div style="margin-top: 8px;">
			<button type="button" class="button button-primary abcc-audio-create-post" data-attachment-id="<?php echo esc_attr( $post->ID ); ?>" data-file-size="<?php echo esc_attr( $file_size_bytes ); ?>">
				<?php esc_html_e( 'Create Post from Audio', 'automated-blog-content-creator' ); ?>
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

	if ( ! abcc_current_user_can_prompt() || ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'automated-blog-content-creator' ) ) );
		return;
	}

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$create_post   = isset( $_POST['create_post'] ) ? (bool) $_POST['create_post'] : false;

	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid attachment ID', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		// Audio transcription always uses OpenAI Whisper — fetch the OpenAI key directly
		// regardless of which text generation provider the user has selected.
		$api_key = abcc_get_provider_api_key( 'openai' );
		if ( empty( $api_key ) ) {
			throw new Exception( __( 'An OpenAI API key is required for audio transcription. Please add one in Advanced Settings.', 'automated-blog-content-creator' ) );
		}

		// Validate file.
		$file_path = get_attached_file( $attachment_id );
		if ( ! file_exists( $file_path ) ) {
			throw new Exception( __( 'File not found', 'automated-blog-content-creator' ) );
		}

		// Check file format.
		$supported_formats = abcc_get_setting( 'abcc_supported_audio_formats', array( 'mp3', 'wav', 'm4a', 'webm' ) );
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

	if ( ! abcc_current_user_can_prompt() || ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'automated-blog-content-creator' ) ) );
		return;
	}

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$transcript    = isset( $_POST['transcript'] ) ? sanitize_textarea_field( wp_unslash( $_POST['transcript'] ) ) : '';

	if ( ! $attachment_id || empty( $transcript ) ) {
		wp_send_json_error( array( 'message' => __( 'Missing required data', 'automated-blog-content-creator' ) ) );
		return;
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

	// Build multipart/form-data body manually (wp_remote_post does not support file uploads natively).
	$boundary  = wp_generate_password( 24, false );
	$language  = abcc_get_setting( 'abcc_transcription_language', 'en' );
	$file_name = basename( $file_path );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$file_data = file_get_contents( $file_path );

	if ( false === $file_data ) {
		throw new Exception( esc_html__( 'Could not read audio file.', 'automated-blog-content-creator' ) );
	}

	$body  = '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
	$body .= 'whisper-1' . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="response_format"' . "\r\n\r\n";
	$body .= 'text' . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="language"' . "\r\n\r\n";
	$body .= $language . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
	$body .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . '--';

	$wp_response = wp_remote_post(
		'https://api.openai.com/v1/audio/transcriptions',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			),
			'body'    => $body,
			'timeout' => 300,
		)
	);

	if ( is_wp_error( $wp_response ) ) {
		throw new Exception(
			sprintf(
				/* translators: %s: error message */
				esc_html__( 'Network error: %s', 'automated-blog-content-creator' ),
				esc_html( $wp_response->get_error_message() )
			)
		);
	}

	$http_code = wp_remote_retrieve_response_code( $wp_response );
	$response  = wp_remote_retrieve_body( $wp_response );

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
	$prompt_select = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
	$char_limit    = abcc_get_setting( 'openai_char_limit', 200 );

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

	// Resolve post status via the shared choke point (honours draft-first and
	// the global default-status setting). The 'audio' source allows v4.5+
	// hooks to differentiate audio-sourced posts if needed.
	$post_status = abcc_resolve_post_status( array( 'source' => 'audio' ) );

	// Create the post.
	$post_data = array(
		'post_title'    => sanitize_text_field( $title ),
		'post_content'  => wp_kses_post( $final_content ),
		'post_status'   => $post_status,
		'post_author'   => get_current_user_id(),
		'post_type'     => 'post',
		'post_category' => array( (int) get_option( 'default_category', 1 ) ),
	);

	$post_id = wp_insert_post( $post_data, true );

	if ( is_wp_error( $post_id ) ) {
		throw new Exception( esc_html( $post_id->get_error_message() ) );
	}

	// Store transcript metadata.
	update_post_meta( $post_id, '_abcc_transcript_audio', $attachment_id );
	update_post_meta( $post_id, '_abcc_original_transcript', $transcript );

	// Generate featured image if enabled.
	if ( abcc_get_setting( 'openai_generate_images', true ) ) {
		try {
			$keywords  = explode( ' ', wp_trim_words( $transcript, 10 ) );
			$image_url = abcc_generate_featured_image( $prompt_select, $keywords );
			if ( $image_url ) {
				$alt_text = get_the_title( $post_id );
				abcc_set_featured_image( $post_id, $image_url, $alt_text );
			}
		} catch ( Exception $e ) {
			abcc_debug_log( 'Featured image generation failed for audio post: ' . $e->getMessage() );
		}
	}

	// Send notification if enabled.
	if ( abcc_get_setting( 'openai_email_notifications', false ) ) {
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
	if ( ! abcc_current_user_can_prompt() || ! $post || false === strpos( $post->post_mime_type, 'audio' ) ) {
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

/**
 * AJAX: generate a post from an uploaded audio attachment in the chosen mode.
 *
 * @since 4.3.0
 * @return void
 */
function abcc_handle_audio_generate_post() {
	check_ajax_referer( 'abcc_admin_buttons', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$mode          = isset( $_POST['mode'] )
		? abcc_sanitize_audio_mode( sanitize_text_field( wp_unslash( $_POST['mode'] ) ) )
		: abcc_get_setting( 'abcc_audio_default_mode', 'transcript_plus_intro' );

	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid audio attachment.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$path = get_attached_file( $attachment_id );
	if ( ! $path ) {
		wp_send_json_error( array( 'message' => __( 'Could not locate the audio file.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$result = abcc_generate_post_from_audio( $path, $mode, array( 'attachment_id' => $attachment_id ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		return;
	}

	wp_send_json_success(
		array(
			'message'  => esc_html__( 'Post created from audio.', 'automated-blog-content-creator' ),
			'post_id'  => $result,
			'edit_url' => get_edit_post_link( $result, 'raw' ),
		)
	);
}
add_action( 'wp_ajax_abcc_audio_generate_post', 'abcc_handle_audio_generate_post' );
