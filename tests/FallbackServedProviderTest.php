<?php
/**
 * Post assembly must follow the provider that actually served the body.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'fallback-served Perplexity body gets linked citations and _abcc_model records the served model',
	function () {
		remove_all_filters( 'abcc_fallback_backoff_us' );
		add_filter(
			'abcc_fallback_backoff_us',
			function () {
				return 0;
			}
		);
		$GLOBALS['abcc_test_options'] = array(
			'openai_api_key'                 => 'sk-openai',
			'perplexity_api_key'             => 'pplx-key',
			'prompt_select'                  => 'gpt-4.1-mini-2025-04-14',
			'abcc_fallback_chain'            => array( array( 'provider' => 'perplexity', 'model' => 'sonar' ) ),
			'abcc_perplexity_citation_style' => 'inline',
			'abcc_default_post_status'       => 'draft',
			'openai_generate_images'         => false,
			'openai_generate_seo'            => false,
		);

		// 1) title via OpenAI, 2) body via OpenAI fails, 3) body via Perplexity with a citation.
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response( 'Served Title' );
		$GLOBALS['abcc_http_queue'][] = array( 'response' => array( 'code' => 429 ), 'body' => '{"error":"slow down"}' );
		$GLOBALS['abcc_http_queue'][] = abcc_test_chat_completion_response(
			"<h2>Facts</h2>\n<p>Water is wet [1].</p>",
			'stop',
			array( 'citations' => array( 'https://example.com/source' ) )
		);

		$post_id = abcc_openai_generate_post( 'sk-openai', array( 'water' ), 'gpt-4.1-mini-2025-04-14', 'default', false, 200, 'post', array( 'job_id' => 5 ) );

		abcc_assert_true( is_int( $post_id ) && $post_id > 0, 'Post must be created: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : '' ) );
		abcc_assert_same( array(), $GLOBALS['abcc_http_queue'], 'All three calls consumed.' );

		$content = get_post( $post_id )->post_content;
		abcc_assert_true( false !== strpos( $content, 'href="https://example.com/source"' ), 'Citation marker must be linked: ' . $content );
		abcc_assert_same( 'sonar', get_post_meta( $post_id, '_abcc_model', true ), 'Served model recorded.' );

		$params = json_decode( get_post_meta( $post_id, '_abcc_generation_params', true ), true );
		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', $params['model'], 'Regeneration params keep the requested model.' );
		abcc_assert_true( empty( get_transient( abcc_citation_transient_key( array( 'job_id' => 5 ) ) ) ), 'Citation stash consumed.' );
	}
);
