<?php

abcc_test(
	'provider registry exposes expected providers and capabilities',
	function () {
		$registry = abcc_get_provider_registry();

		abcc_assert_array_has_key( 'openai', $registry );
		abcc_assert_array_has_key( 'claude', $registry );
		abcc_assert_array_has_key( 'gemini', $registry );
		abcc_assert_array_has_key( 'perplexity', $registry );
		abcc_assert_array_has_key( 'stability', $registry );
		abcc_assert_true( abcc_provider_supports_image_generation( 'gemini' ) );
		abcc_assert_false( abcc_provider_supports_image_generation( 'claude' ) );
		abcc_assert_true( abcc_provider_supports_citations( 'perplexity' ) );
		abcc_assert_same( 'openai', abcc_get_model_provider( 'gpt-4.1-mini-2025-04-14' ) );
		abcc_assert_same( 'gemini', abcc_get_model_provider( 'gemini-2.5-flash' ) );
		abcc_assert_same( 'perplexity', abcc_get_model_provider( 'sonar' ) );
	}
);
