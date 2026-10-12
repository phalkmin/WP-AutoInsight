<?php
/**
 * Reset All Settings resets settings, not credentials or install state (P2-02 / C-07).
 */

abcc_test(
	'reset keeps API keys, version and onboarding, and restores defaults elsewhere',
	function () {
		$GLOBALS['abcc_test_options']['claude_api_key']            = 'sk-keep';
		$GLOBALS['abcc_test_options']['abcc_version']              = '4.5.2';
		$GLOBALS['abcc_test_options']['abcc_onboarding_completed'] = '1';
		$GLOBALS['abcc_test_options']['openai_char_limit']         = 3000;
		$GLOBALS['abcc_test_options']['abcc_default_post_status']  = 'publish';

		abcc_reset_settings();

		abcc_assert_same( 'sk-keep', get_option( 'claude_api_key' ) );
		abcc_assert_same( '4.5.2', get_option( 'abcc_version' ) );
		abcc_assert_same( '1', get_option( 'abcc_onboarding_completed' ) );
		abcc_assert_same( 200, (int) abcc_get_setting( 'openai_char_limit' ) );
		abcc_assert_same( 'draft', abcc_get_setting( 'abcc_default_post_status' ) );
	}
);

abcc_test(
	'reset revokes prompt_ai from roles the default list does not include',
	function () {
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'administrator', 'editor', 'author' );
		abcc_sync_prompt_ai_capability( array( 'administrator', 'editor', 'author' ) );
		abcc_assert_true( get_role( 'author' )->has_cap( 'prompt_ai' ) );

		abcc_reset_settings();

		abcc_assert_false( get_role( 'author' )->has_cap( 'prompt_ai' ), 'Author is not in the default allowed roles.' );
		abcc_assert_true( get_role( 'editor' )->has_cap( 'prompt_ai' ) );
	}
);
