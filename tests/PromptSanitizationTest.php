<?php
/**
 * Outbound prompts must reach providers unmodified.
 */

abcc_test(
	'outbound prompts are not HTML-sanitized',
	function () {
		$prompt = 'Write about "quoted terms", 5 < 10, and the <Foo> product line.';

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Body text.' ) );
		abcc_call_provider_api(
			'openai',
			'gpt-4.1-mini-2025-04-14',
			$prompt,
			array( 'api_key' => 'test-key' )
		);

		$sent = $GLOBALS['abcc_http_last_request'];
		$body = json_decode( $sent['args']['body'], true );
		$out  = $body['messages'][0]['content'] ?? '';

		abcc_assert_true(
			false !== strpos( $out, '"quoted terms"' ),
			'Straight quotes must survive: got ' . $out
		);
		abcc_assert_false(
			false !== strpos( $out, '&quot;' ),
			'Prompt must not be entity-encoded: got ' . $out
		);
		abcc_assert_true(
			false !== strpos( $out, '<Foo>' ),
			'Tag-like product names must survive: got ' . $out
		);
	}
);

abcc_test(
	'audio prompts truncate very long transcripts',
	function () {
		$long = str_repeat( 'word ', 20000 );

		$intro   = abcc_audio_build_intro_prompt( $long, array() );
		$rewrite = abcc_audio_build_rewrite_prompt( $long, array() );

		foreach ( array( 'intro' => $intro, 'rewrite' => $rewrite ) as $label => $prompt ) {
			abcc_assert_true(
				strlen( $prompt ) < strlen( $long ),
				$label . ' prompt must be shorter than the raw transcript.'
			);
			abcc_assert_true(
				str_word_count( $prompt ) < 8000,
				$label . ' prompt should be bounded well under the context window.'
			);
		}
	}
);

abcc_test(
	'rewrite handler bounds very long post content before prompting',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'A Very Long Post',
				'post_content' => str_repeat( 'word ', 20000 ),
			)
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( '<h2>Better</h2>' . "\n" . '<p>Rewritten.</p>' ) );

		$_POST = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();
		$_POST = array();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( is_array( $json ) && $json['success'], 'Rewrite should succeed with a canned response.' );

		$body   = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		$prompt = $body['messages'][0]['content'] ?? '';

		abcc_assert_true(
			str_word_count( $prompt ) < 8000,
			'Rewrite prompt must be bounded well under the raw 20,000-word content.'
		);
	}
);

abcc_test(
	'rewrite failures surface the formatted error, not the old generic string',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'A Post',
				'post_content' => 'Short body.',
			)
		);

		// Nothing queued: the provider request errors.
		$_POST = array(
			'post_id' => $post_id,
			'nonce'   => 'x',
		);
		abcc_handle_rewrite_post();
		$_POST = array();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( is_array( $json ) && ! $json['success'], 'Rewrite should fail without a canned response.' );

		$message = (string) ( $json['data']['message'] ?? '' );
		abcc_assert_false(
			'Failed to generate new content' === $message,
			'Rewrite must not use the pre-4.4 generic error string.'
		);
		abcc_assert_true(
			false !== strpos( $message, 'Generation Log' ),
			'Rewrite errors should route through abcc_format_generation_error, got: ' . $message
		);
	}
);
