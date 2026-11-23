<?php
/**
 * Regressions found by the full 4.6 review (2026-11-11).
 */

abcc_test(
	'reset keeps the custom endpoint together with its key',
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
		abcc_update_setting( 'abcc_content_audience', 'someone' );

		abcc_reset_settings();

		abcc_assert_same( '', abcc_get_setting( 'abcc_content_audience' ), 'Ordinary settings are still reset.' );
		abcc_assert_same( 'https://api.first.example/v1', abcc_get_custom_endpoint_settings()['base_url'] );
		abcc_assert_same( 'key-for-first-host', get_option( 'custom_api_key' ) );

		// The key is still bound to its host: a new host drops it.
		abcc_save_custom_endpoint(
			array(
				'base_url' => 'https://api.second.example/v1',
				'model'    => 'model-a',
			)
		);
		abcc_assert_same( '', (string) get_option( 'custom_api_key' ) );
	}
);

abcc_test(
	'the onboarding key test cannot write the custom endpoint key for a site admin on multisite',
	function () {
		$GLOBALS['abcc_test_multisite']         = true;
		$GLOBALS['abcc_test_current_user_caps'] = array(
			'manage_options'         => true,
			'manage_network_options' => false,
		);
		abcc_update_setting( 'custom_api_key', 'network-admin-key' );

		$_POST = array(
			'nonce'    => 'x',
			'provider' => 'custom',
			'api_key'  => 'site-admin-key',
		);
		abcc_handle_onboarding_test_api();
		$_POST = array();

		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'network-admin-key', get_option( 'custom_api_key' ) );
		abcc_assert_true( empty( $GLOBALS['abcc_http_last_request'] ), 'Nothing is sent before the capability check.' );
	}
);

abcc_test(
	'a model ID newer than the registry gets the parameter rules of its generation',
	function () {
		$openai = abcc_get_model_flags( 'openai', 'gpt-7-nova' );
		abcc_assert_same( 'max_completion_tokens', $openai['token_param'] );
		abcc_assert_false( $openai['temperature'] );
		abcc_assert_same( 4000, $openai['min_output_tokens'] );

		// Older and listed IDs keep their own rules.
		abcc_assert_same( 'max_tokens', abcc_get_model_flags( 'openai', 'gpt-4.1-2025-04-14' )['token_param'] );
		abcc_assert_same( 'none', abcc_get_model_flags( 'openai', 'gpt-6-luna' )['reasoning_effort'] );
		abcc_assert_same( 1000, abcc_get_model_flags( 'openai', 'gpt-6-luna' )['min_output_tokens'] );

		$claude = abcc_get_model_flags( 'claude', 'claude-sonnet-6' );
		abcc_assert_false( $claude['temperature'] );
		abcc_assert_true( $claude['reasoning'] );
		abcc_assert_same( 4000, $claude['min_output_tokens'] );
		abcc_assert_same( 0, abcc_get_model_flags( 'claude', 'claude-opus-4-8' )['min_output_tokens'] );
		abcc_assert_same( 4000, abcc_get_model_flags( 'claude', 'claude-haiku-5-5' )['min_output_tokens'] );
		abcc_assert_same( 1024, abcc_get_model_flags( 'gemini', 'gemini-3.5-flash-lite' )['min_output_tokens'], 'The registry entry wins.' );

		$gemini = abcc_get_model_flags( 'gemini', 'gemini-4-flash' );
		abcc_assert_true( $gemini['reasoning'] );
		abcc_assert_same( 2048, $gemini['min_output_tokens'] );
		abcc_assert_same( 0, abcc_get_model_flags( 'gemini', 'gemini-2.5-flash-lite' )['min_output_tokens'] );

		// A gateway or custom endpoint may serve a model with the same name under other rules.
		abcc_assert_same( 'max_tokens', abcc_get_model_flags( 'openrouter', 'gpt-7-nova' )['token_param'] );
	}
);

abcc_test(
	'a typed OpenAI model ID is sent with max_completion_tokens and no temperature',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_assert_true( true === abcc_add_custom_model( 'openai', 'gpt-7-nova', array( 'context_window' => 200000 ) ) );

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>ok</p>' ) );
		$result = abcc_call_provider_api( 'openai', 'gpt-7-nova', 'p', array( 'api_key' => 'sk-test' ) );
		$body   = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );

		abcc_assert_true( null === $result['error'] );
		abcc_assert_true( isset( $body['max_completion_tokens'] ) );
		abcc_assert_false( isset( $body['max_tokens'] ) );
		abcc_assert_false( isset( $body['temperature'] ) );
	}
);

