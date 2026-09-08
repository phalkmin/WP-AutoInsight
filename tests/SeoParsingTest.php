<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'seo extraction parses structured JSON responses',
	function () {
		$result = abcc_extract_title_and_seo_from_response(
			array(
				'{"title":"Hello","meta_description":"Desc","primary_keyword":"alpha","secondary_keywords":["beta","gamma"],"social_excerpt":"Excerpt"}',
			),
			array( 'alpha', 'beta' ),
			'test-key',
			'gpt-4.1-mini-2025-04-14'
		);

		abcc_assert_same( 'Hello', $result['title'] );
		abcc_assert_same( 'Desc', $result['seo_data']['meta_description'] );
		abcc_assert_same( 'alpha', $result['seo_data']['primary_keyword'] );
	}
);

abcc_test(
	'seo extraction preserves legacy bracket fallback parsing',
	function () {
		$result = abcc_extract_title_and_seo_from_response(
			array(
				'[TITLE]',
				'Legacy Title',
				'[SEO]',
				'Meta Description: Legacy description',
				'Primary Keyword: alpha',
				'Secondary Keywords: beta, gamma',
				'Social Excerpt: Legacy excerpt',
			),
			array( 'alpha', 'beta' ),
			'test-key',
			'gpt-4.1-mini-2025-04-14'
		);

		abcc_assert_same( 'Legacy Title', $result['title'] );
		abcc_assert_same( 'Legacy description', $result['seo_data']['meta_description'] );
		abcc_assert_equals( array( 'beta', 'gamma' ), $result['seo_data']['secondary_keywords'] );
	}
);

abcc_test(
	'SEO JSON extraction survives fenced blocks and brace-bearing prose',
	function () {
		$payload = wp_json_encode(
			array(
				'title'              => 'A Real Title',
				'meta_description'   => 'A description.',
				'primary_keyword'    => 'widgets',
				'secondary_keywords' => array( 'gadgets' ),
				'social_excerpt'     => 'Short.',
			)
		);

		$cases = array(
			'bare'   => $payload,
			'fenced' => "```json\n" . $payload . "\n```",
			'prose'  => "Here is the SEO data you asked for {note: see below}:\n" . $payload,
		);

		foreach ( $cases as $label => $raw ) {
			$parsed = abcc_parse_seo_json( $raw );
			abcc_assert_false( is_wp_error( $parsed ), $label . ' case should parse, got a WP_Error.' );
			abcc_assert_same( 'A Real Title', $parsed['title'], $label . ' case should recover the title.' );
		}
	}
);

abcc_test(
	'unparseable SEO output returns an explicit error, not a silent retry',
	function () {
		$parsed = abcc_parse_seo_json( 'I am afraid I cannot help with that request.' );
		abcc_assert_true( is_wp_error( $parsed ), 'Unparseable output must return a WP_Error.' );
	}
);

abcc_test(
	'SEO meta fields are clamped to their length limits',
	function () {
		$parsed = abcc_parse_seo_json(
			wp_json_encode(
				array(
					'title'            => 'T',
					'meta_description' => str_repeat( 'a', 400 ),
					'social_excerpt'   => str_repeat( 'b', 400 ),
					'primary_keyword'  => 'k',
				)
			)
		);

		abcc_assert_true( mb_strlen( $parsed['meta_description'] ) <= 160, 'meta_description must be clamped to 160.' );
		abcc_assert_true( mb_strlen( $parsed['social_excerpt'] ) <= 200, 'social_excerpt must be clamped to 200.' );
	}
);

abcc_test(
	'legacy bracket-parsed SEO fields are clamped to their length limits',
	function () {
		$long  = str_repeat( 'a', 400 );
		$lines = array(
			'[TITLE]',
			'Legacy Title',
			'[SEO]',
			'Meta Description: ' . $long,
			'Social Excerpt: ' . $long,
		);

		// No braces anywhere: JSON parsing fails and the bracket parser runs.
		$out = abcc_extract_title_and_seo_from_response( $lines, array( 'kw' ), 'sk-test', 'gpt-4.1-mini-2025-04-14' );

		abcc_assert_same( 'Legacy Title', $out['title'], 'Bracket parser should recover the title.' );
		abcc_assert_true( mb_strlen( $out['seo_data']['meta_description'] ) <= 160, 'Legacy-parsed meta_description must be clamped to 160.' );
		abcc_assert_true( mb_strlen( $out['seo_data']['social_excerpt'] ) <= 200, 'Legacy-parsed social_excerpt must be clamped to 200.' );
	}
);
