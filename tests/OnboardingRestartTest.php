<?php
/**
 * Restart setup wizard must survive abcc_check_existing_user_on_activation.
 */

abcc_test(
	'restart flag reopens the wizard even after admin_init re-completes onboarding',
	function () {
		// The restarting user has keys — that is the whole point of the button.
		abcc_update_setting( 'openai_api_key', 'sk-existing' );
		abcc_update_setting( 'abcc_onboarding_completed', false ); // What the button does.

		// admin_init fires before the page callback and re-flips the flag
		// whenever a key exists. The restart gate must not depend on it.
		abcc_check_existing_user_on_activation();
		abcc_assert_true(
			(bool) get_option( 'abcc_onboarding_completed', false ),
			'Precondition: admin_init hook re-completes onboarding for key-holders.'
		);

		// The gate as written in abcc_openai_text_settings_page():
		// restart flag first, completed/no-key check second.
		$restart = true;
		$shows_wizard = $restart || ( ! get_option( 'abcc_onboarding_completed', false ) && ! abcc_has_any_api_key() );

		abcc_assert_true( $shows_wizard, 'The restart flag must open the wizard regardless of the completed flag.' );

		// And the gate source must actually check the flag before the option —
		// guard against the || being refactored back into an &&.
		$gate_source = file_get_contents( dirname( __DIR__ ) . '/admin.php' );
		abcc_assert_true(
			false !== strpos( $gate_source, '$abcc_onboarding_restart || (' ),
			'admin.php must gate on the restart flag FIRST (|| not &&) — abcc_check_existing_user_on_activation() re-completes onboarding on admin_init for every key-holder.'
		);
	}
);

abcc_test(
	'settings-page actions run on load-{page_hook}, before any admin output',
	function () {
		// The render callback runs AFTER admin-header.php has sent output and
		// fired admin_notices, so redirects, download headers, and notice
		// registration only work from the load- hook. Guard the wiring.
		$source = file_get_contents( dirname( __DIR__ ) . '/admin.php' );

		abcc_assert_true(
			false !== strpos( $source, "add_action( 'load-' . \$page_hook, 'abcc_handle_settings_page_actions' )" ),
			'admin.php must register abcc_handle_settings_page_actions on load-{page_hook}.'
		);

		$actions_pos = strpos( $source, 'function abcc_handle_settings_page_actions()' );
		$render_pos  = strpos( $source, 'function abcc_openai_text_settings_page()' );
		$restart_pos = strpos( $source, "'restart_onboarding' === \$action" );
		$export_pos  = strpos( $source, 'abcc_export_settings' );

		abcc_assert_true( false !== $actions_pos && false !== $render_pos, 'Both functions must exist.' );
		abcc_assert_true(
			$restart_pos > $actions_pos && $restart_pos < $render_pos,
			'The restart redirect must live in the load- handler, not the render callback (headers are already sent there).'
		);
		abcc_assert_true(
			$export_pos > $actions_pos && $export_pos < $render_pos,
			'The settings export (sends download headers) must live in the load- handler.'
		);
	}
);
