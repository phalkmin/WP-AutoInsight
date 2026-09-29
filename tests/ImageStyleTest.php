<?php
/**
 * Regression tests for the image style setting and prompt builder (v4.5 Unit D).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'image style sanitizer round-trips presets and falls back to the default',
	function () {
		foreach ( array_keys( abcc_get_image_style_options() ) as $slug ) {
			abcc_assert_same( $slug, abcc_sanitize_image_style( $slug ), $slug . ' must round-trip.' );
		}
		abcc_assert_same( 'editorial photography', abcc_sanitize_image_style( 'banana' ) );
		abcc_assert_same( 'editorial photography', abcc_sanitize_image_style( array( 'custom' ) ) );
		abcc_assert_same( 'editorial photography', abcc_sanitize_image_style( null ) );
	}
);

abcc_test(
	'custom image style text is stripped and capped at 200 characters',
	function () {
		abcc_assert_same( 'vintage poster', abcc_sanitize_image_style_custom( '<b>vintage</b> poster' ) );
		abcc_assert_same( 200, strlen( abcc_sanitize_image_style_custom( str_repeat( 'a', 250 ) ) ) );
		abcc_assert_same( '', abcc_sanitize_image_style_custom( array() ) );
	}
);

abcc_test(
	'effective image style resolves presets, custom text, and blank custom',
	function () {
		$GLOBALS['abcc_test_options'] = array( 'abcc_image_style' => 'watercolor' );
		abcc_assert_same( 'watercolor', abcc_get_effective_image_style() );

		$GLOBALS['abcc_test_options'] = array(
			'abcc_image_style'        => 'custom',
			'abcc_image_style_custom' => 'vintage travel poster, muted palette',
		);
		abcc_assert_same( 'vintage travel poster, muted palette', abcc_get_effective_image_style() );

		$GLOBALS['abcc_test_options'] = array(
			'abcc_image_style'        => 'custom',
			'abcc_image_style_custom' => '',
		);
		abcc_assert_same( 'editorial photography', abcc_get_effective_image_style(), 'Blank custom falls back to the default preset.' );

		$GLOBALS['abcc_test_options'] = array();
		abcc_assert_same( 'editorial photography', abcc_get_effective_image_style(), 'Fresh install uses the default preset.' );
	}
);

abcc_test(
	'image prompt has the exact style-aware shape with the default style',
	function () {
		abcc_assert_same(
			'acids, bases. Related to: Chemistry. Style: editorial photography. Clean composition, natural lighting, no text, no watermarks, no logos.',
			abcc_build_image_prompt( array( 'acids', 'bases' ), array( 'Chemistry' ) )
		);
	}
);

abcc_test(
	'image prompt with no keywords or categories starts at the style clause',
	function () {
		$prompt = abcc_build_image_prompt( array(), array() );
		abcc_assert_same( 0, strpos( $prompt, 'Style:' ), 'No leading ". " artefacts: ' . $prompt );
	}
);

abcc_test(
	'a custom style ending in a period does not produce a double period',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_image_style'        => 'custom',
			'abcc_image_style_custom' => 'oil painting.',
		);
		$prompt = abcc_build_image_prompt( array( 'sea' ), array() );
		abcc_assert_false( false !== strpos( $prompt, '..' ), 'Double period in: ' . $prompt );
		abcc_assert_true( false !== strpos( $prompt, 'Style: oil painting.' ), $prompt );
	}
);

abcc_test(
	'image style settings are declared in the schema with sanitizers',
	function () {
		$style = abcc_get_setting_definition( 'abcc_image_style' );
		abcc_assert_same( 'editorial photography', $style['default'] );
		abcc_assert_same( 'abcc_sanitize_image_style', $style['sanitize'] );

		$custom = abcc_get_setting_definition( 'abcc_image_style_custom' );
		abcc_assert_same( '', $custom['default'] );
		abcc_assert_same( 'abcc_sanitize_image_style_custom', $custom['sanitize'] );
	}
);
