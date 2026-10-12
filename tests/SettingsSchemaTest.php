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

abcc_test(
	'selected post types drop attachments and unknown types, and fall back to post',
	function () {
		abcc_assert_same( array( 'post', 'page' ), abcc_sanitize_selected_post_types( array( 'post', 'attachment', 'page', 'nope' ) ) );
		abcc_assert_same( array( 'post' ), abcc_sanitize_selected_post_types( array() ) );
		abcc_assert_same( array( 'post' ), abcc_sanitize_selected_post_types( array( 'attachment' ) ) );
	}
);

abcc_test(
	'template slugs resolve to their names, unknown slugs stay as-is',
	function () {
		$GLOBALS['abcc_test_options']['abcc_content_templates'] = array(
			'default' => abcc_get_default_content_template(),
			'news'    => array(
				'name'   => 'News brief',
				'prompt' => 'x',
			),
		);
		abcc_assert_same( 'Default Template', abcc_get_content_template_name( 'default' ) );
		abcc_assert_same( 'News brief', abcc_get_content_template_name( 'news' ) );
		abcc_assert_same( 'gone', abcc_get_content_template_name( 'gone' ) );
	}
);

abcc_test(
	'an empty template slug reads as default',
	function () {
		$GLOBALS['abcc_test_options']['abcc_content_templates'] = array();
		abcc_assert_same( 'default', abcc_get_content_template_name( '' ) );
	}
);

abcc_test(
	'infographic provider accepts auto and every image-capable registry provider, nothing else',
	function () {
		$image_providers = array();
		foreach ( abcc_get_provider_ids() as $provider_id ) {
			if ( abcc_provider_supports_image_generation( $provider_id ) ) {
				$image_providers[] = $provider_id;
				abcc_assert_same( $provider_id, abcc_sanitize_infographic_provider( $provider_id ), $provider_id . ' is image-capable.' );
			} else {
				abcc_assert_same( 'auto', abcc_sanitize_infographic_provider( $provider_id ), $provider_id . ' cannot draw images.' );
			}
		}
		abcc_assert_true( count( $image_providers ) >= 3, 'The registry lists the image providers.' );
		abcc_assert_same( 'auto', abcc_sanitize_infographic_provider( 'auto' ) );
		abcc_assert_same( 'auto', abcc_sanitize_infographic_provider( 'midjourney' ) );
		abcc_assert_same( 'abcc_sanitize_infographic_provider', abcc_get_setting_definition( 'abcc_infographic_provider' )['sanitize'] );
	}
);
