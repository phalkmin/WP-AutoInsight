<?php
/**
 * Model audit of 2026-11-05 (v4.6 §1): retired IDs map to successors, new
 * models carry the data the estimator needs, and the UI offers no dead IDs.
 */

abcc_test(
	'retired image models map to their successor once the date has passed',
	function () {
		$before = strtotime( '2026-11-05 UTC' );
		$after  = strtotime( '2026-12-02 UTC' );

		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'gpt-image-1', $before ) );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'dall-e-3', $before ) );
		abcc_assert_same( 'gpt-image-1-mini', abcc_resolve_image_model( 'openai', 'gpt-image-1-mini', $before ), 'Still served until December 1, 2026.' );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'gpt-image-1-mini', $after ) );
		abcc_assert_same( 'gpt-image-2.5-sunburst', abcc_resolve_image_model( 'openai', 'gpt-image-1.5', $after ) );
		abcc_assert_same( 'gpt-image-2.5-sunburst', abcc_resolve_image_model( 'openai', 'gpt-image-2.5-sunburst', $before ) );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', '', $before ), 'Blank falls back to the first option.' );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'made-up', $before ) );

		abcc_assert_same( 'gemini-3-pro-image', abcc_resolve_image_model( 'gemini', 'gemini-3-pro-image-preview', $before ) );
		abcc_assert_same( 'gemini-2.5-flash-image', abcc_resolve_image_model( 'gemini', 'gemini-2.5-flash-image', $before ) );
		abcc_assert_same( 'gemini-3.1-flash-lite-image', abcc_resolve_image_model( 'gemini', 'gemini-2.5-flash-image', strtotime( '2027-03-16 UTC' ) ) );

		abcc_assert_same( 'gpt-image-2.5-flare', abcc_get_setting_default( 'abcc_openai_image_model' ) );
	}
);

abcc_test(
	'image generation sends the successor of a retired saved model',
	function () {
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'down' );
		abcc_gemini_generate_images( 'k', 'a cat', 'gemini-3-pro-image-preview', '1K' );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_http_last_request']['url'], '/models/gemini-3-pro-image:generateContent' ) );

		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'down' );
		abcc_openai_generate_images( 'k', 'a cat', 1, '1024x1024', 'medium', 'gpt-image-1' );
		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_true( false !== strpos( $request['url'], 'images/generations' ) );
		abcc_assert_same( 'gpt-image-2.5-flare', json_decode( $request['args']['body'], true )['model'] );
	}
);

abcc_test(
	'the settings screen offers no retired image IDs',
	function () {
		$markup = file_get_contents( dirname( __DIR__ ) . '/includes/admin/tab-media-images.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		abcc_assert_false( strpos( $markup, '"gpt-image-1"' ) );
		abcc_assert_false( strpos( $markup, 'gemini-3-pro-image-preview' ) );
		abcc_assert_true( false !== strpos( $markup, 'abcc_get_image_model_picker_options' ) );
		abcc_assert_array_has_key( 'gpt-image-2.5-flare', abcc_get_image_model_options( 'openai' ) );
		abcc_assert_array_has_key( 'gemini-3-pro-image', abcc_get_image_model_options( 'gemini' ) );
	}
);

abcc_test(
	'a retired text model is swapped for its successor at call time and hidden from pickers',
	function () {
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['openai']['text_models']['gpt-old'] = array(
					'name'      => 'Old',
					'retires'   => '2020-01-01',
					'successor' => 'gpt-5.4-mini',
				);
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'k';

		abcc_assert_same( 'gpt-5.4-mini', abcc_resolve_retired_model( 'openai', 'gpt-old' ) );
		abcc_assert_same( 'gpt-5.4-mini', abcc_resolve_retired_model( 'openai', 'openai::gpt-old' ) );
		abcc_assert_same( 'gpt-old', abcc_resolve_retired_model( 'openai', 'gpt-old', strtotime( '2019-06-01 UTC' ) ), 'Before the date nothing changes.' );
		abcc_assert_same( 'gpt-5.4-mini', abcc_resolve_retired_model( 'openai', 'gpt-5.4-mini' ) );

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>x</p>' ) );
		$result = abcc_call_provider_api( 'openai', 'openai::gpt-old', 'p', array( 'api_key' => 'k' ) );
		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'gpt-5.4-mini', json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true )['model'] );

		$options = abcc_get_available_text_model_options();
		abcc_assert_false( isset( $options['openai']['options']['gpt-old'] ) );
		abcc_assert_true( isset( $options['openai']['options']['gpt-5.4-mini'] ) );

		// A saved selection of the retired model still validates, so topics keep running.
		abcc_assert_true( abcc_is_valid_text_model( 'openai::gpt-old' ) );

		$GLOBALS['abcc_test_filters'] = array();
		abcc_flush_provider_registry_cache();
	}
);

