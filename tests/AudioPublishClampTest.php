<?php
/**
 * Audio-to-post honors the publish clamp and validates the attachment.
 */

abcc_test(
	'audio post is a draft when the user cannot publish, even with Publish as the default',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_options']['abcc_audio_default_mode']  = 'transcript_plus_intro';
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'publish_posts' => false );

		abcc_test_queue_http_response( abcc_test_fake_generation( "Intro title\nIntro paragraph." ) );

		$post_id = abcc_build_post_from_transcript( 'spoken words here', 'transcript_plus_intro', array() );

		abcc_assert_true( ! is_wp_error( $post_id ), 'Post must be created.' );
		abcc_assert_same( 'draft', get_post( $post_id )->post_status, 'A user without publish_posts gets a draft.' );
	}
);

abcc_test(
	'audio post keeps Publish when the user can publish',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';
		$GLOBALS['abcc_test_options']['abcc_audio_default_mode']  = 'transcript_plus_intro';
		$GLOBALS['abcc_test_current_user_caps']                   = array( 'publish_posts' => true );

		abcc_test_queue_http_response( abcc_test_fake_generation( "Intro title\nIntro paragraph." ) );

		$post_id = abcc_build_post_from_transcript( 'spoken words here', 'transcript_plus_intro', array() );

		abcc_assert_true( ! is_wp_error( $post_id ), 'Post must be created.' );
		abcc_assert_same( 'publish', get_post( $post_id )->post_status, 'A user with publish_posts keeps Publish.' );
	}
);

abcc_test(
	'audio attachment validation rejects non-audio, disabled feature and unsupported formats',
	function () {
		$image = wp_insert_post(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
			)
		);
		abcc_assert_true( is_wp_error( abcc_validate_audio_attachment( $image ) ), 'An image is not an audio attachment.' );

		$audio = wp_insert_post(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'audio/mpeg',
			)
		);
		touch( get_attached_file( $audio ) );

		$GLOBALS['abcc_test_options']['abcc_enable_audio_transcription'] = false;
		abcc_assert_same( 'abcc_audio_disabled', abcc_validate_audio_attachment( $audio )->get_error_code() );

		$GLOBALS['abcc_test_options']['abcc_enable_audio_transcription'] = true;
		$GLOBALS['abcc_test_options']['abcc_supported_audio_formats']    = array( 'wav' );
		abcc_assert_same( 'abcc_audio_format', abcc_validate_audio_attachment( $audio )->get_error_code() );

		$GLOBALS['abcc_test_options']['abcc_supported_audio_formats'] = array( 'mp3' );
		abcc_assert_true( true === abcc_validate_audio_attachment( $audio ), 'A supported audio file passes.' );

		unlink( get_attached_file( $audio ) );
	}
);

abcc_test(
	'audio generate-post handler requires create_posts for the post type',
	function () {
		$GLOBALS['abcc_test_last_json']         = null;
		$GLOBALS['abcc_test_current_user_caps'] = array( 'edit_posts' => false );
		$_POST                                  = array(
			'attachment_id' => 5,
			'mode'          => 'transcript_plus_intro',
		);

		abcc_handle_audio_generate_post();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'Permission denied.', $GLOBALS['abcc_test_last_json']['data']['message'] );
		$_POST = array();
	}
);
