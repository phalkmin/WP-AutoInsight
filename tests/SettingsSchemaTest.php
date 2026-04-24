<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'settings schema migration preserves keywords and templates',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_version'               => '3.4.0',
			'openai_keywords'            => "alpha\nbeta",
			'openai_selected_categories' => array( 7 ),
		);

		abcc_run_settings_migrations();

		$groups    = get_option( 'abcc_keyword_groups', array() );
		$templates = get_option( 'abcc_content_templates', array() );
		$notice    = get_option( 'abcc_settings_migration_notice', array() );

		abcc_assert_same( ABCC_VERSION, get_option( 'abcc_version' ) );
		abcc_assert_same( 'Default Group', $groups[0]['name'] );
		abcc_assert_equals( array( 'alpha', 'beta' ), $groups[0]['keywords'] );
		abcc_assert_same( 7, $groups[0]['category'] );
		abcc_assert_array_has_key( 'default', $templates );
		abcc_assert_same( '3.4.0', $notice['from'] );
		abcc_assert_same( ABCC_VERSION, $notice['to'] );
	}
);
