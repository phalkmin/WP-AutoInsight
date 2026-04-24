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
