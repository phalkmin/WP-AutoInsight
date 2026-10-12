<?php
/**
 * Export carries portable settings only (P2-17).
 */

abcc_test(
	'export excludes credentials and internal state and normalizes booleans',
	function () {
		$GLOBALS['abcc_test_options']['openai_api_key']            = 'sk';
		$GLOBALS['abcc_test_options']['abcc_onboarding_completed'] = '1';
		$GLOBALS['abcc_test_options']['openai_generate_seo']       = '';

		$exported = abcc_get_exportable_settings();

		foreach ( array( 'openai_api_key', 'claude_api_key', 'abcc_version', 'abcc_onboarding_completed', 'abcc_topics_last_sweep', 'abcc_composer_last_source' ) as $key ) {
			abcc_assert_false( array_key_exists( $key, $exported ), $key . ' must not be exported.' );
		}
		abcc_assert_true( array_key_exists( 'openai_char_limit', $exported ) );
		abcc_assert_same( abcc_get_setting_definition( 'openai_char_limit' )['default'], $exported['openai_char_limit'], 'An unset setting exports its schema default.' );
		abcc_assert_same( false, $exported['openai_generate_seo'], 'Booleans export as real booleans.' );
	}
);
