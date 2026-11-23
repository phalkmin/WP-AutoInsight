<?php
/**
 * Per-call usage records and the job-log narration (v4.6 §5.4, §7).
 */

/**
 * Float comparison for costs; round() and literals can differ in the last bit.
 *
 * @param float      $expected Expected value.
 * @param float|null $actual   Actual value.
 * @param string     $message  Failure message.
 */
function abcc_test_assert_cost( $expected, $actual, $message = '' ) {
	abcc_assert_true( null !== $actual && abs( (float) $expected - (float) $actual ) < 0.0000000001, $message ? $message : sprintf( 'Expected cost %s, got %s', $expected, wp_json_encode( $actual ) ) );
}

abcc_test(
	'cost comes from the registry prices and is null when they are unknown',
	function () {
		// Claude Sonnet 4.6: $3 in, $15 out per 1M.
		abcc_test_assert_cost(
			0.000066,
			abcc_estimate_call_cost(
				'claude',
				'claude-sonnet-4-6',
				array(
					'input'     => 7,
					'output'    => 3,
					'reasoning' => null,
				)
			)
		);

		// Gemini bills thought tokens as output: (5 × 0.30 + (9 + 4) × 2.50) / 1M.
		abcc_test_assert_cost(
			0.000034,
			abcc_estimate_call_cost(
				'gemini',
				'gemini-2.5-flash',
				array(
					'input'     => 5,
					'output'    => 9,
					'reasoning' => 4,
				)
			)
		);

		// OpenAI already counts reasoning inside output: (10 × 0.75 + 8 × 4.5) / 1M.
		abcc_test_assert_cost(
			0.0000435,
			abcc_estimate_call_cost(
				'openai',
				'gpt-5.4-mini',
				array(
					'input'     => 10,
					'output'    => 8,
					'reasoning' => 8,
				)
			)
		);

		abcc_assert_same(
			null,
			abcc_estimate_call_cost(
				'perplexity',
				'sonar-pro',
				array(
					'input'     => 10,
					'output'    => 10,
					'reasoning' => null,
				)
			),
			'Legacy Sonar Pro has no price.'
		);
		abcc_assert_same(
			null,
			abcc_estimate_call_cost(
				'openai',
				'gpt-5.4-mini',
				array(
					'input'     => null,
					'output'    => null,
					'reasoning' => null,
				)
			),
			'No tokens, no cost.'
		);
	}
);

abcc_test(
	'the chain records one row per attempt with the model that served it',
	function () {
		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options']['abcc_fallback_chain'] = array(
			array(
				'provider' => 'claude',
				'model'    => 'claude-sonnet-4-6',
			),
		);
		$GLOBALS['abcc_test_options']['claude_api_key']      = 'ak';

		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{}',
		);
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content' => array( array( 'text' => '<p>From Claude</p>' ) ),
					'usage'   => array(
						'input_tokens'  => 7,
						'output_tokens' => 3,
					),
				)
			)
		);

		$chain = abcc_execute_fallback_chain(
			'openai',
			'openai::gpt-5.4-mini',
			'p',
			array(
				'api_key'   => 'k',
				'operation' => 'article',
			),
			array( 'job_id' => 777 )
		);
		abcc_assert_same( 'claude', $chain['served_provider'] );

		$usage = abcc_get_job_usage( 777 );
		abcc_assert_same( 2, count( $usage ) );

		abcc_assert_same( 'openai', $usage[0]['provider'] );
		abcc_assert_same( 'gpt-5.4-mini', $usage[0]['model'] );
		abcc_assert_same( 'abcc_provider_rate_limited', $usage[0]['error'] );
		abcc_assert_same( null, $usage[0]['input_tokens'], 'A failed call without usage stores null, not 0.' );
		abcc_assert_same( null, $usage[0]['estimated_cost'] );

		abcc_assert_same( 'claude', $usage[1]['provider'] );
		abcc_assert_same( 'claude-sonnet-4-6', $usage[1]['model'] );
		abcc_assert_same( 'article', $usage[1]['operation'] );
		abcc_assert_same( 7, $usage[1]['input_tokens'] );
		abcc_assert_same( 3, $usage[1]['output_tokens'] );
		abcc_test_assert_cost( 0.000066, $usage[1]['estimated_cost'] );
		abcc_assert_same( 'registry', $usage[1]['pricing_source'] );
		abcc_assert_same( '2026-11-05', $usage[1]['pricing_date'] );

		$summary = abcc_summarize_job_usage( 777 );
		abcc_assert_same( 2, $summary['calls'] );
		abcc_assert_same( 7, $summary['input_tokens'] );
		abcc_assert_same( 3, $summary['output_tokens'] );
		abcc_test_assert_cost( 0.000066, $summary['cost'] );
		abcc_assert_same( 1, $summary['unpriced'] );

		$note = abcc_format_job_usage_note( 777 );
		abcc_assert_true( false !== strpos( $note, '2 calls' ), $note );
		abcc_assert_true( false !== strpos( $note, 'some calls unpriced' ), $note );
		abcc_assert_true( false !== strpos( $note, '2026-11-05' ), $note );
	}
);

abcc_test(
	'calls without a job record nothing and a job without calls has no usage note',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'ok' ) );
		abcc_execute_fallback_chain( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ), array() );
		abcc_assert_same( array(), abcc_get_job_usage( 0 ) );
		abcc_assert_same( '', abcc_format_job_usage_note( 4242 ) );
	}
);

