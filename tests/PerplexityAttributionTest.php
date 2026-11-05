<?php
/**
 * Perplexity requests identify the plugin via the X-Pplx-Integration header.
 */

abcc_test(
	'perplexity generation requests send the integration header',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body</p>' ) );
		abcc_call_provider_api(
			'perplexity',
			'sonar',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( 'https://api.perplexity.ai/chat/completions', $request['url'] );
		abcc_assert_same( 'wp-autoinsight', $request['args']['headers']['X-Pplx-Integration'] );
	}
);

abcc_test(
	'perplexity connection test sends the integration header',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Hi' ) );
		abcc_test_perplexity_connection( 'k' );
		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( 'https://api.perplexity.ai/chat/completions', $request['url'] );
		abcc_assert_same( 'wp-autoinsight', $request['args']['headers']['X-Pplx-Integration'] );
	}
);

abcc_test(
	'non-perplexity providers do not send the integration header',
	function () {
		abcc_test_queue_http_response( wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => '<p>Body</p>' ) ) ) ) );
		abcc_call_provider_api(
			'claude',
			'claude-sonnet-4-5',
			'p',
			array(
				'api_key'    => 'k',
				'max_tokens' => 300,
			)
		);
		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_same( false, isset( $request['args']['headers']['X-Pplx-Integration'] ) );
	}
);
