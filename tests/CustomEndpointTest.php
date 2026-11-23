<?php
/**
 * Custom OpenAI-compatible endpoint (v4.6 §4): URL policy, keyless local
 * endpoints, no redirects, who may change it.
 */

/**
 * Store a custom endpoint directly, bypassing the save policy.
 *
 * @param array $overrides Setting overrides.
 * @return void
 */
function abcc_test_set_custom_endpoint( $overrides = array() ) {
	abcc_update_setting(
		'abcc_custom_endpoint',
		array_merge(
			array(
				'base_url'       => 'https://api.example.com/v1',
				'model'          => 'llama3.1',
				'context_window' => 32768,
				'max_output'     => 2048,
				'allow_local'    => false,
			),
			$overrides
		)
	);
	abcc_flush_provider_registry_cache();
}

abcc_test(
	'URL policy: https for public hosts, local hosts only behind the switch, no userinfo, no query',
	function () {
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'https://api.example.com/v1', false ) );
		abcc_assert_same( 'abcc_endpoint_insecure', abcc_validate_custom_endpoint( 'http://api.example.com/v1', false )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_insecure', abcc_validate_custom_endpoint( 'http://api.example.com/v1', true )->get_error_code(), 'The local switch does not excuse plain http to a public host.' );

		foreach ( array( 'http://localhost:11434/v1', 'http://127.0.0.1:11434/v1', 'http://10.0.0.5/v1', 'http://192.168.1.20:8080/v1', 'http://169.254.169.254/latest', 'http://[::1]:11434/v1', 'http://ollama:11434/v1', 'https://models.internal/v1', 'https://localhost/v1' ) as $local ) {
			abcc_assert_same( 'abcc_endpoint_local', abcc_validate_custom_endpoint( $local, false )->get_error_code(), $local . ' must need the local switch.' );
			abcc_assert_true( true === abcc_validate_custom_endpoint( $local, true ), $local . ' is allowed with the local switch.' );
		}

		abcc_assert_same( 'abcc_endpoint_userinfo', abcc_validate_custom_endpoint( 'https://user:pass@api.example.com/v1', false )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_userinfo', abcc_validate_custom_endpoint( 'https://token@api.example.com/v1', true )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_query', abcc_validate_custom_endpoint( 'https://api.example.com/v1?key=abc', false )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_scheme', abcc_validate_custom_endpoint( 'ftp://api.example.com/v1', false )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_invalid', abcc_validate_custom_endpoint( 'not a url', false )->get_error_code() );
		abcc_assert_same( 'abcc_endpoint_invalid', abcc_validate_custom_endpoint( '', true )->get_error_code() );
	}
);

abcc_test(
	'a public-looking name that resolves to a private address is refused at save time',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function ( $ips, $host ) {
				return 'sneaky.example.com' === $host ? array( '10.1.2.3' ) : array( '93.184.216.34' );
			},
			10,
			2
		);

		abcc_assert_same( 'abcc_endpoint_local', abcc_validate_custom_endpoint( 'https://sneaky.example.com/v1', false, true )->get_error_code() );
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'https://honest.example.com/v1', false, true ) );

		$saved = abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://sneaky.example.com/v1',
				'model'    => 'llama3.1',
			)
		);
		abcc_assert_same( 'abcc_endpoint_local', $saved->get_error_code() );
		abcc_assert_same( '', abcc_get_custom_endpoint_settings()['base_url'], 'A refused URL is not stored.' );
	}
);

