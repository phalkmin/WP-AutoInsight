<?php
/**
 * A fresh install has nothing to migrate and gets no migration notice (C-14).
 */

abcc_test(
	'fresh install seeds the version and queues no migration notice',
	function () {
		abcc_run_settings_migrations();

		abcc_assert_same( ABCC_VERSION, get_option( 'abcc_version' ) );
		abcc_assert_same( false, get_option( 'abcc_settings_migration_notice' ), 'Nothing was migrated.' );
	}
);

abcc_test(
	'a real upgrade still queues the notice',
	function () {
		$GLOBALS['abcc_test_options']['abcc_version']           = '4.4.0';
		$GLOBALS['abcc_test_options']['abcc_content_templates'] = array( 'default' => abcc_get_default_content_template() );

		abcc_run_settings_migrations();

		$notice = get_option( 'abcc_settings_migration_notice' );
		abcc_assert_same( '4.4.0', $notice['from'] );
	}
);

abcc_test(
	'an old install with settings but no version record is treated as an upgrade',
	function () {
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk-old';

		abcc_run_settings_migrations();

		$notice = get_option( 'abcc_settings_migration_notice' );
		abcc_assert_true( is_array( $notice ), 'Pre-3.3 installs had no version option but do have settings; they migrate.' );
	}
);
