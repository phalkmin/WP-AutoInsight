<?php
/**
 * Context window and output ceiling come from the registry first (v4.6 §2.2).
 */

abcc_test(
	'context window reads the registry, then the legacy map, then 4096',
	function () {
		$listed = abcc_get_model_definition( 'openai::gpt-5.4-mini' );
		abcc_assert_same( (int) $listed['context_window'], abcc_get_model_context_window( 'openai::gpt-5.4-mini' ) );
		abcc_assert_same( (int) $listed['context_window'], abcc_get_model_context_window( 'gpt-5.4-mini' ) );

		// Retired o4-mini keeps the same context in the registry and legacy map.
		abcc_assert_same( 200000, abcc_get_model_context_window( 'o4-mini' ) );
		abcc_assert_same( 200000, abcc_get_model_context_window( 'openai::o4-mini' ) );

		abcc_assert_same( 4096, abcc_get_model_context_window( 'never-heard-of-it' ) );
	}
);

abcc_test(
	'max output caps the budget and unknown models are not capped',
	function () {
		abcc_assert_same( (int) abcc_get_model_definition( 'claude::claude-sonnet-4-6' )['max_output'], abcc_get_model_max_output( 'claude::claude-sonnet-4-6' ) );
		abcc_assert_same( 0, abcc_get_model_max_output( 'o4-mini' ) );

		abcc_assert_same( abcc_get_model_max_output( 'claude-sonnet-4-6' ), abcc_calculate_available_tokens( 'short prompt', 999999, 'claude-sonnet-4-6' ) );
		abcc_assert_same( 150000, abcc_calculate_available_tokens( 'short prompt', 150000, 'o4-mini' ) );
	}
);

abcc_test(
	'a prompt that fills the context yields zero so callers fail cleanly',
	function () {
		$huge = str_repeat( 'word ', 5000 );
		abcc_assert_same( 0, abcc_calculate_available_tokens( $huge, 500, 'never-heard-of-it' ) );
	}
);

abcc_test(
	'word target and token request follow the content language',
	function () {
		abcc_assert_same( 900, abcc_get_word_target( 1200 ) );
		abcc_assert_same( 150, abcc_get_word_target( 200 ) );

		// English: 900 words x 1.35 x 1.3 headroom.
		abcc_assert_same( (int) ceil( 900 * 1.35 * 1.3 ), abcc_words_to_token_request( 900, 'en_US' ) );
		abcc_assert_true( abcc_words_to_token_request( 900, 'pt_BR' ) > abcc_words_to_token_request( 900, 'en_US' ), 'Portuguese needs more tokens per word.' );
		abcc_assert_true( abcc_words_to_token_request( 900, 'ja' ) > abcc_words_to_token_request( 900, 'pt_BR' ), 'Non-Latin scripts need more still.' );

		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		abcc_assert_same( abcc_words_to_token_request( 900, 'pt_BR' ), abcc_get_body_token_request( 1200 ), 'The body call follows the site language.' );

		add_filter(
			'abcc_tokens_per_word',
			function () {
				return 2.0;
			}
		);
		abcc_assert_same( (int) ceil( 100 * 2.0 * 1.3 ), abcc_words_to_token_request( 100, 'en_US' ) );
	}
);

abcc_test(
	'output budget adds a reasoning reserve, applies the floor and clamps to max output',
	function () {
		// Non-reasoning model: floor only.
		abcc_assert_same( 1000, abcc_resolve_output_budget( 300, 'openai', 'gpt-5.4-mini' ) );
		abcc_assert_same( 1580, abcc_resolve_output_budget( 1580, 'openai', 'gpt-5.4-mini' ) );

		// Reasoning model with a 4000 floor: small requests land on the floor,
		// large ones get half the floor on top.
		abcc_assert_same( 4000, abcc_resolve_output_budget( 300, 'openai', 'gpt-6.1-sol' ) );
		abcc_assert_same( 7000, abcc_resolve_output_budget( 5000, 'openai', 'gpt-6.1-sol' ) );

		// max_output wins.
		$max = abcc_get_model_max_output( 'gpt-6.1-sol', 'openai' );
		abcc_assert_same( $max, abcc_resolve_output_budget( $max + 5000, 'openai', 'gpt-6.1-sol' ) );

		// Gemini 2.5's explicit thinking allowance is part of the same clamped budget.
		abcc_assert_same( 628, abcc_resolve_output_budget( 500, 'gemini', 'gemini-2.5-pro' ) );
	}
);

abcc_test(
	'estimator and transport agree on the output ceiling',
	function () {
		$cases = array(
			'openai::gpt-5.4-mini'          => 'max_completion_tokens',
			'openai::gpt-6.1-sol'           => 'max_completion_tokens',
			'claude::claude-sonnet-5-5'     => 'max_tokens',
			'perplexity::sonar'             => 'max_tokens',
			'gemini::gemini-2.5-pro'        => 'maxOutputTokens',
			'gemini::gemini-3.8-flash'      => 'maxOutputTokens',
			'gemini::gemini-2.5-flash-lite' => 'maxOutputTokens',
		);

		foreach ( array( 200, 1200, 4000 ) as $char_limit ) {
			foreach ( $cases as $selection => $param ) {
				$parsed = abcc_parse_model_selection( $selection );

				// The reply does not matter; only the request that was built.
				abcc_test_queue_http_response( '{}' );
				abcc_call_provider_api(
					$parsed['provider'],
					$selection,
					'Short prompt.',
					array(
						'api_key'    => 'k',
						'max_tokens' => abcc_get_body_token_request( $char_limit ),
					)
				);

				$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
				$sent = 'maxOutputTokens' === $param ? $body['generationConfig']['maxOutputTokens'] : $body[ $param ];

				abcc_assert_same(
					abcc_estimate_output_tokens( abcc_get_word_target( $char_limit ), $selection ),
					(int) $sent,
					sprintf( 'Estimate and request differ for %s at %d.', $selection, $char_limit )
				);
			}
		}
	}
);

abcc_test(
	'estimate is clamped to the context left after the prompt',
	function () {
		// Unknown model: 4096 context, no floors.
		abcc_assert_same( 4096 - 3000, abcc_estimate_output_tokens( 3000, 'never-heard-of-it', array( 'prompt_tokens' => 3000 ) ) );
	}
);