abcc_test(
	'a public endpoint sends the key as a bearer token, follows no redirect, and asks core to re-check the host',
	function () {
		abcc_test_set_custom_endpoint();
		abcc_update_setting( 'custom_api_key', 'custom-secret-key-123' );
		abcc_flush_provider_registry_cache();

		abcc_assert_true( abcc_is_valid_text_model( 'custom::llama3.1' ) );
		abcc_assert_same( 'bearer', abcc_get_provider( 'custom' )['auth'] );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Hello.' ) );
		$result = abcc_call_provider_api(
			'custom',
			'custom::llama3.1',
			'Prompt.',
			array(
				'api_key'    => 'custom-secret-key-123',
				'max_tokens' => 9000,
			)
		);

		$request = $GLOBALS['abcc_http_last_request'];
		$body    = json_decode( $request['args']['body'], true );

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'https://api.example.com/v1/chat/completions', $request['url'] );
		abcc_assert_same( 'Bearer custom-secret-key-123', $request['args']['headers']['Authorization'] );
		abcc_assert_same( 0, $request['args']['redirection'], 'A redirect could carry the key to another host.' );
		abcc_assert_true( $request['args']['reject_unsafe_urls'] );
		abcc_assert_same( 'llama3.1', $body['model'] );
		abcc_assert_same( 2048, $body['max_tokens'], 'The declared max output caps the request.' );
		abcc_assert_same( 32768, abcc_get_model_context_window( 'llama3.1', 'custom' ) );

		// A redirect answer is an error, never a second request.
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 302 ),
			'body'     => '',
		);
		$redirected                   = abcc_call_provider_api( 'custom', 'custom::llama3.1', 'Prompt.', array( 'api_key' => 'custom-secret-key-123' ) );
		abcc_assert_true( is_wp_error( $redirected['error'] ) );
	}
);

abcc_test(
	'a public endpoint without a key is not ready; keyless works only for a local endpoint with the switch on',
	function () {
		abcc_test_set_custom_endpoint();
		abcc_assert_same( 'bearer', abcc_get_provider( 'custom' )['auth'] );
		abcc_assert_false( abcc_provider_is_ready( 'custom' ), 'A public host always needs its key.' );
		abcc_assert_false( isset( abcc_get_available_text_model_options()['custom'] ) );

		// Local address without the switch: no endpoint at all.
		abcc_test_set_custom_endpoint( array( 'base_url' => 'http://localhost:11434/v1' ) );
		abcc_assert_same( '', abcc_get_provider( 'custom' )['endpoint'] );
		abcc_assert_false( abcc_provider_supports_text_generation( 'custom' ) );
		abcc_assert_false( abcc_provider_is_ready( 'custom' ) );
		$refused = abcc_call_provider_api( 'custom', 'custom::llama3.1', 'Prompt.', array() );
		abcc_assert_true( is_wp_error( $refused['error'] ) );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'Nothing is sent to a local address the admin did not allow.' );

		// With the switch: keyless, no Authorization header, plain http allowed.
		abcc_test_set_custom_endpoint(
			array(
				'base_url'    => 'http://localhost:11434/v1',
				'allow_local' => true,
			)
		);
		$config = abcc_get_provider( 'custom' );
		abcc_assert_same( 'none', $config['auth'] );
		abcc_assert_false( $config['reject_unsafe_urls'], 'Core would block the local address the admin allowed.' );
		abcc_assert_true( abcc_provider_is_ready( 'custom' ) );
		abcc_assert_true( abcc_model_is_ready( 'custom::llama3.1' ) );
		abcc_assert_true( isset( abcc_get_available_text_model_options()['custom']['options']['llama3.1'] ) );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Local hello.' ) );
		$result  = abcc_call_provider_api( 'custom', 'custom::llama3.1', 'Prompt.', array( 'api_key' => '' ) );
		$request = $GLOBALS['abcc_http_last_request'];

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'http://localhost:11434/v1/chat/completions', $request['url'] );
		abcc_assert_false( isset( $request['args']['headers']['Authorization'] ) );
		abcc_assert_same( 0, $request['args']['redirection'] );
	}
);