abcc_test(
	'documented removed aliases stay qualified and resolve to successors after their audited dates',
	function () {
		$aliases = array(
			'openai' => array(
				'models'    => array( 'o4-mini', 'o4-mini-2025-04-16', 'gpt-4.1-nano', 'gpt-4.1-nano-2025-04-14' ),
				'retires'   => '2026-10-23',
				'successor' => 'gpt-6-luna',
			),
			'claude' => array(
				'models'    => array( 'claude-sonnet-4-5', 'claude-sonnet-4-5-20250929' ),
				'retires'   => '2026-11-30',
				'successor' => 'claude-sonnet-5-5',
			),
		);

		foreach ( $aliases as $provider => $alias ) {
			$before   = strtotime( $alias['retires'] . ' UTC' ) - DAY_IN_SECONDS;
			$retired  = strtotime( $alias['retires'] . ' UTC' );
			$expected = abcc_build_model_selection( $provider, $alias['successor'] );

			foreach ( $alias['models'] as $model ) {
				$qualified = abcc_build_model_selection( $provider, $model );
				$parsed    = abcc_parse_model_selection( $model );
				abcc_assert_same( $provider, $parsed['provider'], $model . ' keeps its provider.' );
				abcc_assert_true( $parsed['known'], $model . ' remains a known legacy selection.' );
				abcc_assert_true( abcc_is_valid_text_model( $qualified ), $qualified . ' remains valid for saved jobs.' );
				abcc_assert_same( $model, abcc_resolve_retired_model( $provider, $model, $before ), $model . ' is unchanged before retirement.' );
				abcc_assert_same( $alias['successor'], abcc_resolve_retired_model( $provider, $qualified, $retired ), $qualified . ' resolves on its retirement date.' );

				$definition = abcc_get_model_definition( $qualified );
				abcc_assert_true( ! empty( $definition['legacy'] ), $qualified . ' is hidden legacy compatibility data.' );
				abcc_assert_same( $alias['retires'], $definition['retires'] );
				abcc_assert_same( $alias['successor'], $definition['successor'] );

				if ( 'claude' === $provider ) {
					abcc_assert_same( 64000, $definition['max_output'] );
					abcc_assert_same( 3.0, $definition['price_input'] );
					abcc_assert_same( 15.0, $definition['price_output'] );
					$GLOBALS['abcc_test_options']['claude_api_key'] = 'k';
					$before_options                                 = abcc_get_available_text_model_options();
					abcc_assert_true( isset( $before_options['claude']['options'][ $model ] ), $qualified . ' keeps its saved selection visible before retirement.' );
					abcc_assert_true( null !== abcc_estimate_post_cost( $qualified, 100 )['cost'] );
				}
			}

			add_filter(
				'abcc_retirement_now',
				function () use ( $retired ) {
					return $retired;
				}
			);
			$GLOBALS['abcc_test_options'][ $provider . '_api_key' ] = 'k';
			$options = abcc_get_available_text_model_options();
			foreach ( $alias['models'] as $model ) {
				$qualified = abcc_build_model_selection( $provider, $model );
				abcc_assert_same( $expected, abcc_get_live_model_selection( $model ) );
				abcc_assert_same( $expected, abcc_get_live_model_selection( $qualified ) );
				abcc_assert_false( isset( $options[ $provider ]['options'][ $model ] ), $qualified . ' is hidden after retirement.' );
			}
			abcc_assert_true( isset( $options[ $provider ]['options'][ $alias['successor'] ] ), $expected . ' remains selectable.' );
		}
	}
);

