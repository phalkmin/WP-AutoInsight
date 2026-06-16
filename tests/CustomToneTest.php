<?php
/**
 * Regression: 'custom' tone must resolve to the user's custom_tone text (review #3).
 */

abcc_test(
	'custom tone: resolves to custom_tone description',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_tone'   => 'custom',
			'custom_tone'   => 'snarky and irreverent',
			'prompt_select' => 'gpt-4.1-mini-2025-04-14',
		);

		$payload = abcc_build_generation_payload( array() );

		abcc_assert_same( 'snarky and irreverent', $payload['tone'], 'Custom tone should resolve to the custom_tone setting value' );
	}
);

abcc_test(
	'custom tone: non-custom tone passes through unchanged',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_tone'   => 'professional',
			'prompt_select' => 'gpt-4.1-mini-2025-04-14',
		);

		$payload = abcc_build_generation_payload( array() );

		abcc_assert_same( 'professional', $payload['tone'], 'Non-custom tone should pass through unchanged' );
	}
);

abcc_test(
	'custom tone: empty custom_tone falls back to professional (not literal "custom")',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_tone'   => 'custom',
			'custom_tone'   => '',
			'prompt_select' => 'gpt-4.1-mini-2025-04-14',
		);

		$payload = abcc_build_generation_payload( array() );

		// Empty custom text must fall back to the default tone, not leave literal 'custom' in the prompt.
		abcc_assert_same( 'professional', $payload['tone'], 'Empty custom_tone must fall back to professional' );
	}
);
