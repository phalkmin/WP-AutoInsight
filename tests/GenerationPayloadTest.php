<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'generation payload and tracking meta keep the normalized shape',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'    => 'gemini-2.5-flash',
			'openai_tone'      => 'professional',
			'openai_char_limit'=> 333,
		);

		$payload = abcc_build_generation_payload(
			array(
				'keywords'  => array( 'alpha', 'beta' ),
				'post_type' => 'post',
				'category'  => 5,
				'template'  => 'default',
				'source'    => 'bulk',
			)
		);

		$meta   = abcc_build_generation_tracking_meta( $payload );
		$params = json_decode( $meta['_abcc_generation_params'], true );

		abcc_assert_same( 'gemini-2.5-flash', $payload['model'] );
		abcc_assert_same( 'professional', $payload['tone'] );
		abcc_assert_same( 333, $payload['char_limit'] );
		abcc_assert_same( 'bulk', $params['source'] );
		abcc_assert_same( 5, $params['category'] );
		abcc_assert_equals( array( 'alpha', 'beta' ), $params['keywords'] );
	}
);

abcc_test(
	'detailed generation surfaces truncated and error alongside content',
	function () {
		// finish_reason "length" is the OpenAI truncation signal.
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => "<p>Cut off here" ),
							'finish_reason' => 'length',
						),
					),
					'usage'   => array( 'prompt_tokens' => 10, 'completion_tokens' => 20 ),
				)
			)
		);

		$result = abcc_generate_content_detailed(
			'sk-test',
			'Write something.',
			'gpt-4.1-mini-2025-04-14',
			200
		);

		abcc_assert_array_has_key( 'truncated', $result, 'Detailed result must expose truncated.' );
		abcc_assert_array_has_key( 'error', $result, 'Detailed result must expose error.' );
		abcc_assert_array_has_key( 'content', $result, 'Detailed result must expose content.' );
		abcc_assert_true( $result['truncated'], 'finish_reason "length" must set truncated.' );
	}
);

abcc_test(
	'the legacy generate_content wrapper keeps its array-or-false contract',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );

		$ok = abcc_generate_content( 'sk-test', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );
		abcc_assert_true( is_array( $ok ), 'Success must still return an array of lines.' );

		// Nothing queued: the request errors.
		$bad = abcc_generate_content( 'sk-test', 'Prompt.', 'gpt-4.1-mini-2025-04-14', 200 );
		abcc_assert_false( false !== $bad, 'Failure must still return false, not an array.' );
	}
);