abcc_test(
	'the harness pins the retirement clock to the audit date',
	function () {
		abcc_assert_same( strtotime( '2026-11-05 12:00:00 UTC' ), abcc_get_retirement_now() );
		// Gemini 2.5 Flash keeps its own request shape until December 1, 2026.
		abcc_assert_same( 'gemini-2.5-flash', abcc_resolve_retired_model( 'gemini', 'gemini-2.5-flash' ) );
		abcc_assert_same( 'gemini-3.5-flash-lite', abcc_resolve_retired_model( 'gemini', 'gemini-2.5-flash', strtotime( '2026-12-01 UTC' ) ) );
		abcc_assert_same( 'gemini-3.6-flash', abcc_resolve_retired_model( 'gemini', 'gemini-3.5-flash' ) );
	}
);

abcc_test(
	'every listed text model carries a context window, and current ones carry prices',
	function () {
		foreach ( abcc_get_provider_registry() as $provider_id => $provider ) {
			if ( empty( $provider['text_models'] ) ) {
				continue;
			}
			abcc_assert_true( ! empty( $provider['pricing_date'] ), $provider_id . ' needs a pricing_date.' );
			abcc_assert_true( '' !== abcc_get_provider_transport( $provider_id ), $provider_id . ' needs a transport.' );

			foreach ( $provider['text_models'] as $model_id => $model ) {
				abcc_assert_true( ! empty( $model['context_window'] ), $model_id . ' needs a context_window.' );
				if ( empty( $model['legacy'] ) ) {
					abcc_assert_true( isset( $model['price_input'], $model['price_output'] ), $model_id . ' needs prices.' );
				}
			}
		}

		foreach ( array( 'openai::gpt-6-luna', 'openai::gpt-6.1-terra', 'claude::claude-sonnet-5-5', 'claude::claude-haiku-5-5', 'gemini::gemini-3.8-flash', 'gemini::gemini-3.5-flash-lite' ) as $selection ) {
			abcc_assert_true( abcc_is_valid_text_model( $selection ), $selection . ' must be listed.' );
		}
	}
);

abcc_test(
	'Gemini 3 models send thinkingLevel and no thinkingBudget; Gemini 2.5 keeps thinkingBudget',
	function () {
		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-3.8-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$config = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true )['generationConfig'];
		abcc_assert_same( 'LOW', $config['thinkingConfig']['thinkingLevel'] );
		abcc_assert_false( isset( $config['thinkingConfig']['thinkingBudget'] ) );
		abcc_assert_same( 2048, $config['maxOutputTokens'], 'Floor covers thought tokens.' );
		abcc_assert_true( abcc_get_model_flags( 'gemini', 'gemini-3.8-flash' )['reasoning'] );

		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-flash',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$config = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true )['generationConfig'];
		abcc_assert_same( 0, $config['thinkingConfig']['thinkingBudget'] );
		abcc_assert_false( isset( $config['thinkingConfig']['thinkingLevel'] ) );
	}
);

