<?php
/**
 * Regressions from the 4.6 generation-path review.
 */

abcc_test(
	'truncation repair uses the provider that served a fallback body',
	function () {
		remove_all_filters( 'abcc_fallback_backoff_us' );
		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'         => 'sk-openai',
			'claude_api_key'         => 'sk-claude',
			'abcc_fallback_chain'    => array(
				array(
					'provider' => 'claude',
					'model'    => 'claude-sonnet-4-6',
				),
			),
			'openai_generate_images' => false,
			'openai_generate_seo'    => false,
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Fallback Repair' ) );
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{"error":"slow down"}',
		);
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content'     => array(
						array(
							'type' => 'text',
							'text' => "<h2>Complete section</h2>\n<p>Complete paragraph.</p>\n<p>Cut off mid-sen",
						),
					),
					'stop_reason' => 'max_tokens',
				)
			)
		);
		abcc_test_queue_claude_success( '<p>A complete closing paragraph.</p>' );

		$post_id = abcc_openai_generate_post(
			'sk-openai',
			array( 'widgets' ),
			'openai::gpt-4.1-mini-2025-04-14',
			'default',
			false,
			200,
			'post',
			array( 'job_id' => 8801 )
		);

		abcc_assert_true( is_int( $post_id ) && $post_id > 0, 'The fallback body should still produce a post.' );
		abcc_assert_true( false !== strpos( get_post( $post_id )->post_content, 'A complete closing paragraph.' ), 'The backup provider must write the conclusion too.' );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_http_last_request']['url'], 'api.anthropic.com' ), 'The repair must not go back to the rate-limited primary.' );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'] );
	}
);

abcc_test(
	'Gemini thinking allowance is included before the context clamp',
	function () {
		add_filter(
			'abcc_provider_registry',
			function ( $registry ) {
				$registry['gemini']['text_models']['gemini-2.5-pro']['context_window'] = 700;
				$registry['gemini']['text_models']['gemini-2.5-pro']['max_output']     = 2000;
				return $registry;
			}
		);
		abcc_flush_provider_registry_cache();

		$words  = 285;
		$prompt = str_repeat( 'a', 400 );
		abcc_test_queue_http_response( abcc_test_gemini_body( array( array( 'text' => 'ok' ) ) ) );
		abcc_call_provider_api(
			'gemini',
			'gemini-2.5-pro',
			$prompt,
			array(
				'api_key'    => 'k',
				'max_tokens' => abcc_words_to_token_request( $words, 'en_US' ),
			)
		);

		$sent     = abcc_test_last_body()['generationConfig']['maxOutputTokens'];
		$estimate = abcc_estimate_output_tokens(
			$words,
			'gemini::gemini-2.5-pro',
			array(
				'locale'        => 'en_US',
				'prompt_tokens' => 100,
			)
		);

		abcc_assert_same( $estimate, $sent, 'Estimator and transport must apply the same total-output context clamp.' );
		abcc_assert_same( 600, $sent, 'Visible output plus thinking must fit the 700-token window after the 100-token prompt.' );
	}
);

abcc_test(
	'provider refusal and safety responses are errors rather than article content',
	function () {
		$cases = array(
			array(
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini-2025-04-14',
				'response' => abcc_test_chat_completion_response( '<p>Refused.</p>', 'content_filter' ),
			),
			array(
				'provider' => 'perplexity',
				'model'    => 'sonar',
				'response' => abcc_test_chat_completion_response( '<p>Refused.</p>', 'content_filter' ),
			),
			array(
				'provider' => 'claude',
				'model'    => 'claude-sonnet-4-6',
				'response' => array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'content'     => array(
								array(
									'type' => 'text',
									'text' => '<p>Refused.</p>',
								),
							),
							'stop_reason' => 'refusal',
						)
					),
				),
			),
			array(
				'provider' => 'gemini',
				'model'    => 'gemini-2.5-flash-lite',
				'response' => array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'promptFeedback' => array( 'blockReason' => 'SAFETY' ),
						)
					),
				),
			),
		);

		foreach ( $cases as $case ) {
			$GLOBALS['abcc_http_queue'][] = $case['response'];
			$result                       = abcc_call_provider_api( $case['provider'], $case['model'], 'Write an article.', array( 'api_key' => 'k' ) );

			abcc_assert_true( is_wp_error( $result['error'] ), $case['provider'] . ' must reject a blocked response.' );
			abcc_assert_same( 'abcc_provider_content_blocked', $result['error']->get_error_code(), $case['provider'] );
			abcc_assert_same( array(), $result['content'], 'Blocked text must not reach post assembly.' );
		}
	}
);

abcc_test(
	'regeneration preserves the topic brief, facts and answer-mode choice',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'         => 'sk-openai',
			'openai_generate_images' => false,
			'openai_generate_seo'    => false,
		);
		$topic_id                     = 7331;
		update_post_meta( $topic_id, '_abcc_topic_answer_mode', 'on' );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Original Topic Draft' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Original body.</p>' ) );
		$post_id = abcc_openai_generate_post(
			'sk-openai',
			array( 'widgets' ),
			'openai::gpt-4.1-mini-2025-04-14',
			'default',
			false,
			1200,
			'post',
			array(
				'topic_id'     => $topic_id,
				'prompt'       => 'Compare {keyword} sizes for beginners.',
				'source_notes' => 'The small widget is 10 cm. Source: https://example.com/specs',
			)
		);
		abcc_assert_true( is_int( $post_id ) && $post_id > 0 );

		update_post_meta( $topic_id, '_abcc_topic_answer_mode', 'off' );
		$_POST = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_regenerate_post();
		$_POST = array();

		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		$job_id  = $GLOBALS['abcc_test_last_json']['data']['job_id'];
		$payload = get_post_meta( $job_id, '_abcc_job_payload', true );
		abcc_assert_same( $topic_id, (int) $payload['topic_id'] );
		abcc_assert_same( 'Compare {keyword} sizes for beginners.', $payload['prompt'] );
		abcc_assert_same( 'The small widget is 10 cm. Source: https://example.com/specs', $payload['source_notes'] );
		abcc_assert_true( ! empty( $payload['answer_mode'] ), 'Regeneration uses the original answer-mode choice.' );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Regenerated Topic Draft' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Regenerated body.</p>' ) );
		abcc_process_generation_job( $job_id );

		$body = abcc_test_last_body();
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'Compare widgets sizes for beginners.' ) );
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'https://example.com/specs' ) );
		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'Answer-focused structure' ) );
	}
);

abcc_test(
	'regeneration of legacy params keeps the global answer-mode behavior',
	function () {
		$GLOBALS['abcc_test_options']['abcc_answer_engine_mode']         = true;
		$GLOBALS['abcc_test_post_meta'][7332]['_abcc_generation_params'] = wp_json_encode(
			array(
				'keywords'   => array( 'widgets' ),
				'model'      => 'openai::gpt-4.1-mini-2025-04-14',
				'tone'       => 'default',
				'char_limit' => 1200,
			)
		);
		$_POST = array(
			'post_id' => 7332,
			'nonce'   => 'x',
		);

		abcc_handle_regenerate_post();
		$_POST = array();

		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		$payload = get_post_meta( $GLOBALS['abcc_test_last_json']['data']['job_id'], '_abcc_job_payload', true );
		abcc_assert_true( ! empty( $payload['answer_mode'] ) );
	}
);
