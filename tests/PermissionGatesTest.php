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

abcc_test(
	'API key validation rejects a provider that is not in the registry',
	function () {
		$_POST = array(
			'provider' => 'not_a_real_provider',
			'nonce'    => 'x',
		);

		abcc_handle_validate_api_key();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( is_array( $json ), 'Handler should have sent a JSON response.' );
		abcc_assert_false( $json['success'], 'An unknown provider must be rejected.' );
		abcc_assert_false(
			false !== get_option( 'abcc_last_validation_not_a_real_provider' ),
			'An unknown provider must not create an option.'
		);

		$_POST = array();
	}
);

abcc_test(
	'rewrite and regenerate reject posts the user cannot edit',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Someone else\'s post',
				'post_content' => 'Body.',
			)
		);

		// User has prompt access but not edit_post on this specific post.
		$GLOBALS['abcc_test_uneditable_posts'] = array( $post_id );

		foreach ( array( 'abcc_handle_rewrite_post', 'abcc_handle_regenerate_post' ) as $handler ) {
			$GLOBALS['abcc_test_last_json'] = null;
			$_POST                          = array(
				'post_id' => $post_id,
				'nonce'   => 'x',
			);

			$handler();

			$json = $GLOBALS['abcc_test_last_json'];
			abcc_assert_true( is_array( $json ), $handler . ' should have sent a JSON response.' );
			abcc_assert_false(
				$json['success'],
				$handler . ' must reject a post the user cannot edit.'
			);
		}

		$_POST = array();
	}
);

abcc_test(
	'onboarding test-api handler rejects users without manage_options',
	function () {
		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$_POST                                  = array(
			'provider' => 'openai',
			'api_key'  => 'sk-should-not-be-saved',
			'nonce'    => 'x',
		);

		abcc_handle_onboarding_test_api();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( is_array( $json ), 'Handler should have sent a JSON response.' );
		abcc_assert_false( $json['success'], 'Handler must reject a user without manage_options.' );
		abcc_assert_same(
			'',
			(string) abcc_get_provider_saved_api_key( 'openai' ),
			'Handler must not persist an API key for an unauthorised user.'
		);

		$_POST = array();
	}
);