abcc_test(
	'GPT-6.1 Sol and Claude 5.5 send no sampling parameters and keep a reasoning floor',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'ok' ) );
		abcc_call_provider_api(
			'openai',
			'gpt-6.1-sol',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 50,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 'low', $body['reasoning_effort'] );
		abcc_assert_same( 4000, $body['max_completion_tokens'] );
		abcc_assert_false( isset( $body['temperature'] ) );

		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'text' => 'ok' ) ) ) ) );
		abcc_call_provider_api(
			'claude',
			'claude-sonnet-5-5',
			'p',
			array(
				'api_key'     => 'k',
				'max_tokens'  => 50,
				'temperature' => 0.2,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 4000, $body['max_tokens'] );
		abcc_assert_false( isset( $body['temperature'] ) );
	}
);

abcc_test(
	'a stored retired selection shows and validates as its successor instead of another provider',
	function () {
		abcc_assert_same( 'gemini::gemini-3.6-flash', abcc_get_live_model_selection( 'gemini-3.5-flash' ) );
		abcc_assert_same( 'gemini::gemini-3.6-flash', abcc_get_live_model_selection( 'gemini::gemini-3.5-flash' ) );
		abcc_assert_same( 'openai::gpt-5.4-mini', abcc_get_live_model_selection( 'openai::gpt-5.4-mini' ) );
		abcc_assert_same( 'not-a-model', abcc_get_live_model_selection( 'not-a-model' ) );

		$GLOBALS['abcc_test_options']['gemini_api_key'] = 'g';
		$options                                        = abcc_get_available_text_model_options();
		abcc_assert_false( isset( $options['gemini']['options']['gemini-3.5-flash'] ), 'Retired model is not offered.' );
		$parsed = abcc_parse_model_selection( abcc_get_live_model_selection( 'gemini-3.5-flash' ) );
		abcc_assert_true( isset( $options['gemini']['options'][ $parsed['model'] ] ), 'Its successor is, so the validator keeps the provider.' );
	}
);

abcc_test(
	'the chain does not re-send the primary request when the primary is qualified',
	function () {
		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options']['abcc_fallback_chain'] = array(
			array(
				'provider' => 'openai',
				'model'    => 'gpt-5.4-mini',
			),
		);
		$GLOBALS['abcc_test_options']['openai_api_key']      = 'k';

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{}',
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( 'would be a duplicate call' ) );

		$result = abcc_execute_fallback_chain( 'openai', 'openai::gpt-5.4-mini', 'p', array( 'api_key' => 'k' ), array() );
		abcc_assert_same( 'abcc_provider_rate_limited', $result['error']->get_error_code() );
		abcc_assert_same( 1, count( $GLOBALS['abcc_http_queue'] ), 'The identical target must be skipped.' );
		abcc_assert_same( 1, $result['attempt'] );
	}
);

abcc_test(
	'a retired primary is recorded and reported as the model that actually served',
	function () {
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['openai']['text_models']['gpt-old'] = array(
					'name'      => 'Old',
					'retires'   => '2020-01-01',
					'successor' => 'gpt-5.4-mini',
				);
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();

		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => 'ok' ),
							'finish_reason' => 'stop',
						),
					),
					'usage'   => array(
						'prompt_tokens'     => 100,
						'completion_tokens' => 200,
					),
				)
			)
		);
		$result = abcc_execute_fallback_chain( 'openai', 'openai::gpt-old', 'p', array( 'api_key' => 'k' ), array( 'job_id' => 780 ) );

		abcc_assert_same( null, $result['error'] );
		abcc_assert_same( 'gpt-5.4-mini', $result['served_model'] );

		$usage = abcc_get_job_usage( 780 );
		abcc_assert_same( 'gpt-5.4-mini', $usage[0]['model'] );
		// Priced as gpt-5.4-mini: (100 × 0.75 + 200 × 4.5) / 1M.
		abcc_assert_true( abs( $usage[0]['estimated_cost'] - 0.000975 ) < 0.0000000001, (string) $usage[0]['estimated_cost'] );

		$GLOBALS['abcc_test_filters'] = array();
		abcc_flush_provider_registry_cache();
	}
);
