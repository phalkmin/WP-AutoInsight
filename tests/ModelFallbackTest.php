<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'Claude model registry contains expected 4.6/4.7 models',
	function () {
		$registry = abcc_get_provider_registry();
		$models   = array_keys( $registry['claude']['text_models'] );

		if ( ! in_array( 'claude-sonnet-4-6', $models, true ) ) {
			throw new Exception( 'claude-sonnet-4-6 missing from Claude model registry' );
		}

		if ( ! in_array( 'claude-opus-4-7', $models, true ) ) {
			throw new Exception( 'claude-opus-4-7 missing from Claude model registry' );
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
		abcc_assert_same( 'claude-haiku-4-5-20251001', $default );
	}
);

abcc_test(
	'stale Claude 4.5 model IDs are absent from registry and default fallback is Haiku',
	function () {
		$registry    = abcc_get_provider_registry();
		$claude_models = array_keys( $registry['claude']['text_models'] );

		$stale_ids = array( 'claude-sonnet-4-5-20250929', 'claude-opus-4-5-20251101' );
		foreach ( $stale_ids as $stale ) {
			if ( in_array( $stale, $claude_models, true ) ) {
				throw new Exception( sprintf( '"%s" should have been replaced in the registry but is still present', $stale ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		$fallback = abcc_get_provider_default_model( 'claude' );
		abcc_assert_same( 'claude-haiku-4-5-20251001', $fallback );
	}
);
