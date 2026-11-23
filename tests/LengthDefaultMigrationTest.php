<?php
/**
 * 4.6 length default (v4.6 §5.3): 1200 for new installs, 200 pinned for
 * upgrades that never saved a length, saved values untouched.
 */

abcc_test(
	'fresh install gets 1200 and no length notice; answer-focused structure is off',
	function () {
		abcc_run_settings_migrations();

		abcc_assert_same( 1200, (int) abcc_get_setting( 'openai_char_limit' ) );
		abcc_assert_false( array_key_exists( 'openai_char_limit', $GLOBALS['abcc_test_options'] ), 'A fresh install stores nothing; the schema default applies.' );
		abcc_assert_false( array_key_exists( 'abcc_length_notice', $GLOBALS['abcc_test_options'] ) );
		abcc_assert_false( abcc_resolve_answer_mode() );
	}
);

abcc_test(
	'upgrade with no saved length is pinned to 200 and queues the notice once',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_version'   => '4.5.1',
			'openai_api_key' => 'sk-test',
		);
		abcc_run_settings_migrations();

		abcc_assert_same( 200, $GLOBALS['abcc_test_options']['openai_char_limit'] );
		abcc_assert_same( 1, (int) get_option( 'abcc_length_notice' ) );
		abcc_assert_false( abcc_resolve_answer_mode(), 'Upgrades do not turn the answer-focused structure on.' );

		// A later run (next version) does not re-queue a dismissed-by-deletion notice or touch the value.
		update_option( 'abcc_length_notice', 0 );
		update_option( 'abcc_version', '4.5.1' );
		update_option( 'openai_char_limit', 800 );
		abcc_run_settings_migrations();

		abcc_assert_same( 800, (int) get_option( 'openai_char_limit' ) );
		abcc_assert_same( 0, (int) get_option( 'abcc_length_notice' ) );
	}
);

abcc_test(
	'pre-versioned installs with only legacy schema options get upgrade length behavior',
	function () {
		$legacy_options = array(
			array( 'openai_keywords' => "First keyword\nSecond keyword" ),
			array( 'openai_selected_categories' => array( 17 ) ),
			array( 'openai_tone' => 'professional' ),
			array( 'openai_auto_create' => '1' ),
			array( 'openai_generate_seo' => '0' ),
			array( 'openai_generate_images' => '0' ),
		);

		foreach ( $legacy_options as $options ) {
			$GLOBALS['abcc_test_options'] = $options;
			abcc_run_settings_migrations();

			abcc_assert_same( 200, (int) get_option( 'openai_char_limit' ) );
			abcc_assert_same( 1, (int) get_option( 'abcc_length_notice' ) );
			$notice = get_option( 'abcc_settings_migration_notice' );
			abcc_assert_same( '1.0.0', $notice['from'] );
			abcc_assert_same( ABCC_VERSION, $notice['to'] );
		}
	}
);

abcc_test(
	'upgrade with a saved length keeps it',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_version'      => '4.5.1',
			'openai_api_key'    => 'sk-test',
			'openai_char_limit' => 2500,
		);
		abcc_run_settings_migrations();

		abcc_assert_same( 2500, (int) get_option( 'openai_char_limit' ) );
	}
);

abcc_test(
	'length notice text gives both prices and disappears once the length reaches the default',
	function () {
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );
		abcc_update_setting( 'openai_char_limit', 200 );

		$text = abcc_get_length_notice_text();
		abcc_assert_true( 1 === preg_match( '/about 150 words \(≈\$[\d.]+ each\)\. You can now write full articles of about 900 words for ≈\$[\d.]+ each with your current model\./u', $text['message'] ), $text['message'] );
		abcc_assert_false( array_key_exists( 'detail', $text ) );

		abcc_update_setting( 'openai_char_limit', 1200 );
		abcc_assert_same( '', abcc_get_length_notice_text()['message'] );

		// Unknown pricing never prints a dollar amount.
		abcc_update_setting( 'openai_char_limit', 200 );
		abcc_update_setting( 'prompt_select', 'perplexity::sonar-pro' );
		$unknown = abcc_get_length_notice_text();
		abcc_assert_false( false !== strpos( $unknown['message'], '$' ) );
		abcc_assert_true( false !== strpos( $unknown['message'], 'unknown' ) );
	}
);

abcc_test(
	'length notice dismissal is nonce- and capability-gated and stored per user',
	function () {
		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$_POST                                  = array( 'nonce' => 'x' );
		abcc_handle_dismiss_length_notice();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( ! get_user_meta( get_current_user_id(), 'abcc_length_notice_dismissed', true ) );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => true );
		abcc_handle_dismiss_length_notice();
		$_POST = array();
		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 1, (int) get_user_meta( get_current_user_id(), 'abcc_length_notice_dismissed', true ) );
	}
);

abcc_test(
	'the length notice shows on the plugin screens and the Dashboard only',
	function () {
		update_option( 'abcc_length_notice', 1 );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );
		abcc_update_setting( 'openai_char_limit', 200 );

		$render = function ( $screen_id ) {
			$GLOBALS['abcc_test_current_screen'] = null === $screen_id ? null : (object) array( 'id' => $screen_id );
			ob_start();
			abcc_display_length_notice();
			return (string) ob_get_clean();
		};

		abcc_assert_true( false !== strpos( $render( 'dashboard' ), 'data-abcc-notice="length"' ) );
		abcc_assert_true( false !== strpos( $render( 'toplevel_page_automated-blog-content-creator-post' ), 'data-abcc-notice="length"' ) );
		abcc_assert_same( '', $render( 'plugins' ) );
		abcc_assert_same( '', $render( 'edit-post' ) );
		abcc_assert_same( '', $render( null ) );
	}
);
