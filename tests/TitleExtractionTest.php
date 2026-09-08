<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
/**
 * Regression tests for generated-title extraction (v4.3).
 *
 * Chatty models (notably Gemini) answer the title prompt with a preamble
 * ("Here are some catchy blog post titles...:") followed by a numbered list
 * of options. Taking the first response line blindly turned that preamble
 * into the post title. The extractor must skip preamble lines and return
 * one clean title.
 *
 * @package WP-AutoInsight
 */

abcc_test(
	'title extractor skips preamble and picks the first listed option',
	function () {
		$lines = array(
			'Here are some catchy blog post titles about Claude Code, playing on different angles and tones:',
			'',
			'1. **Claude Code: Your New Pair Programmer**',
			'2. **Ship Faster with Claude Code**',
		);

		abcc_assert_same( 'Claude Code: Your New Pair Programmer', abcc_extract_generated_title( $lines ) );
	}
);

abcc_test(
	'title extractor cleans quotes, markdown, and heading markers from a single-line title',
	function () {
		abcc_assert_same( 'Hello World', abcc_extract_generated_title( array( '## **"Hello World"**' ) ) );
	}
);

abcc_test(
	'title extractor strips bullet markers',
	function () {
		abcc_assert_same( 'A Great Post', abcc_extract_generated_title( array( '- A Great Post' ) ) );
	}
);

abcc_test(
	'title extractor keeps titles containing a colon mid-line',
	function () {
		abcc_assert_same( 'Claude Code: A Field Guide', abcc_extract_generated_title( array( 'Claude Code: A Field Guide' ) ) );
	}
);

abcc_test(
	'title extractor falls back to a de-coloned preamble when nothing else exists',
	function () {
		abcc_assert_same( 'Here are five options', abcc_extract_generated_title( array( 'Here are five options:' ) ) );
	}
);

abcc_test(
	'title extractor returns empty string for empty input',
	function () {
		abcc_assert_same( '', abcc_extract_generated_title( array( '', '   ' ) ) );
	}
);

abcc_test(
	'generate title survives a chatty Gemini list response end to end',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array(
								'parts' => array(
									array( 'text' => "Here are some catchy blog post titles about Claude Code, playing on different angles and tones:\n\n1. **Claude Code: Your New Pair Programmer**\n2. **Ship Faster with Claude Code**" ),
								),
							),
							'finishReason' => 'STOP',
						),
					),
				)
			),
		);

		$title = abcc_generate_title( 'g-test', array( 'Claude Code' ), 'gemini-2.5-flash' );
		abcc_assert_same( 'Claude Code: Your New Pair Programmer', $title );
	}
);

abcc_test(
	'title prompt instructs the model to return only the title',
	function () {
		$GLOBALS['abcc_http_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'candidates' => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => 'A Title' ) ) ),
							'finishReason' => 'STOP',
						),
					),
				)
			),
		);

		abcc_generate_title( 'g-test', array( 'Claude Code' ), 'gemini-2.5-flash' );

		$body   = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		$prompt = $body['contents'][0]['parts'][0]['text'];
		abcc_assert_true( false !== strpos( $prompt, 'only the title' ), 'Prompt must demand a single title with no alternatives.' );
	}
);
