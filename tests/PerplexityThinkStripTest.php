<?php
/**
 * Perplexity reasoning models wrap thoughts in <think>; none of it is content (C-03).
 */

abcc_test(
	'closed think blocks are removed from perplexity output',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( "<think>plan the post</think>\n<h2>Title</h2>\n<p>Body</p>" ) );
		$result = abcc_call_provider_api(
			'perplexity',
			'sonar-reasoning-pro',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$lines  = array_values( array_filter( array_map( 'trim', $result['content'] ), 'strlen' ) );
		abcc_assert_same( array( '<h2>Title</h2>', '<p>Body</p>' ), $lines );
	}
);

abcc_test(
	'an unclosed think block (truncated reasoning) yields an empty completion',
	function () {
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => '<think>still thinking' ),
							'finish_reason' => 'length',
						),
					),
				)
			)
		);
		$result = abcc_call_provider_api(
			'perplexity',
			'sonar-reasoning-pro',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		abcc_assert_same( 'abcc_provider_empty_completion', $result['error']->get_error_code() );
	}
);

abcc_test(
	'openai output containing the literal word think is untouched',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Think about it.</p>' ) );
		$result = abcc_call_provider_api(
			'openai',
			'gpt-5.4-mini',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		abcc_assert_same( '<p>Think about it.</p>', trim( $result['content'][0] ) );
	}
);