abcc_test(
	'a reasoning-only reply still records its tokens',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array(
								'content'           => '',
								'reasoning_content' => 'thinking',
							),
							'finish_reason' => 'length',
						),
					),
					'usage'   => array(
						'prompt_tokens'             => 12,
						'completion_tokens'         => 1000,
						'completion_tokens_details' => array( 'reasoning_tokens' => 1000 ),
					),
				)
			)
		);
		abcc_execute_fallback_chain( 'openai', 'gpt-5.4-mini', 'p', array( 'api_key' => 'k' ), array( 'job_id' => 778 ) );

		$usage = abcc_get_job_usage( 778 );
		abcc_assert_same( 1, count( $usage ) );
		abcc_assert_same( 'abcc_reasoning_only', $usage[0]['error'] );
		abcc_assert_same( 1000, $usage[0]['output_tokens'] );
		abcc_assert_same( 1000, $usage[0]['reasoning_tokens'] );
		abcc_assert_true( $usage[0]['estimated_cost'] > 0 );
	}
);

abcc_test(
	'a job run narrates title, body and usage in its notes',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_generate_seo'    => false,
			'openai_generate_images' => false,
			'openai_api_key'         => 'k',
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( 'A Fine Title' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "<h2>Intro</h2>\n<p>One two three four five six.</p>" ) );

		$post_id = abcc_openai_generate_post( 'k', array( 'alpha' ), 'openai::gpt-5.4-mini', 'default', false, 200, 'post', array( 'job_id' => 779 ) );
		abcc_assert_false( is_wp_error( $post_id ), is_wp_error( $post_id ) ? $post_id->get_error_message() : '' );

		$notes  = (array) get_post_meta( 779, '_abcc_job_notes', true );
		$joined = implode( ' | ', $notes );
		abcc_assert_true( false !== strpos( $joined, 'Title chosen' ), $joined );
		abcc_assert_true( false !== strpos( $joined, 'Body drafted: 7 words with GPT-5.4 mini' ), $joined );
		abcc_assert_true( false !== strpos( $joined, 'Usage: 2 calls' ), $joined );
		abcc_assert_same( 2, count( abcc_get_job_usage( 779 ) ) );
	}
);

abcc_test(
	'word count ignores tags and counts any script',
	function () {
		abcc_assert_same( 5, abcc_count_content_words( array( '<h2>Um título</h2>', '<p>com três palavras</p>' ) ) );
		abcc_assert_same( 0, abcc_count_content_words( array() ) );
	}
);

abcc_test(
	'Gemini thought tokens are priced when visible input and output are absent',
	function () {
		abcc_test_assert_cost(
			0.00001,
			abcc_estimate_call_cost(
				'gemini',
				'gemini-2.5-flash',
				array(
					'input'     => null,
					'output'    => null,
					'reasoning' => 4,
				)
			)
		);

		abcc_assert_same(
			null,
			abcc_estimate_call_cost(
				'openai',
				'gpt-5.4-mini',
				array(
					'input'     => null,
					'output'    => null,
					'reasoning' => 4,
				)
			),
			'Reasoning alone is not separately billable on OpenAI.'
		);
	}
);

abcc_test(
	'a failed background job narrates the usage it spent',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'         => 'sk-test',
			'prompt_select'          => 'openai::gpt-5.4-mini',
			'openai_generate_seo'    => false,
			'openai_generate_images' => false,
		);
		// The title call is billed; the body call is refused.
		abcc_test_queue_http_response( abcc_test_fake_generation( 'A Fine Title' ) );
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{}',
		);

		$job_id = abcc_queue_generation_job(
			abcc_build_generation_payload( array( 'keywords' => array( 'widgets' ) ) ),
			array( 'process_inline' => true )
		);

		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_same( 2, count( abcc_get_job_usage( $job_id ) ) );
		abcc_assert_true(
			false !== strpos( implode( ' | ', (array) get_post_meta( $job_id, '_abcc_job_notes', true ) ), 'Usage: 2 calls' ),
			'The failed row must expose the same usage note as an inline failure.'
		);
	}
);

abcc_test(
	'a failed job that used no tokens has no usage note',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key' => 'sk-test',
			'prompt_select'  => 'openai::gpt-5.4-mini',
		);
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 429 ),
			'body'     => '{}',
		);

		$job_id = abcc_queue_generation_job(
			abcc_build_generation_payload( array( 'keywords' => array( 'widgets' ) ) ),
			array( 'process_inline' => true )
		);

		abcc_assert_same( ABCC_Job::STATUS_FAILED, get_post_meta( $job_id, '_abcc_job_status', true ) );
		abcc_assert_same( 1, count( abcc_get_job_usage( $job_id ) ), 'The refused call is still recorded.' );
		abcc_assert_false(
			false !== strpos( implode( ' | ', (array) get_post_meta( $job_id, '_abcc_job_notes', true ) ), 'Usage:' ),
			'A row of zero tokens and unknown cost tells the user nothing.'
		);
		abcc_assert_same( '', abcc_format_job_usage_note( $job_id ) );
	}
);

abcc_test(
	'a successful call that reports no usage still gets its "cost unknown" line',
	function () {
		$GLOBALS['abcc_test_post_meta'][905]['_abcc_job_usage'] = array(
			array(
				'provider'         => 'custom',
				'input_tokens'     => null,
				'output_tokens'    => null,
				'reasoning_tokens' => null,
				'estimated_cost'   => null,
				'pricing_date'     => '',
				'error'            => '',
			),
		);
		$note = abcc_format_job_usage_note( 905 );
		abcc_assert_true( false !== strpos( $note, 'Usage: 1 call' ), $note );
		abcc_assert_true( false !== strpos( $note, 'cost unknown' ), $note );

		// The same record with an error is a refused call and says nothing.
		$GLOBALS['abcc_test_post_meta'][905]['_abcc_job_usage'][0]['error'] = 'abcc_provider_auth_error';
		abcc_assert_same( '', abcc_format_job_usage_note( 905 ) );
	}
);
