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

abcc_test(
	'abcc_content_language is declared with a site default and sanitizer',
	function () {
		$definition = abcc_get_setting_definition( 'abcc_content_language' );

		abcc_assert_true( is_array( $definition ), 'abcc_content_language must be declared in the schema.' );
		abcc_assert_same( 'site', $definition['default'], 'Default must be "site".' );
		abcc_assert_same(
			'abcc_sanitize_content_language',
			$definition['sanitize'],
			'Must declare the language sanitizer.'
		);
	}
);

abcc_test(
	'upgrades refresh the read-only default template and keep custom ones',
	function () {
		abcc_update_setting(
			'abcc_content_templates',
			array(
				'default' => array(
					'name'   => 'Default Template',
					'prompt' => 'Old pre-4.4 one-liner about {keyword}',
				),
				'mine'    => array(
					'name'   => 'Mine',
					'prompt' => 'Custom {keyword}',
				),
			)
		);
		update_option( 'abcc_version', '4.3.0' );

		abcc_run_settings_migrations();

		$templates = abcc_get_setting( 'abcc_content_templates', array() );

		abcc_assert_same(
			abcc_get_default_content_template(),
			$templates['default'],
			'An upgrade must refresh the read-only default template.'
		);
		abcc_assert_same(
			'Custom {keyword}',
			$templates['mine']['prompt'],
			'Custom templates must be untouched by the refresh.'
		);
		abcc_assert_same( ABCC_VERSION, get_option( 'abcc_version' ), 'Version must be stamped after migration.' );
	}
);
