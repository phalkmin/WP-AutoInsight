<?php

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
