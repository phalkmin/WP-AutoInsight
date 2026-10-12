<?php
/**
 * Whisper gets a language only when the admin picked one (C-09).
 */

function abcc_test_whisper_request_body( $language_setting ) {
	$GLOBALS['abcc_test_options']['abcc_transcription_language'] = $language_setting;
	$file = sys_get_temp_dir() . '/abcc-whisper-test.mp3';
	file_put_contents( $file, 'audio-bytes' );
	abcc_test_queue_http_response( abcc_test_fake_transcription( 'hello' ) );
	abcc_transcribe_audio( 'k', $file );
	unlink( $file );
	return (string) $GLOBALS['abcc_http_last_request']['args']['body'];
}

abcc_test(
	'transcription language defaults to auto',
	function () {
		abcc_assert_same( 'auto', abcc_get_setting_default( 'abcc_transcription_language' ) );
	}
);

abcc_test(
	'auto sends no language field; an explicit language is sent',
	function () {
		abcc_assert_false( false !== strpos( abcc_test_whisper_request_body( 'auto' ), 'name="language"' ), 'auto must omit the field.' );
		abcc_assert_false( false !== strpos( abcc_test_whisper_request_body( '' ), 'name="language"' ), 'empty must omit the field.' );
		abcc_assert_true( false !== strpos( abcc_test_whisper_request_body( 'pt' ), "name=\"language\"\r\n\r\npt\r\n" ), 'pt must be sent.' );
	}
);
