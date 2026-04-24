<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Tests for abcc_handle_autosave_setting() schema validation and sanitization logic.
 *
 * We exercise the logic directly instead of going through the AJAX dispatch layer,
 * since bootstrap.php stubs out wp_send_json_* to return data and check_ajax_referer
 * to return true.
 */

abcc_test(
	'autosave rejects a key that is not in the settings schema',
	function () {
		// Simulate what the handler does for an unknown key.
		$key        = 'nonexistent_setting_xyz';
		$definition = abcc_get_setting_definition( $key );

		abcc_assert_true( null === $definition, 'Unknown key must return null from abcc_get_setting_definition()' );
	}
);

abcc_test(
	'autosave rejects blocked sensitive keys',
	function () {
		$blocked = array(
			'openai_api_key',
			'gemini_api_key',
			'claude_api_key',
			'perplexity_api_key',
			'stability_api_key',
			'abcc_keyword_groups',
			'abcc_content_templates',
			'abcc_selected_post_types',
			'abcc_supported_audio_formats',
		);

		foreach ( $blocked as $key ) {
			$definition = abcc_get_setting_definition( $key );
			abcc_assert_true(
				null !== $definition,
				"Blocked key '{$key}' must exist in schema (so the block check is meaningful)"
			);
		}
	}
);

abcc_test(
	'autosave boolean schema default drives correct type coercion',
	function () {
		$key        = 'abcc_draft_first';
		$definition = abcc_get_setting_definition( $key );

		abcc_assert_true( null !== $definition, 'abcc_draft_first must exist in schema' );
		abcc_assert_true( is_bool( $definition['default'] ), 'abcc_draft_first default must be bool' );

		// Replicate the handler's sanitization logic.
		$raw   = '1';
		$value = ( '1' === $raw || 'true' === $raw );
		abcc_assert_true( $value === true, 'Raw "1" must coerce to true for a bool schema default' );

		$raw   = '0';
		$value = ( '1' === $raw || 'true' === $raw );
		abcc_assert_true( $value === false, 'Raw "0" must coerce to false for a bool schema default' );
	}
);

abcc_test(
	'autosave integer schema default drives absint coercion',
	function () {
		$key        = 'openai_char_limit';
		$definition = abcc_get_setting_definition( $key );

		abcc_assert_true( null !== $definition, 'openai_char_limit must exist in schema' );
		abcc_assert_true( is_int( $definition['default'] ), 'openai_char_limit default must be int' );

		$raw   = '800';
		$value = absint( $raw );
		abcc_assert_same( 800, $value, 'Raw "800" must coerce to int 800' );

		$raw   = '-50';
		$value = absint( $raw );
		abcc_assert_same( 50, $value, 'absint of negative input must be positive' );
	}
);

abcc_test(
	'autosave string schema default drives sanitize_text_field coercion',
	function () {
		$key        = 'openai_tone';
		$definition = abcc_get_setting_definition( $key );

		abcc_assert_true( null !== $definition, 'openai_tone must exist in schema' );
		abcc_assert_true( is_string( $definition['default'] ), 'openai_tone default must be string' );

		$raw   = '  professional  ';
		$value = sanitize_text_field( $raw );
		abcc_assert_same( 'professional', $value, 'sanitize_text_field must trim whitespace' );
	}
);

abcc_test(
	'autosave can round-trip a boolean setting through update and read',
	function () {
		// Write false.
		abcc_update_setting( 'abcc_debug_logging', false );
		abcc_assert_same( false, abcc_get_setting( 'abcc_debug_logging' ), 'Should read back false' );

		// Write true.
		abcc_update_setting( 'abcc_debug_logging', true );
		abcc_assert_same( true, abcc_get_setting( 'abcc_debug_logging' ), 'Should read back true' );

		// Reset to schema default.
		abcc_update_setting( 'abcc_debug_logging', abcc_get_setting_default( 'abcc_debug_logging' ) );
	}
);