abcc_test(
	'the worker runs a keyless local endpoint instead of failing with "no API key"',
	function () {
		abcc_test_set_custom_endpoint(
			array(
				'base_url'    => 'http://127.0.0.1:11434/v1',
				'allow_local' => true,
			)
		);
		abcc_update_setting( 'openai_generate_seo', false );
		abcc_update_setting( 'openai_generate_images', false );

		$job_id = abcc_queue_generation_job(
			abcc_build_generation_payload(
				array(
					'keywords' => array( 'widgets' ),
					'model'    => 'custom::llama3.1',
				)
			)
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Widget Basics' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "<p>One.</p>\n<p>Two.</p>" ) );
		abcc_process_generation_job( $job_id );

		abcc_assert_same( ABCC_Job::STATUS_SUCCESS, get_post_meta( $job_id, '_abcc_job_status', true ), (string) get_post_meta( $job_id, '_abcc_job_error', true ) );

		// A public endpoint with no key still fails cleanly.
		abcc_test_set_custom_endpoint();
		$job_id = abcc_queue_generation_job(
			abcc_build_generation_payload(
				array(
					'keywords' => array( 'widgets' ),
					'model'    => 'custom::llama3.1',
				)
			)
		);
		abcc_process_generation_job( $job_id );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
	}
);

abcc_test(
	'saving: policy applies to what was typed, an empty URL clears the endpoint, and the model is required',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () {
				return array( '93.184.216.34' );
			}
		);

		abcc_assert_same(
			'abcc_endpoint_userinfo',
			abcc_save_custom_endpoint(
				array(
					'base_url' => 'https://admin:hunter2@api.example.com/v1',
					'model'    => 'm',
				)
			)->get_error_code()
		);
		abcc_assert_same( 'abcc_endpoint_model', abcc_save_custom_endpoint( array( 'base_url' => 'https://api.example.com/v1' ) )->get_error_code() );
		abcc_assert_same(
			'abcc_endpoint_model',
			abcc_save_custom_endpoint(
				array(
					'base_url' => 'https://api.example.com/v1',
					'model'    => 'bad::id',
				)
			)->get_error_code()
		);

		$saved = abcc_save_custom_endpoint(
			array(
				'base_url'       => 'https://api.example.com/v1/',
				'model'          => 'deepseek-chat',
				'context_window' => '64000',
				'max_output'     => '8000',
			)
		);
		abcc_assert_true( true === $saved );

		$settings = abcc_get_custom_endpoint_settings();
		abcc_assert_same( 'https://api.example.com/v1', $settings['base_url'] );
		abcc_assert_same( 'deepseek-chat', $settings['model'] );
		abcc_assert_same( 64000, $settings['context_window'] );
		abcc_assert_false( $settings['allow_local'] );
		abcc_assert_same( 'https://api.example.com/v1/chat/completions', abcc_get_provider( 'custom' )['endpoint'] );

		// A base URL that already names the route is not doubled.
		abcc_assert_same( 'https://x.example.com/v1/chat/completions', abcc_get_custom_endpoint_chat_url( 'https://x.example.com/v1/chat/completions' ) );

		abcc_assert_true( true === abcc_save_custom_endpoint( array( 'base_url' => '' ) ) );
		abcc_assert_same( '', abcc_get_custom_endpoint_settings()['base_url'] );
		abcc_assert_false( abcc_provider_supports_text_generation( 'custom' ) );
	}
);

abcc_test(
	'only an admin may change the endpoint, and on multisite only a network admin',
	function () {
		$input = array(
			'base_url'    => 'http://localhost:11434/v1',
			'model'       => 'llama3.1',
			'allow_local' => '1',
		);

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		abcc_assert_same( 'abcc_endpoint_forbidden', abcc_save_custom_endpoint( $input )->get_error_code() );

		// Multisite: a site admin is not enough.
		$GLOBALS['abcc_test_multisite']         = true;
		$GLOBALS['abcc_test_current_user_caps'] = array(
			'manage_options'         => true,
			'manage_network_options' => false,
		);
		abcc_assert_false( abcc_current_user_can_manage_custom_endpoint() );
		abcc_assert_same( 'abcc_endpoint_forbidden', abcc_save_custom_endpoint( $input )->get_error_code() );
		abcc_assert_same( '', abcc_get_custom_endpoint_settings()['base_url'] );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_network_options' => true );
		abcc_assert_true( true === abcc_save_custom_endpoint( $input ) );
		abcc_assert_true( abcc_get_custom_endpoint_settings()['allow_local'] );
	}
);

