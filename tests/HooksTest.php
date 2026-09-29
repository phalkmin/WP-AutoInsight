<?php
/**
 * Regression tests for the v4.5 extension hooks (Unit A).
 *
 * Hooks are public API from v4.6 on, so these pin the names, argument
 * order, and the guarantee that no hook ever receives an API key.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Return the arguments of the N-th firing of an action (0-based).
 *
 * @param string $hook  Hook name.
 * @param int    $index Which firing.
 * @return array|null
 */
function abcc_test_fired_action_args( $hook, $index = 0 ) {
	$seen = 0;
	foreach ( $GLOBALS['abcc_test_fired_actions'] as $fired ) {
		if ( $fired['hook'] !== $hook ) {
			continue;
		}
		if ( $seen === $index ) {
			return $fired['args'];
		}
		++$seen;
	}
	return null;
}

abcc_test(
	'abcc_hook_safe_context strips api_key and fills defaults',
	function () {
		$safe = abcc_hook_safe_context(
			array(
				'api_key' => 'sk-secret',
				'model'   => 'gpt-5.4-mini',
				'extra'   => 'kept',
			)
		);

		abcc_assert_false( array_key_exists( 'api_key', $safe ), 'api_key must never reach a hook.' );
		abcc_assert_same( 'gpt-5.4-mini', $safe['model'] );
		abcc_assert_same( 'kept', $safe['extra'], 'Unknown keys pass through.' );
		abcc_assert_same( '', $safe['provider'] );
		abcc_assert_same( 0, $safe['char_limit'] );
		abcc_assert_same( 0, $safe['job_id'] );
		abcc_assert_same( 'manual', $safe['source'] );
		abcc_assert_same( 1, $safe['attempt'] );
	}
);

abcc_test(
	'abcc_hook_safe_context normalizes types',
	function () {
		$safe = abcc_hook_safe_context(
			array(
				'char_limit' => '800',
				'job_id'     => '42',
				'attempt'    => '2',
				'source'     => 'group:0',
			)
		);

		abcc_assert_same( 800, $safe['char_limit'] );
		abcc_assert_same( 42, $safe['job_id'] );
		abcc_assert_same( 2, $safe['attempt'] );
		abcc_assert_same( 'group:0', $safe['source'] );
	}
);

abcc_test(
	'generation fires before/after hooks once with a credential-free context',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );

		$result = abcc_generate_content_detailed(
			'sk-test',
			'Prompt.',
			'gpt-4.1-mini-2025-04-14',
			200,
			array(
				'job_id'  => 7,
				'source'  => 'group:1',
				'api_key' => 'sk-leak',
			)
		);

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 1, did_action( 'abcc_before_generate' ) );
		abcc_assert_same( 1, did_action( 'abcc_after_generate' ) );

		$before = abcc_test_fired_action_args( 'abcc_before_generate' );
		$after  = abcc_test_fired_action_args( 'abcc_after_generate' );

		abcc_assert_false( array_key_exists( 'api_key', $before[0] ), 'before_generate context leaked api_key.' );
		abcc_assert_false( array_key_exists( 'api_key', $after[0] ), 'after_generate context leaked api_key.' );
		abcc_assert_same( 'openai', $before[0]['provider'] );
		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', $before[0]['model'] );
		abcc_assert_same( 200, $before[0]['char_limit'] );
		abcc_assert_same( 7, $before[0]['job_id'] );
		abcc_assert_same( 'group:1', $before[0]['source'] );
		abcc_assert_same( '<p>Body.</p>', $after[1]['content'][0], 'after_generate receives the result as its second argument.' );
	}
);

abcc_test(
	'abcc_generation_prompt filter changes the prompt sent to the provider',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );

		add_filter(
			'abcc_generation_prompt',
			function ( $prompt, $context ) {
				abcc_assert_false( array_key_exists( 'api_key', $context ) );
				return $prompt . ' [X]';
			}
		);

		abcc_generate_content_detailed( 'sk-test', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		$body = (string) ( $GLOBALS['abcc_http_last_request']['args']['body'] ?? '' );
		abcc_assert_true( false !== strpos( $body, 'Prompt. [X]' ), 'Filtered prompt must be what goes over the wire.' );
	}
);

abcc_test(
	'abcc_generation_result filter replaces what _detailed() returns',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Original.</p>' ) );

		add_filter(
			'abcc_generation_result',
			function ( $result, $context ) {
				$result['content'] = array( 'filtered' );
				return $result;
			}
		);

		$result = abcc_generate_content_detailed( 'sk-test', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );

		abcc_assert_same( array( 'filtered' ), $result['content'] );

		$after = abcc_test_fired_action_args( 'abcc_after_generate' );
		abcc_assert_same( array( 'filtered' ), $after[1]['content'], 'after_generate must see the filtered result.' );
	}
);

