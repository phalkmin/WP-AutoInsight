<?php
/**
 * "Transcribe Only" sends create_post=false; jQuery encodes that as the
 * string "false", which (bool) would read as true.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'transcribe-only request with the string "false" does not create a post',
	function () {
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk-test';
		$GLOBALS['abcc_test_current_user_caps']         = array( 'upload_files' => true );

		$audio_path = get_attached_file( 5 );
		file_put_contents( $audio_path, 'fake-audio-data' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		abcc_test_queue_http_response( abcc_test_fake_transcription( 'Only words.' ) );

		$posts_before = count( $GLOBALS['abcc_test_posts'] );
		$_POST        = array( 'attachment_id' => 5, 'create_post' => 'false', 'nonce' => 'x' );

		abcc_handle_audio_transcription();

		unlink( $audio_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		$_POST = array();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( $json['success'], 'Transcription must succeed: ' . ( $json['data']['message'] ?? '' ) );
		abcc_assert_same( 'Only words.', $json['data']['transcript'] );
		abcc_assert_false( isset( $json['data']['post_id'] ), 'No post may be created for transcribe-only.' );
		abcc_assert_same( $posts_before, count( $GLOBALS['abcc_test_posts'] ), 'No post inserted.' );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'Exactly one HTTP call: the transcription.' );
	}
);