abcc_test(
	'the custom key is never exported or logged, and autosave refuses the key and the endpoint',
	function () {
		abcc_test_set_custom_endpoint();
		abcc_update_setting( 'custom_api_key', 'custom-secret-key-123' );
		abcc_flush_provider_registry_cache();

		$exported = abcc_get_exportable_settings();
		abcc_assert_false( array_key_exists( 'custom_api_key', $exported ) );
		abcc_assert_false( false !== strpos( wp_json_encode( $exported ), 'custom-secret-key-123' ) );
		abcc_assert_false( false !== strpos( abcc_redact_secrets( 'endpoint said: bad key custom-secret-key-123' ), 'custom-secret-key-123' ) );

		foreach ( array( 'custom_api_key', 'abcc_custom_endpoint' ) as $key ) {
			$_POST = array(
				'nonce' => 'x',
				'key'   => $key,
				'value' => 'http://localhost/v1',
			);
			abcc_handle_autosave_setting();
			abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'], $key . ' must not be auto-saved.' );
		}
		$_POST = array();
	}
);

abcc_test(
	'connection test reports the stage that failed and accepts a keyless local endpoint',
	function () {
		abcc_test_set_custom_endpoint(
			array(
				'base_url'    => 'http://localhost:11434/v1',
				'allow_local' => true,
			)
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( 'ok' ) );
		$ok = abcc_test_provider_connection( 'custom', '' );
		abcc_assert_true( $ok['success'] );
		abcc_assert_true( $ok['probe']['reachable'] && $ok['probe']['authenticated'] && $ok['probe']['generates'] );

		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'Connection refused' );
		$down                         = abcc_test_provider_connection( 'custom', '' );
		abcc_assert_false( $down['success'] );
		abcc_assert_false( $down['probe']['reachable'] );

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 404 ),
			'body'     => '{"error":{"message":"model \'llama3.1\' not found"}}',
		);
		$no_model                     = abcc_test_provider_connection( 'custom', '' );
		abcc_assert_false( $no_model['success'] );
		abcc_assert_true( $no_model['probe']['reachable'] && $no_model['probe']['authenticated'] );
		abcc_assert_false( $no_model['probe']['generates'] );
		abcc_assert_true( false !== strpos( $no_model['error'], 'did not answer' ) );

		// The Validate button works without a key for a keyless endpoint.
		abcc_test_queue_http_response( abcc_test_fake_generation( 'ok' ) );
		$_POST = array(
			'nonce'    => 'x',
			'provider' => 'custom',
		);
		abcc_handle_validate_api_key();
		$_POST = array();
		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
	}
);