abcc_test(
	'abcc_settings_schema filter makes an extension key readable and writable',
	function () {
		add_filter(
			'abcc_settings_schema',
			function ( $schema ) {
				$schema['settings']['abcc_test_ext_key'] = array( 'default' => 'ext-default' );
				return $schema;
			}
		);

		abcc_assert_same( 'ext-default', abcc_get_setting( 'abcc_test_ext_key', 'x' ), 'Filtered default must win over the fallback.' );
		abcc_assert_true( is_array( abcc_get_setting_definition( 'abcc_test_ext_key' ) ), 'Definition must be visible to the schema loops.' );

		abcc_update_setting( 'abcc_test_ext_key', 'saved' );
		abcc_assert_same( 'saved', abcc_get_setting( 'abcc_test_ext_key', 'x' ) );
	}
);

abcc_test(
	'abcc_post_inserted fires once per generated post with a credential-free context',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_generate_seo'    => false,
			'openai_generate_images' => false,
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( 'My Title' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "<h2>Intro</h2>\n<p>Body.</p>" ) );

		$post_id = abcc_openai_generate_post(
			'sk-test',
			array( 'alpha' ),
			'gpt-4.1-mini-2025-04-14',
			'default',
			false,
			200,
			'post',
			array(
				'source' => 'manual',
				'job_id' => 3,
			)
		);

		abcc_assert_true( is_int( $post_id ) && $post_id > 0, 'Generation must succeed in the harness.' );
		abcc_assert_same( 1, did_action( 'abcc_post_inserted' ) );

		$args = abcc_test_fired_action_args( 'abcc_post_inserted' );
		abcc_assert_same( $post_id, $args[0] );
		abcc_assert_same( $post_id, $args[1]['post_id'] );
		abcc_assert_same( 'manual', $args[1]['source'] );
		abcc_assert_same( 3, $args[1]['job_id'] );
		abcc_assert_same( 'openai', $args[1]['provider'] );
		abcc_assert_false( array_key_exists( 'api_key', $args[1] ), 'post_inserted context leaked api_key.' );
	}
);

abcc_test(
	'abcc_post_inserted fires for post-from-audio',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( "Audio Title\n<p>Intro.</p>" ) );

		$post_id = abcc_build_post_from_transcript( 'Spoken words here.', 'transcript_plus_intro', array() );

		abcc_assert_true( is_int( $post_id ) && $post_id > 0, 'Audio post must be created.' );
		abcc_assert_same( 1, did_action( 'abcc_post_inserted' ) );

		$args = abcc_test_fired_action_args( 'abcc_post_inserted' );
		abcc_assert_same( $post_id, $args[0] );
		abcc_assert_same( 'audio', $args[1]['source'] );
		abcc_assert_false( array_key_exists( 'api_key', $args[1] ) );
	}
);

abcc_test(
	'abcc_admin_tabs registry lists the six core tabs and accepts additions and removals',
	function () {
		$core = array_keys( abcc_get_admin_tabs() );
		abcc_assert_same( array( 'dashboard', 'content', 'topics', 'media', 'connections', 'settings' ), $core );

		foreach ( abcc_get_admin_tabs() as $slug => $tab ) {
			abcc_assert_true( isset( $tab['label'], $tab['file'] ), "Tab {$slug} must declare label and file." );
			abcc_assert_true(
				file_exists( dirname( __DIR__ ) . '/includes/admin/' . $tab['file'] ),
				"Core tab file {$tab['file']} must exist."
			);
		}

		add_filter(
			'abcc_admin_tabs',
			function ( $tabs ) {
				$tabs['ext'] = array(
					'label' => 'Ext',
					'file'  => '/tmp/x/tab-ext.php',
				);
				unset( $tabs['topics'] );
				return $tabs;
			}
		);

		$filtered = array_keys( abcc_get_admin_tabs() );
		abcc_assert_same( array( 'dashboard', 'content', 'media', 'connections', 'settings', 'ext' ), $filtered );
	}
);

abcc_test(
	'abcc_resolve_admin_tab_file confines relative entries to includes/admin and honors absolute paths',
	function () {
		$base = dirname( __DIR__ ) . '/includes/admin/';

		abcc_assert_same( $base . 'tab-content.php', abcc_resolve_admin_tab_file( array( 'file' => 'tab-content.php' ) ) );
		abcc_assert_same( $base . 'tab-content.php', abcc_resolve_admin_tab_file( array( 'file' => '../../tab-content.php' ) ), 'Traversal must be stripped.' );

		$abs = tempnam( sys_get_temp_dir(), 'abcc-tab' );
		abcc_assert_same( $abs, abcc_resolve_admin_tab_file( array( 'file' => $abs ) ) );
		unlink( $abs );

		abcc_assert_same( '', abcc_resolve_admin_tab_file( array( 'file' => '/definitely/missing/tab.php' ) ), 'Missing absolute file resolves to empty.' );
		abcc_assert_same( '', abcc_resolve_admin_tab_file( array() ) );
	}
);
