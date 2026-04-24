<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/audio.php';
require_once dirname( __DIR__ ) . '/includes/infographic.php';

abcc_test(
	'prompt permission allows configured non-admin roles without prompt_ai capability',
	function () {
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'editor' );
		$GLOBALS['abcc_test_current_user_caps']             = array(
			'prompt_ai' => false,
		);
		$GLOBALS['abcc_test_current_user_roles']            = array( 'editor' );
		$GLOBALS['abcc_test_current_user_exists']           = true;

		abcc_assert_true( abcc_current_user_can_prompt(), 'Editors in allowed roles should be able to prompt AI.' );
	}
);

abcc_test(
	'prompt permission rejects disallowed roles without prompt_ai capability',
	function () {
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'editor' );
		$GLOBALS['abcc_test_current_user_caps']             = array(
			'prompt_ai' => false,
		);
		$GLOBALS['abcc_test_current_user_roles']            = array( 'author' );
		$GLOBALS['abcc_test_current_user_exists']           = true;

		abcc_assert_false( abcc_current_user_can_prompt(), 'Authors outside allowed roles should be denied.' );
	}
);

abcc_test(
	'audio transcription handler respects plugin prompt permission gate',
	function () {
		$GLOBALS['abcc_test_last_json']                     = null;
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'editor' );
		$GLOBALS['abcc_test_current_user_caps']             = array(
			'prompt_ai'    => false,
			'upload_files' => true,
		);
		$GLOBALS['abcc_test_current_user_roles']            = array( 'author' );
		$_POST                                                = array();

		abcc_handle_audio_transcription();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'], 'Audio transcription should fail when prompt permission is denied.' );
		abcc_assert_same( 'Permission denied', $GLOBALS['abcc_test_last_json']['data']['message'] );
	}
);

abcc_test(
	'create post from transcript respects plugin prompt permission gate',
	function () {
		$GLOBALS['abcc_test_last_json']                     = null;
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'editor' );
		$GLOBALS['abcc_test_current_user_caps']             = array(
			'prompt_ai'  => false,
			'edit_posts' => true,
		);
		$GLOBALS['abcc_test_current_user_roles']            = array( 'author' );
		$_POST                                                = array();

		abcc_handle_create_post_from_transcript();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'], 'Transcript post creation should fail when prompt permission is denied.' );
		abcc_assert_same( 'Permission denied', $GLOBALS['abcc_test_last_json']['data']['message'] );
	}
);

abcc_test(
	'infographic handler respects plugin prompt permission gate',
	function () {
		$GLOBALS['abcc_test_last_json']                     = null;
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'editor' );
		$GLOBALS['abcc_test_current_user_caps']             = array(
			'prompt_ai' => false,
			'edit_post' => true,
		);
		$GLOBALS['abcc_test_current_user_roles']            = array( 'author' );
		$_POST                                                = array(
			'nonce'   => 'test',
			'post_id' => 123,
		);

		abcc_handle_create_infographic();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'], 'Infographic generation should fail when prompt permission is denied.' );
		abcc_assert_same( 'Permission denied', $GLOBALS['abcc_test_last_json']['data']['message'] );
	}
);