abcc_test(
	'an error carried in a 200 response is reported as the provider error it is',
	function () {
		abcc_update_setting( 'openrouter_api_key', 'sk-or-test' );

		// Top-level error with the upstream status.
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'error' => array(
						'code'    => 429,
						'message' => 'Rate limit exceeded upstream',
					),
				)
			)
		);
		$result = abcc_call_provider_api( 'openrouter', 'openai/gpt-6-luna', 'p', array( 'api_key' => 'sk-or-test' ) );
		abcc_assert_same( 'abcc_provider_rate_limited', $result['error']->get_error_code() );
		abcc_assert_same( 'Rate limit exceeded upstream', $result['error']->get_error_data()['detail'] );
		abcc_assert_true( abcc_is_retryable_generation_error( $result['error'] ) );

		// Per-choice error with no status: an upstream failure, retryable on a backup.
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => '' ),
							'finish_reason' => 'error',
							'error'         => array( 'message' => 'Provider returned error' ),
						),
					),
				)
			)
		);
		$result = abcc_call_provider_api( 'openrouter', 'openai/gpt-6-luna', 'p', array( 'api_key' => 'sk-or-test' ) );
		abcc_assert_same( 'abcc_provider_server_error', $result['error']->get_error_code() );

		// A normal answer that happens to mention an error object elsewhere is untouched.
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Fine.</p>' ) );
		$result = abcc_call_provider_api( 'openrouter', 'openai/gpt-6-luna', 'p', array( 'api_key' => 'sk-or-test' ) );
		abcc_assert_true( null === $result['error'] );
	}
);

abcc_test(
	'a request timeout from the provider is transient',
	function () {
		abcc_assert_same( 'abcc_provider_server_error', abcc_classify_provider_http_status( 408 ) );
		abcc_assert_same( 'abcc_provider_http_error', abcc_classify_provider_http_status( 404 ) );
	}
);

abcc_test(
	'live catalog values replace stale price and reasoning settings on OpenRouter models',
	function () {
		$base = array(
			'name'                     => 'Old name',
			'price_input'              => 1.0,
			'price_output'             => 2.0,
			'gateway_reasoning_effort' => 'none',
			'free'                     => true,
			'custom'                   => true,
		);
		$live = array(
			'name'              => 'New name',
			'context_window'    => 32000,
			'min_output_tokens' => 0,
		);

		$merged = abcc_openrouter_apply_live_definition( $base, $live );

		abcc_assert_same( 'New name', $merged['name'] );
		abcc_assert_false( isset( $merged['gateway_reasoning_effort'] ), 'An effort the catalog no longer offers must not be sent.' );
		abcc_assert_false( isset( $merged['price_input'] ), 'A model whose price now varies has no known price.' );
		abcc_assert_false( isset( $merged['free'] ) );
		abcc_assert_true( $merged['custom'] );

		// Through the registry: the shortlist's dated effort is dropped when the catalog stops offering it.
		set_transient(
			'abcc_openrouter_live',
			array(
				'fetched_at' => time(),
				'models'     => array(
					'openai/gpt-6-luna' => array(
						'name'              => 'OpenAI: GPT-6 Luna',
						'context_window'    => 1050000,
						'structured_output' => true,
					),
				),
			),
			DAY_IN_SECONDS
		);
		abcc_flush_provider_registry_cache();

		$body = abcc_openrouter_request_body( array( 'model' => 'openai/gpt-6-luna' ), 'openai/gpt-6-luna', array() );
		abcc_assert_false( isset( $body['reasoning'] ) );
	}
);

abcc_test(
	'prompt input is bounded for scripts str_word_count() cannot count',
	function () {
		$cyrillic = trim( str_repeat( 'слово ', 700 ) );
		$bounded  = abcc_sanitize_source_notes( $cyrillic );
		abcc_assert_true( abcc_count_words_unicode( $bounded ) <= 605, 'Cyrillic notes are cut to about 600 words.' );
		abcc_assert_true( false !== strpos( $bounded, 'truncated' ) );

		$cjk     = str_repeat( '字', 20000 );
		$bounded = abcc_sanitize_source_notes( $cjk );
		abcc_assert_true( mb_strlen( $bounded ) <= 4900, 'Text without spaces is cut by length.' );

		$short = 'Launch date: 12 March. Price: $49.';
		abcc_assert_same( $short, abcc_sanitize_source_notes( $short ) );
		abcc_assert_same( 'one two three', abcc_bound_prompt_input( 'one two three', 3 ) );
	}
);

