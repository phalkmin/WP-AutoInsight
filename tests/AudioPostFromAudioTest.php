<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'audio mode sanitizer accepts known modes and defaults the rest',
	function () {
		abcc_assert_same( 'transcript_plus_intro', abcc_sanitize_audio_mode( 'transcript_plus_intro' ) );
		abcc_assert_same( 'full_rewrite', abcc_sanitize_audio_mode( 'full_rewrite' ) );
		abcc_assert_same( 'transcript_plus_intro', abcc_sanitize_audio_mode( 'garbage' ) );
		abcc_assert_same( 'transcript_plus_intro', abcc_sanitize_audio_mode( '' ) );
	}
);

abcc_test(
	'audio default mode setting is declared',
	function () {
		$schema = abcc_get_settings_schema();
		abcc_assert_array_has_key( 'abcc_audio_default_mode', $schema['settings'] );
		abcc_assert_same( 'transcript_plus_intro', $schema['settings']['abcc_audio_default_mode']['default'] );
	}
);

abcc_test(
	'intro prompt embeds transcript and asks for title + short intro only',
	function () {
		$p = abcc_audio_build_intro_prompt( 'Hello this is a test transcript.', array() );
		abcc_assert_true( strpos( $p, 'Hello this is a test transcript.' ) !== false, 'Transcript must be embedded.' );
		abcc_assert_true( strpos( strtolower( $p ), 'intro' ) !== false, 'Must ask for an intro.' );
		abcc_assert_true( strpos( strtolower( $p ), 'title' ) !== false, 'Must ask for a title.' );
	}
);

abcc_test(
	'rewrite prompt embeds transcript and permits restructuring',
	function () {
		$p = abcc_audio_build_rewrite_prompt( 'Some rambling voice memo content.', array() );
		abcc_assert_true( strpos( $p, 'Some rambling voice memo content.' ) !== false, 'Transcript must be embedded.' );
		abcc_assert_true( strpos( strtolower( $p ), 'restructure' ) !== false, 'Rewrite must permit restructuring.' );
	}
);

abcc_test(
	'mode A creates a post with intro + transcript and honors draft status',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'           => 'sk-test',
			'prompt_select'            => 'gpt-4.1-mini-2025-04-14',
			'abcc_default_post_status' => 'draft',
		);

		// Create a dummy audio file so abcc_transcribe_audio can read it (HTTP is mocked).
		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// 1) transcription response, 2) intro generation response.
		abcc_test_queue_http_response( abcc_test_fake_transcription( 'This is the spoken transcript.' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "My Audio Post\nA short intro sentence." ) );

		$post_id = abcc_generate_post_from_audio( $audio_path, 'transcript_plus_intro', array( 'attachment_id' => 0 ) );

		abcc_assert_true( ! is_wp_error( $post_id ), 'Mode A must create a post.' );
		$post = get_post( $post_id );
		abcc_assert_same( 'draft', $post->post_status );
		abcc_assert_same( 'My Audio Post', $post->post_title, 'Post title must equal the first line of the generated content.' );
		abcc_assert_true( strpos( $post->post_content, 'spoken transcript' ) !== false, 'Transcript must be embedded in mode A.' );
		abcc_assert_same( 'transcript_plus_intro', get_post_meta( $post_id, '_abcc_audio_mode', true ) );
	}
);

abcc_test(
	'mode B rewrite failure falls back to mode A and flags it',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'           => 'sk-test',
			'prompt_select'            => 'gpt-4.1-mini-2025-04-14',
			'abcc_default_post_status' => 'draft',
		);

		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		abcc_test_queue_http_response( abcc_test_fake_transcription( 'Raw rambling content here.' ) );
		// Rewrite generation fails (empty), then mode-A intro generation succeeds.
		abcc_test_queue_http_response( abcc_test_fake_generation( '' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "Fallback Title\nFallback intro." ) );

		$post_id = abcc_generate_post_from_audio( $audio_path, 'full_rewrite', array( 'attachment_id' => 0 ) );

		abcc_assert_true( ! is_wp_error( $post_id ), 'Fallback must still create a post.' );
		abcc_assert_same( '1', get_post_meta( $post_id, '_abcc_audio_rewrite_failed', true ) );
		abcc_assert_true( strpos( get_post( $post_id )->post_content, 'rambling content' ) !== false, 'Fallback embeds transcript.' );
		abcc_assert_same( 'transcript_plus_intro', get_post_meta( $post_id, '_abcc_audio_mode', true ), 'Mode must be reassigned to fallback mode after rewrite failure.' );
	}
);