abcc_test(
	'a private-network host name saved with the local switch stays usable, and is_local never comes from the request',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function ( $ips, $host ) {
				return 'ollama.corp.example.com' === $host ? array( '10.20.30.40' ) : array( '93.184.216.34' );
			},
			10,
			2
		);

		$saved = abcc_save_custom_endpoint(
			array(
				'base_url'    => 'http://ollama.corp.example.com:11434/v1',
				'model'       => 'llama3.1',
				'allow_local' => '1',
			)
		);
		abcc_assert_true( true === $saved );
		abcc_assert_true( abcc_get_custom_endpoint_settings()['is_local'] );

		$config = abcc_get_provider( 'custom' );
		abcc_assert_same( 'http://ollama.corp.example.com:11434/v1/chat/completions', $config['endpoint'], 'The registry revalidates the private DNS answer.' );
		abcc_assert_false( $config['reject_unsafe_urls'] );
		abcc_assert_same( 'none', $config['auth'] );

		// A public host cannot be passed off as local to skip https or the key.
		$forged = abcc_save_custom_endpoint(
			array(
				'base_url'    => 'http://public.example.com/v1',
				'model'       => 'llama3.1',
				'allow_local' => '1',
				'is_local'    => '1',
			)
		);
		abcc_assert_same( 'abcc_endpoint_insecure', $forged->get_error_code() );

		$public = abcc_save_custom_endpoint(
			array(
				'base_url'    => 'https://public.example.com/v1',
				'model'       => 'llama3.1',
				'allow_local' => '1',
				'is_local'    => '1',
			)
		);
		abcc_assert_true( true === $public );
		abcc_assert_false( abcc_get_custom_endpoint_settings()['is_local'] );
		abcc_assert_same( 'bearer', abcc_get_provider( 'custom' )['auth'], 'A public host never runs keyless, whatever the switch says.' );
		abcc_assert_true( abcc_get_provider( 'custom' )['reject_unsafe_urls'] );

		// The one-model endpoint takes no extra model IDs.
		abcc_update_setting( 'custom_api_key', 'k-12345678' );
		abcc_flush_provider_registry_cache();
		abcc_assert_same( 'abcc_custom_model_provider', abcc_add_custom_model( 'custom', 'another-model' )->get_error_code() );
	}
);

abcc_test(
	'a saved key does not follow the endpoint to a different host',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () {
				return array( '93.184.216.34' );
			}
		);

		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.first.example/v1',
				'model'    => 'model-a',
			)
		);
		abcc_update_setting( 'custom_api_key', 'key-for-first-host' );

		// Same host, different path or model: the key stays.
		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.first.example/openai/v1',
				'model'    => 'model-b',
			)
		);
		abcc_assert_same( 'key-for-first-host', get_option( 'custom_api_key' ) );

		// A refused URL changes nothing.
		abcc_save_custom_endpoint(
			array(
				'base_url' => 'http://api.second.example/v1',
				'model'    => 'model-b',
			)
		);
		abcc_assert_same( 'key-for-first-host', get_option( 'custom_api_key' ) );
		abcc_assert_same( 'https://api.first.example/openai/v1', abcc_get_custom_endpoint_settings()['base_url'] );

		// New host: the old key is dropped.
		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.second.example/v1',
				'model'    => 'model-b',
			)
		);
		abcc_assert_same( '', (string) get_option( 'custom_api_key' ) );
		abcc_assert_false( abcc_provider_is_ready( 'custom' ) );

		// Turning the endpoint off drops the key too, so it cannot attach to whatever is entered next.
		abcc_update_setting( 'custom_api_key', 'key-for-second-host' );
		abcc_save_custom_endpoint( array( 'base_url' => '' ) );
		abcc_assert_same( '', (string) get_option( 'custom_api_key' ) );
	}
);


abcc_test(
	'mixed public and private DNS answers never qualify an endpoint as local',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () {
				return array( '10.20.30.40', '93.184.216.34' );
			}
		);

		abcc_assert_false( abcc_is_local_endpoint_host( 'mixed.example.com', true ) );
		abcc_assert_same( 'abcc_endpoint_insecure', abcc_validate_custom_endpoint( 'http://mixed.example.com/v1', true, true )->get_error_code() );
		abcc_assert_same(
			'abcc_endpoint_insecure',
			abcc_save_custom_endpoint(
				array(
					'base_url'    => 'http://mixed.example.com/v1',
					'model'       => 'llama3.1',
					'allow_local' => true,
				)
			)->get_error_code()
		);
		abcc_assert_same( '', abcc_get_custom_endpoint_settings()['base_url'] );
	}
);