abcc_test(
	'text survives when the reasoning-block pattern cannot run',
	function () {
		abcc_assert_same( 'Body.', abcc_strip_reasoning_blocks( '<think>plan</think>Body.' ) );
		abcc_assert_same( '', abcc_strip_reasoning_blocks( '<think>never closed' ) );

		$limit = ini_get( 'pcre.backtrack_limit' );
		$jit   = ini_get( 'pcre.jit' );
		ini_set( 'pcre.jit', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		ini_set( 'pcre.backtrack_limit', '10' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$text   = '<think>' . str_repeat( 'x', 5000 ) . ' and then the article body with no closing tag';
		$result = abcc_strip_reasoning_blocks( 'Intro. ' . $text );
		ini_set( 'pcre.backtrack_limit', (string) $limit ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		ini_set( 'pcre.jit', (string) $jit ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		abcc_assert_true( 0 === strpos( $result, 'Intro. ' ), 'A failed match must not empty the text.' );
	}
);

abcc_test(
	'a PHP error during a rewrite closes its log row and hides the internals',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Throwable Post',
				'post_content' => '<p>Original body text.</p>',
			)
		);

		add_filter(
			'abcc_generation_prompt',
			function () {
				throw new Error( 'internal detail at /var/www/secret.php' );
			}
		);

		// The handler writes the detail to the server log; keep it out of the test output.
		$previous_log = ini_set( 'error_log', '/dev/null' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		$_POST        = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();
		$_POST = array();
		ini_set( 'error_log', (string) $previous_log ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_false( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], 'secret.php' ) );

		$job_id = 0;
		foreach ( get_posts( array( 'post_type' => ABCC_Job::POST_TYPE ) ) as $job ) {
			if ( 'rewrite' === get_post_meta( $job->ID, '_abcc_job_source', true ) ) {
				$job_id = max( $job_id, (int) $job->ID );
			}
		}
		abcc_assert_true( $job_id > 0 );
		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_false( false !== strpos( (string) get_post_meta( $job_id, '_abcc_job_error', true ), 'secret.php' ) );
	}
);

abcc_test(
	'the SEO call asks for more tokens in languages that need them',
	function () {
		abcc_update_setting( 'gemini_api_key', 'g-test' );

		$GLOBALS['abcc_test_locale'] = 'en_US';
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => '{"title":"T","meta_description":"D","primary_keyword":"k","secondary_keywords":[],"social_excerpt":"S"}' ) ) ),
							'finishReason' => 'STOP',
						),
					),
				)
			)
		);
		abcc_generate_title_and_seo( 'g-test', array( 'widgets' ), 'gemini::gemini-2.5-flash-lite', array() );
		$english = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true )['generationConfig']['maxOutputTokens'];

		$GLOBALS['abcc_test_locale'] = 'ja';
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => '{"title":"T","meta_description":"D","primary_keyword":"k","secondary_keywords":[],"social_excerpt":"S"}' ) ) ),
							'finishReason' => 'STOP',
						),
					),
				)
			)
		);
		abcc_generate_title_and_seo( 'g-test', array( 'widgets' ), 'gemini::gemini-2.5-flash-lite', array() );
		$japanese = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true )['generationConfig']['maxOutputTokens'];

		abcc_assert_same( 300, $english );
		abcc_assert_true( $japanese > 500, 'Japanese needs well over 300 tokens for the same JSON: ' . $japanese );
	}
);

abcc_test(
	'a site with no image provider gets a reason that says so',
	function () {
		// The harness defines OPENAI_API; drop it so no image-capable provider has a key.
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['openai']['constant'] = '';
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();
		abcc_update_setting( 'openrouter_api_key', 'sk-or-test' );
		abcc_update_setting( 'openai_generate_images', true );

		abcc_assert_false( abcc_generate_featured_image( 'openrouter::openai/gpt-6-luna', array( 'widgets' ) ) );
		abcc_assert_true( false !== strpos( abcc_get_last_image_error(), 'No image provider' ) );
	}
);

abcc_test(
	'an upgrade from 4.5.3 that never saved a length stays at 200 and is told once',
	function () {
		$GLOBALS['abcc_test_options']['abcc_version']   = '4.5.3';
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk-existing';
		unset( $GLOBALS['abcc_test_options']['openai_char_limit'], $GLOBALS['abcc_test_options']['abcc_length_notice'] );

		abcc_assert_true( version_compare( ABCC_VERSION, '4.6.0', '>=' ), 'The length pin only runs once the plugin reports 4.6.0.' );

		abcc_run_settings_migrations();

		abcc_assert_same( 200, (int) get_option( 'openai_char_limit' ) );
		abcc_assert_same( 1, (int) get_option( 'abcc_length_notice' ) );
		abcc_assert_same( ABCC_VERSION, get_option( 'abcc_version' ) );
	}
);
