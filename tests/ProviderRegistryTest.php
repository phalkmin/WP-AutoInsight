<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

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

abcc_test(
	'claude registry exposes opus 4.8 and not 4.7',
	function () {
		$registry = abcc_get_provider_registry();
		$models   = $registry['claude']['text_models'];
		abcc_assert_array_has_key( 'claude-opus-4-8', $models );
		abcc_assert_false( isset( $models['claude-opus-4-7'] ), 'Opus 4.7 should be replaced by 4.8.' );
	}
);

abcc_test(
	'openai registry adds gpt-5.x tier and flags 4.1 legacy',
	function () {
		$models = abcc_get_provider_registry()['openai']['text_models'];

		abcc_assert_array_has_key( 'gpt-5.4-mini', $models );
		abcc_assert_array_has_key( 'gpt-5.4', $models );
		abcc_assert_array_has_key( 'gpt-5.5', $models );
		abcc_assert_false( isset( $models['o4-mini-2025-04-16'] ), 'o4-mini should be removed.' );
		abcc_assert_false( isset( $models['o3-pro'] ), 'o3-pro must NOT be added (unconfirmed API model).' );

		// Legacy 4.1 entries retained and flagged.
		abcc_assert_array_has_key( 'gpt-4.1-mini-2025-04-14', $models );
		abcc_assert_true( ! empty( $models['gpt-4.1-mini-2025-04-14']['legacy'] ), '4.1 mini must be flagged legacy.' );
		abcc_assert_true( ! empty( $models['gpt-4.1-2025-04-14']['legacy'] ), '4.1 must be flagged legacy.' );
	}
);

abcc_test(
	'gemini registry adds 3.5 flash and keeps the 2.5 series',
	function () {
		$models = abcc_get_provider_registry()['gemini']['text_models'];
		abcc_assert_array_has_key( 'gemini-3.5-flash', $models );
		abcc_assert_array_has_key( 'gemini-2.5-flash', $models );
		abcc_assert_array_has_key( 'gemini-2.5-pro', $models );
	}
);

abcc_test(
	'perplexity registry adds sonar-deep-research carrying a cost warning',
	function () {
		$models = abcc_get_provider_registry()['perplexity']['text_models'];
		abcc_assert_array_has_key( 'sonar-deep-research', $models );
		abcc_assert_true( ! empty( $models['sonar-deep-research']['cost_warning'] ), 'Deep research must carry a cost warning.' );
	}
);

abcc_test(
	'abcc_format_model_option_label appends (legacy) for legacy models',
	function () {
		$label = abcc_format_model_option_label( 'gpt-4', array( 'name' => 'GPT-4', 'legacy' => true ) );
		abcc_assert_true( false !== strpos( $label, '(legacy)' ), 'Legacy model must include "(legacy)" in label.' );
		abcc_assert_true( 0 === strpos( $label, 'GPT-4' ), 'Label must start with the model name.' );
	}
);

abcc_test(
	'abcc_format_model_option_label returns plain name for normal models',
	function () {
		$label = abcc_format_model_option_label( 'gpt-4.1', array( 'name' => 'GPT-4.1' ) );
		abcc_assert_same( 'GPT-4.1', $label );
	}
);

abcc_test(
	'abcc_format_model_option_label falls back to model id when name is missing',
	function () {
		$label = abcc_format_model_option_label( 'unknown-model', array() );
		abcc_assert_same( 'unknown-model', $label );
	}
);