abcc_test(
	'a hostname saved as local is disabled when DNS later returns a public address',
	function () {
		$ips = array( '10.20.30.40' );
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () use ( &$ips ) {
				return $ips;
			}
		);

		abcc_assert_true(
			true === abcc_save_custom_endpoint(
				array(
					'base_url'    => 'https://ollama.corp.example.com:11434/v1',
					'model'       => 'llama3.1',
					'allow_local' => true,
				)
			)
		);
		abcc_update_setting( 'custom_api_key', 'local-rebind-secret' );
		abcc_flush_provider_registry_cache();
		abcc_assert_same( 'https://ollama.corp.example.com:11434/v1/chat/completions', abcc_get_provider( 'custom' )['endpoint'] );

		$ips = array( '93.184.216.34' );
		abcc_flush_provider_registry_cache();
		abcc_assert_same( '', abcc_get_provider( 'custom' )['endpoint'] );
		abcc_assert_false( abcc_provider_supports_text_generation( 'custom' ) );

		$result = abcc_call_provider_api( 'custom', 'custom::llama3.1', 'Prompt.', array( 'api_key' => 'local-rebind-secret' ) );
		abcc_assert_true( is_wp_error( $result['error'] ) );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'A rebound public address must not receive the local endpoint key.' );
	}
);

abcc_test(
	'a saved custom key is bound to the normalized scheme, host and port',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function ( $ips, $host ) {
				return 'localhost' === $host ? array( '127.0.0.1', '::1' ) : array( '93.184.216.34' );
			},
			10,
			2
		);

		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.origin.example:443/v1',
				'model'    => 'model-a',
			)
		);
		abcc_update_setting( 'custom_api_key', 'origin-secret' );

		// An explicit default port and a path change are still the same origin.
		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.origin.example/openai/v1',
				'model'    => 'model-a',
			)
		);
		abcc_assert_same( 'origin-secret', get_option( 'custom_api_key' ) );

		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.origin.example:444/v1',
				'model'    => 'model-a',
			)
		);
		abcc_assert_same( '', (string) get_option( 'custom_api_key' ), 'A different service on the same host must not inherit the key.' );

		abcc_save_custom_endpoint(
			array(
				'base_url'    => 'https://localhost:11434/v1',
				'model'       => 'model-a',
				'allow_local' => true,
			)
		);
		abcc_update_setting( 'custom_api_key', 'local-origin-secret' );
		abcc_save_custom_endpoint(
			array(
				'base_url'    => 'http://localhost:11434/v1',
				'model'       => 'model-a',
				'allow_local' => true,
			)
		);
		abcc_assert_same( '', (string) get_option( 'custom_api_key' ), 'A scheme change must not inherit the key.' );
	}
);


abcc_test(
	'a wp-config custom key blocks an endpoint origin change',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () {
				return array( '93.184.216.34' );
			}
		);
		add_filter(
			'abcc_custom_endpoint_has_constant_key',
			function () {
				return true;
			}
		);

		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.constant.example/v1',
				'model'    => 'model-a',
			)
		);
		$result = abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.other-example.com/v1',
				'model'    => 'model-a',
			)
		);

		abcc_assert_same( 'abcc_endpoint_constant_origin', $result->get_error_code() );
		abcc_assert_same( 'https://api.constant.example/v1', abcc_get_custom_endpoint_settings()['base_url'] );
		abcc_assert_true( true === abcc_save_custom_endpoint( array( 'base_url' => '' ) ) );

		$result = abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.other-example.com/v1',
				'model'    => 'model-a',
			)
		);
		abcc_assert_same( 'abcc_endpoint_constant_origin', $result->get_error_code(), 'Clearing the form must not rebind the wp-config key.' );

		remove_all_filters( 'abcc_custom_endpoint_has_constant_key' );
		abcc_assert_true(
			true === abcc_save_custom_endpoint(
				array(
					'base_url' => 'https://api.other-example.com/v1',
					'model'    => 'model-a',
				)
			)
		);
		abcc_assert_same( 'https://api.other-example.com:443', abcc_get_custom_endpoint_settings()['constant_key_origin'] );
	}
);


