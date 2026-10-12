<?php
/**
 * Uninstall cleans what the plugin created (C-21).
 */

abcc_test(
	'uninstall removes prompt_ai, the migration notice, citation transients, and loops over sites',
	function () {
		$src = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
		abcc_assert_true( false !== strpos( $src, "remove_cap( 'prompt_ai' )" ) );
		abcc_assert_true( false !== strpos( $src, 'abcc_settings_migration_notice' ) );
		abcc_assert_true(
			1 === preg_match(
				'/\$wpdb->prepare\(\s*"DELETE FROM \{\$wpdb->options\}[^"]*",\s*\$wpdb->esc_like\( \'_transient_abcc_\' \) \. \'%\',\s*\$wpdb->esc_like\( \'_transient_timeout_abcc_\' \) \. \'%\'/',
				$src
			),
			'Prefix delete must go through prepare() with esc_like() for both transient and timeout rows.'
		);
		abcc_assert_true( false !== strpos( $src, 'is_multisite()' ) && false !== strpos( $src, 'switch_to_blog' ) );
	}
);