abcc_test(
	'content generation uses the selected model provider key, not the OpenAI transcription key',
	function () {
		// User's text model is Claude, but transcription always uses OpenAI.
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'           => 'sk-openai-transcribe',
			'claude_api_key'           => 'sk-claude-generate',
			'prompt_select'            => 'claude-sonnet-4-6',
			'abcc_default_post_status' => 'draft',
		);

		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// 1) transcription (OpenAI), 2) Claude-shaped intro generation response.
		abcc_test_queue_http_response( abcc_test_fake_transcription( 'Spoken words about chemistry.' ) );
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'content'     => array( array( 'text' => "Claude Title\nClaude intro." ) ),
					'stop_reason' => 'end_turn',
					'usage'       => array(
						'input_tokens'  => 5,
						'output_tokens' => 9,
					),
				)
			),
		);

		$post_id = abcc_generate_post_from_audio( $audio_path, 'transcript_plus_intro', array( 'attachment_id' => 0 ) );

		abcc_assert_true( ! is_wp_error( $post_id ), 'Post must be created.' );
		// The LAST request (the generation call) must carry the Claude key, not the OpenAI key.
		$headers = $GLOBALS['abcc_http_last_request']['args']['headers'] ?? array();
		abcc_assert_same( 'sk-claude-generate', $headers['x-api-key'] ?? '', 'Generation call must use the Claude key for a Claude model.' );
		abcc_assert_same( 'Claude Title', get_post( $post_id )->post_title, 'Title must come from the Claude generation.' );
	}
);

abcc_test(
	'mode A embeds an audio player block when an attachment is provided',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
		);

		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		abcc_test_queue_http_response( abcc_test_fake_transcription( 'Para one.' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "Title\nIntro sentence." ) );

		$post_id = abcc_generate_post_from_audio( $audio_path, 'transcript_plus_intro', array( 'attachment_id' => 42 ) );

		abcc_assert_true( strpos( get_post( $post_id )->post_content, 'wp:audio' ) !== false, 'A wp:audio block must be embedded when attachment_id is set.' );
	}
);

abcc_test(
	'multi-paragraph transcript becomes multiple paragraph blocks',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
		);

		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// Transcript with two paragraphs separated by a blank line.
		abcc_test_queue_http_response( abcc_test_fake_transcription( "First paragraph here.\n\nSecond paragraph here." ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "Title\nIntro." ) );

		$post_id = abcc_generate_post_from_audio( $audio_path, 'transcript_plus_intro', array( 'attachment_id' => 0 ) );
		$content = get_post( $post_id )->post_content;

		abcc_assert_true( strpos( $content, 'First paragraph here.' ) !== false, 'First transcript paragraph present.' );
		abcc_assert_true( strpos( $content, 'Second paragraph here.' ) !== false, 'Second transcript paragraph present.' );
		// Intro + two transcript paragraphs should be separate paragraph blocks, not one blob.
		abcc_assert_true( substr_count( $content, '<!-- wp:paragraph -->' ) >= 3, 'Transcript paragraphs should be separate blocks.' );
	}
);

abcc_test(
	'AI title with markdown and heading markers is cleaned before becoming post_title',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
		);

		$audio_path = sys_get_temp_dir() . '/abcc-test-fake.mp3';
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		abcc_test_queue_http_response( abcc_test_fake_transcription( 'Body.' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "## **My Clean Title**\nIntro." ) );

		$post_id = abcc_generate_post_from_audio( $audio_path, 'transcript_plus_intro', array( 'attachment_id' => 0 ) );

		abcc_assert_same( 'My Clean Title', get_post( $post_id )->post_title, 'Markdown/heading markers must be stripped from the title.' );
	}
);

abcc_test(
	'legacy transcript handler produces a post with v4.3 tracking meta',
	function () {
		$attachment_id = 4242;
		$GLOBALS['abcc_test_post_types'][ $attachment_id ] = 'attachment';
		abcc_update_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
		abcc_update_setting( 'openai_api_key', 'sk-test' );

		abcc_test_queue_http_response( abcc_test_fake_generation( "<h2>A Section</h2>\n<p>Body.</p>" ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( 'A Clean Title' ) );

		$post_id = abcc_create_post_from_audio_transcript( 'Spoken words here.', $attachment_id );

		abcc_assert_true( (int) $post_id > 0, 'Legacy path should still create a post.' );
		abcc_assert_same(
			'1',
			(string) get_post_meta( $post_id, '_abcc_generated', true ),
			'Legacy path must set the _abcc_generated tracking meta added in v4.3.'
		);
		abcc_assert_true(
			'' !== (string) get_post_meta( $post_id, '_abcc_model', true ),
			'Legacy path must record the model used.'
		);
	}
);