abcc_test(
	'multisite site admins cannot probe the network custom endpoint through key validation',
	function () {
		abcc_test_set_custom_endpoint(
			array(
				'base_url'    => 'http://localhost:11434/v1',
				'allow_local' => true,
			)
		);
		$GLOBALS['abcc_test_multisite']         = true;
		$GLOBALS['abcc_test_current_user_caps'] = array(
			'manage_options'         => true,
			'manage_network_options' => false,
		);
		$_POST                                  = array(
			'nonce'    => 'x',
			'provider' => 'custom',
			'api_key'  => 'site-admin-candidate-key',
		);

		abcc_handle_validate_api_key();

		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'A site admin must not trigger requests to the network local endpoint.' );
		$_POST = array();
	}
);

abcc_test(
	'local-looking hostnames are DNS-revalidated at save and whenever the registry is rebuilt',
	function () {
		$answers = array( '10.20.30.40' );
		$lookups = array();
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function ( $ips, $host ) use ( &$answers, &$lookups ) {
				$lookups[] = $host;
				return $answers;
			},
			10,
			2
		);

		foreach ( array( 'localhost', 'ollama', 'ollama.local', 'ollama.internal', 'ollama.lan', 'ollama.home', 'ollama.home.arpa', 'ollama.test' ) as $host ) {
			$saved = abcc_save_custom_endpoint(
				array(
					'base_url'    => 'http://' . $host . ':11434/v1',
					'model'       => 'llama3.1',
					'allow_local' => true,
				)
			);
			abcc_assert_true( true === $saved, $host . ' should accept a private DNS answer.' );
			abcc_assert_true( in_array( $host, $lookups, true ), $host . ' must be resolved at save time.' );
			abcc_assert_same( 'http://' . $host . ':11434/v1/chat/completions', abcc_get_provider( 'custom' )['endpoint'] );
		}

		foreach ( array( array( '93.184.216.34' ), array( '10.20.30.40', '93.184.216.34' ), array( 'not-an-ip' ), array(), false ) as $unsafe_answers ) {
			$answers = $unsafe_answers;
			abcc_flush_provider_registry_cache();
			abcc_assert_same( '', abcc_get_provider( 'custom' )['endpoint'], 'Unsafe or unavailable DNS must disable a local-looking hostname.' );
			abcc_assert_false( abcc_provider_supports_text_generation( 'custom' ) );
		}

		$answers = array( '10.20.30.40', 'fd00::40' );
		abcc_flush_provider_registry_cache();
		abcc_assert_same( 'http://ollama.test:11434/v1/chat/completions', abcc_get_provider( 'custom' )['endpoint'], 'Normal private IPv4 and IPv6 DNS answers keep working.' );
	}
);

abcc_test(
	'local-looking hostnames fail closed at save on public, mixed, invalid or failed DNS',
	function () {
		$answers = array();
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () use ( &$answers ) {
				return $answers;
			}
		);

		foreach ( array( array( '93.184.216.34' ), array( '10.20.30.40', '93.184.216.34' ), array( 'not-an-ip' ), array(), false ) as $unsafe_answers ) {
			$answers = $unsafe_answers;
			$result  = abcc_save_custom_endpoint(
				array(
					'base_url'    => 'http://ollama.internal:11434/v1',
					'model'       => 'llama3.1',
					'allow_local' => true,
				)
			);
			abcc_assert_true( is_wp_error( $result ), 'A local-looking name must not be saved without an entirely private, valid DNS answer.' );
			abcc_assert_same( '', abcc_get_custom_endpoint_settings()['base_url'], 'A rejected local-looking name leaves the previous setting untouched.' );
		}
	}
);

