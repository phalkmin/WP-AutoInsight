<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'Claude model registry contains expected 4.5/4.6/4.8 models',
	function () {
		$registry = abcc_get_provider_registry();
		$models   = array_keys( $registry['claude']['text_models'] );

		if ( ! in_array( 'claude-sonnet-4-6', $models, true ) ) {
			throw new Exception( 'claude-sonnet-4-6 missing from Claude model registry' );
		}

		if ( ! in_array( 'claude-opus-4-8', $models, true ) ) {
			throw new Exception( 'claude-opus-4-8 missing from Claude model registry' );
		}

		if ( ! in_array( 'claude-haiku-4-5-20251001', $models, true ) ) {
			throw new Exception( 'claude-haiku-4-5-20251001 missing from Claude model registry' );
		}
	}
);

abcc_test(
	'abcc_get_provider_default_model returns first registered Claude model',
	function () {
		$default = abcc_get_provider_default_model( 'claude' );
		abcc_assert_same( 'claude-haiku-5-5', $default );
	}
);

abcc_test(
	'Claude Sonnet 4.5 aliases remain legacy entries and default fallback is Haiku',
	function () {
		$registry      = abcc_get_provider_registry();
		$claude_models = $registry['claude']['text_models'];

		foreach ( array( 'claude-sonnet-4-5', 'claude-sonnet-4-5-20250929' ) as $legacy ) {
			abcc_assert_array_has_key( $legacy, $claude_models );
			abcc_assert_true( ! empty( $claude_models[ $legacy ]['legacy'] ) );
		}
		abcc_assert_false( isset( $claude_models['claude-opus-4-5-20251101'] ) );

		$fallback = abcc_get_provider_default_model( 'claude' );
		abcc_assert_same( 'claude-haiku-5-5', $fallback );
	}
);

abcc_test(
	'each provider starts new users on its registry default model',
	function () {
		abcc_assert_same( 'gpt-5.4-mini', abcc_get_provider_default_model( 'openai' ) );
		abcc_assert_same( 'claude-haiku-5-5', abcc_get_provider_default_model( 'claude' ) );
		abcc_assert_same( 'gemini-3.5-flash-lite', abcc_get_provider_default_model( 'gemini' ), 'Gemini names its default; the list still opens with 2.5 Flash-Lite.' );
		abcc_assert_same( 'sonar', abcc_get_provider_default_model( 'perplexity' ) );

		// A site that skips the wizard lands on the same model.
		$grouped = array(
			'gemini' => array(
				'group'   => 'Google Gemini Models',
				'options' => array(
					'gemini-2.5-flash-lite' => array( 'name' => 'Gemini 2.5 Flash-Lite' ),
					'gemini-3.5-flash-lite' => array( 'name' => 'Gemini 3.5 Flash-Lite' ),
				),
			),
		);
		abcc_assert_same( 'gemini::gemini-3.5-flash-lite', abcc_get_first_available_model_selection( $grouped ) );
	}
);

abcc_test(
	'a default_model the provider does not list falls back to its first model',
	function () {
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['gemini']['default_model'] = 'gemini-does-not-exist';
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();

		abcc_assert_same( 'gemini-2.5-flash-lite', abcc_get_provider_default_model( 'gemini' ) );
	}
);
