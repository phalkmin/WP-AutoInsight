<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'credential resolution respects connector, constant, and option sources',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'connectors_ai_anthropic_api_key' => 'connector-claude-key',
			'gemini_api_key'                  => 'option-gemini-key',
		);
		$GLOBALS['abcc_test_connectors'] = array( 'anthropic' );

		abcc_assert_same( 'const-openai-key', abcc_get_provider_api_key( 'openai' ) );
		abcc_assert_same( 'connector-claude-key', abcc_get_provider_api_key( 'claude' ) );
		abcc_assert_same( 'option-gemini-key', abcc_get_provider_api_key( 'gemini' ) );
		abcc_assert_same( 'wp_connector', abcc_get_provider_credential_source( 'claude' ) );
		abcc_assert_same( 'constant', abcc_get_provider_credential_source( 'openai' ) );
		abcc_assert_same( 'option', abcc_get_provider_credential_source( 'gemini' ) );
	}
);

abcc_test(
	'blank API key submission preserves the stored key',
	function () {
		abcc_set_provider_saved_api_key( 'openai', 'sk-existing-value' );

		// Simulates the settings form submitting an untouched (blank) key field.
		abcc_save_provider_api_key_submission( 'openai', '' );

		abcc_assert_same(
			'sk-existing-value',
			abcc_get_provider_saved_api_key( 'openai' ),
			'A blank submission must not clear the stored key.'
		);

		abcc_save_provider_api_key_submission( 'openai', 'sk-replacement' );
		abcc_assert_same(
			'sk-replacement',
			abcc_get_provider_saved_api_key( 'openai' ),
			'A non-blank submission must replace the stored key.'
		);
	}
);