abcc_test(
	'non-canonical numeric hosts are rejected while canonical IPv4 and IPv6 literals keep working',
	function () {
		foreach ( array( '134744072', '0x08080808', '8.8.8', '010.010.010.010', '0x7f.0.0.1' ) as $host ) {
			$result = abcc_validate_custom_endpoint( 'http://' . $host . '/v1', true, true );
			abcc_assert_true( is_wp_error( $result ), $host . ' is a non-canonical numeric host, not a hostname.' );
			abcc_assert_same( 'abcc_endpoint_invalid', $result->get_error_code() );

			abcc_test_set_custom_endpoint(
				array(
					'base_url'    => 'http://' . $host . '/v1',
					'allow_local' => true,
					'is_local'    => true,
				)
			);
			abcc_assert_same( '', abcc_get_provider( 'custom' )['endpoint'], $host . ' must not enter the runtime registry.' );
		}

		abcc_assert_true( true === abcc_validate_custom_endpoint( 'https://8.8.8.8/v1', false, true ) );
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'http://127.0.0.1/v1', true, true ) );
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'https://[2001:4860:4860::8888]/v1', false, true ) );
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'http://[::1]/v1', true, true ) );
		abcc_assert_true( true === abcc_validate_custom_endpoint( 'http://[fd00::1]/v1', true, true ) );
	}
);

abcc_test(
	'saving a local hostname stores the same DNS classification validation approved',
	function () {
		foreach ( array( array( '93.184.216.34' ), false ) as $later_answer ) {
			$lookups = 0;
			add_filter(
				'abcc_custom_endpoint_resolved_ips',
				function () use ( &$lookups, $later_answer ) {
					++$lookups;
					return 1 === $lookups ? array( '10.20.30.40' ) : $later_answer;
				}
			);

			abcc_assert_true(
				true === abcc_save_custom_endpoint(
					array(
						'base_url'    => 'https://ollama.corp.example.com:11434/v1',
						'model'       => 'llama3.1',
						'allow_local' => true,
					)
				)
			);
			abcc_assert_same( 1, $lookups, 'Saving must not independently resolve the host a second time.' );
			abcc_assert_true( abcc_get_custom_endpoint_settings()['is_local'] );
			abcc_assert_same( '', abcc_get_provider( 'custom' )['endpoint'], 'A later public or failed answer disables the endpoint at runtime.' );
			remove_all_filters( 'abcc_custom_endpoint_resolved_ips' );
		}
	}
);

abcc_test(
	'a new endpoint address does not inherit the previous address\'s "verified" state',
	function () {
		add_filter(
			'abcc_custom_endpoint_resolved_ips',
			function () {
				return array( '93.184.216.34' );
			}
		);

		abcc_assert_true(
			true === abcc_save_custom_endpoint(
				array(
					'base_url' => 'https://first.example.com/v1',
					'model'    => 'm',
				)
			)
		);
		abcc_set_provider_saved_api_key( 'custom', 'sk-first' );
		abcc_flush_provider_registry_cache();
		abcc_record_provider_validation( 'custom', true );
		abcc_assert_same( 'connected', abcc_get_provider_health_snapshot( 'custom' )['health'] );

		// Turned off: both health stores forget the old address.
		abcc_assert_true( true === abcc_save_custom_endpoint( array( 'base_url' => '' ) ) );
		abcc_assert_false( get_transient( 'abcc_last_validation_custom' ) );
		abcc_assert_false( isset( get_option( 'abcc_provider_health', array() )['custom'] ) );

		// A health row left by an earlier version must not vouch for the next address.
		$rows           = (array) get_option( 'abcc_provider_health', array() );
		$rows['custom'] = array(
			'status'    => 'connected',
			'timestamp' => time(),
		);
		update_option( 'abcc_provider_health', $rows );

		abcc_assert_true(
			true === abcc_save_custom_endpoint(
				array(
					'base_url' => 'https://second.example.com/v1',
					'model'    => 'm',
				)
			)
		);
		abcc_flush_provider_registry_cache();
		abcc_assert_false( isset( get_option( 'abcc_provider_health', array() )['custom'] ) );
		abcc_assert_true( empty( abcc_get_provider_health_snapshot( 'custom' )['last_check'] ) );
	}
);
