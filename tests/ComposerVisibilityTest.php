<?php
/**
 * The Composer must send exactly the overrides the user can see.
 */

abcc_test(
	'composer hidden fields are not shown by the grid rule',
	function () {
		$css = file_get_contents( dirname( __DIR__ ) . '/css/admin.css' );

		abcc_assert_true(
			1 === preg_match( '/\.abcc-composer__fields\[hidden\]\s*\{[^}]*display:\s*none\s*(!important)?;?[^}]*\}/', $css ),
			'.abcc-composer__fields[hidden] must set display:none, or the grid rule shows collapsed fields.'
		);
	}
);

abcc_test(
	'composer sends overrides when the fields are visible, not when the toggle says so',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/admin.js' );

		abcc_assert_true(
			false !== strpos( $js, 'function abccComposerFieldsVisible()' ),
			'A visibility helper must exist.'
		);
		abcc_assert_true(
			false !== strpos( $js, 'if (abccComposerFieldsVisible()) {' ),
			'Generate Now must gate overrides on the helper.'
		);
		abcc_assert_false(
			false !== strpos( $js, '$("#abcc-composer-toggle").attr("aria-expanded") === "true"' ),
			'The aria-expanded gate must be gone.'
		);
	}
);
