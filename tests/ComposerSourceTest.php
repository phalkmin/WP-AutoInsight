<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'composer source sanitizer accepts valid shapes and rejects junk',
	function () {
		abcc_assert_same( '', abcc_sanitize_composer_source( '' ) );
		abcc_assert_same( 'group:0', abcc_sanitize_composer_source( 'group:0' ) );
		abcc_assert_same( 'group:12', abcc_sanitize_composer_source( 'group:12' ) );
		abcc_assert_same( 'topic:345', abcc_sanitize_composer_source( 'topic:345' ) );
		// Junk and malformed values collapse to empty.
		abcc_assert_same( '', abcc_sanitize_composer_source( 'group:' ) );
		abcc_assert_same( '', abcc_sanitize_composer_source( 'group:abc' ) );
		abcc_assert_same( '', abcc_sanitize_composer_source( 'post:5' ) );
		abcc_assert_same( '', abcc_sanitize_composer_source( '<script>' ) );
		abcc_assert_same( '', abcc_sanitize_composer_source( array( 'group:0' ) ) );
	}
);

abcc_test(
	'composer source setting is declared with empty default',
	function () {
		$schema = abcc_get_settings_schema();
		abcc_assert_array_has_key( 'abcc_composer_last_source', $schema['settings'] );
		abcc_assert_same( '', $schema['settings']['abcc_composer_last_source']['default'] );
	}
);

abcc_test(
	'resolver: empty token falls back to first group with keywords',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_keyword_groups' => array(
				array( 'name' => 'Empty', 'keywords' => array(), 'category' => 0, 'template' => 'default' ),
				array( 'name' => 'Chemistry', 'keywords' => array( 'acids', 'bases' ), 'category' => 7, 'template' => 'how-to' ),
			),
		);

		$resolved = abcc_resolve_composer_source( '' );

		abcc_assert_false( is_wp_error( $resolved ), 'Resolver should succeed when a group has keywords.' );
		abcc_assert_same( 'group:1', $resolved['token'] );
		abcc_assert_same( 'group', $resolved['type'] );
		abcc_assert_same( 7, $resolved['category'] );
		abcc_assert_same( 'how-to', $resolved['template'] );
		abcc_assert_equals( array( 'acids', 'bases' ), $resolved['keywords'] );
	}
);

abcc_test(
	'resolver: valid group token resolves to that group',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_keyword_groups' => array(
				array( 'name' => 'Cooking', 'keywords' => array( 'pasta' ), 'category' => 3, 'template' => 'listicle' ),
				array( 'name' => 'Chemistry', 'keywords' => array( 'acids' ), 'category' => 7, 'template' => 'how-to' ),
			),
		);

		$resolved = abcc_resolve_composer_source( 'group:0' );

		abcc_assert_same( 'group:0', $resolved['token'] );
		abcc_assert_same( 3, $resolved['category'] );
		abcc_assert_same( 'listicle', $resolved['template'] );
	}
);

abcc_test(
	'resolver: stale group token falls back gracefully',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'abcc_keyword_groups' => array(
				array( 'name' => 'Only', 'keywords' => array( 'x' ), 'category' => 0, 'template' => 'default' ),
			),
		);

		// Index 9 does not exist -> fall back to first group with keywords (index 0).
		$resolved = abcc_resolve_composer_source( 'group:9' );

		abcc_assert_same( 'group:0', $resolved['token'] );
	}
);

abcc_test(
	'resolver: valid topic token resolves to that topic',
	function () {
		$GLOBALS['abcc_test_options'] = array( 'abcc_keyword_groups' => array() );

		// Seed a topic post the bootstrap can read.
		$topic_id = abcc_test_make_topic( 'Weekly SEO roundup', 'Write a weekly SEO roundup.' );

		$resolved = abcc_resolve_composer_source( 'topic:' . $topic_id );

		abcc_assert_false( is_wp_error( $resolved ), 'Topic source should resolve.' );
		abcc_assert_same( 'topic:' . $topic_id, $resolved['token'] );
		abcc_assert_same( 'topic', $resolved['type'] );
		abcc_assert_same( $topic_id, $resolved['topic_id'] );
		abcc_assert_same( 'Write a weekly SEO roundup.', $resolved['prompt'] );
		abcc_assert_equals( array( 'Weekly SEO roundup' ), $resolved['keywords'] );
	}
);

abcc_test(
	'resolver: no groups and no topics returns WP_Error',
	function () {
		$GLOBALS['abcc_test_options'] = array( 'abcc_keyword_groups' => array() );

		$resolved = abcc_resolve_composer_source( '' );

		abcc_assert_true( is_wp_error( $resolved ), 'Resolver must error when there is nothing to generate from.' );
		abcc_assert_same( 'abcc_no_source', $resolved->get_error_code() );
	}
);

abcc_test(
	'composer overrides never mutate global prompt_select or post status',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'            => 'gpt-4.1-mini-2025-04-14',
			'abcc_default_post_status' => 'draft',
			'abcc_keyword_groups'      => array(
				array( 'name' => 'G', 'keywords' => array( 'x' ), 'category' => 0, 'template' => 'default' ),
			),
		);

		// Simulate the override-application step the handler performs.
		$payload = abcc_apply_composer_overrides(
			abcc_build_generation_payload(
				array( 'keywords' => array( 'x' ), 'source' => 'manual' )
			),
			array( 'model' => 'claude-opus-4-8', 'post_status' => 'publish' )
		);

		// Payload reflects the overrides...
		abcc_assert_same( 'claude-opus-4-8', $payload['model'] );
		abcc_assert_same( 'publish', $payload['post_status'] );
		// ...but globals are untouched.
		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', abcc_get_setting( 'prompt_select' ) );
		abcc_assert_same( 'draft', abcc_get_setting( 'abcc_default_post_status' ) );
	}
);
