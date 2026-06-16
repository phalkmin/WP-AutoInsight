<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
/**
 * Regression tests for keyword group focus selection.
 *
 * A keyword group is a pool of related topics; one keyword should be picked
 * randomly per generation so each post stays focused. The full group remains
 * available for templates that explicitly want the comma-joined list.
 */

abcc_test(
	'pick focus keyword returns a member of the array',
	function () {
		$keywords = array( 'atoms', 'bonds', 'reactions', 'acids' );

		$picked = abcc_pick_focus_keyword( $keywords );

		abcc_assert_true( in_array( $picked, $keywords, true ), 'Picked keyword must come from the input list.' );
	}
);

abcc_test(
	'pick focus keyword returns the only entry when array has one keyword',
	function () {
		$picked = abcc_pick_focus_keyword( array( 'chemistry' ) );

		abcc_assert_same( 'chemistry', $picked );
	}
);

abcc_test(
	'pick focus keyword returns empty string for empty array',
	function () {
		abcc_assert_same( '', abcc_pick_focus_keyword( array() ) );
	}
);

abcc_test(
	'pick focus keyword skips empty entries',
	function () {
		$keywords = array( '', '   ', 'real-keyword', '' );

		$picked = abcc_pick_focus_keyword( $keywords );

		abcc_assert_same( 'real-keyword', $picked );
	}
);

abcc_test(
	'template prompt substitutes {keyword} with the focus keyword',
	function () {
		update_option(
			'abcc_content_templates',
			array(
				'default' => array(
					'name'   => 'Default Template',
					'prompt' => 'Write about {keyword}.',
				),
			)
		);

		$prompt = abcc_build_content_template_prompt(
			'default',
			'Some Title',
			array( 'acid-base reactions' ),
			array(
				'tone'         => 'friendly',
				'category'     => 0,
				'char_limit'   => 200,
				'keywords_all' => array( 'atoms', 'bonds', 'acid-base reactions' ),
			)
		);

		abcc_assert_true(
			false !== strpos( $prompt, 'Write about acid-base reactions.' ),
			'Expected {keyword} substitution to use the focus keyword.'
		);
	}
);

abcc_test(
	'template prompt substitutes {keywords} from keywords_all when provided',
	function () {
		update_option(
			'abcc_content_templates',
			array(
				'default' => array(
					'name'   => 'Default Template',
					'prompt' => 'List: {keywords}',
				),
			)
		);

		$prompt = abcc_build_content_template_prompt(
			'default',
			'Some Title',
			array( 'acid-base reactions' ),
			array(
				'tone'         => 'friendly',
				'category'     => 0,
				'char_limit'   => 200,
				'keywords_all' => array( 'atoms', 'bonds', 'acid-base reactions' ),
			)
		);

		abcc_assert_true(
			false !== strpos( $prompt, 'List: atoms, bonds, acid-base reactions' ),
			'Expected {keywords} to render the full keyword list when keywords_all is supplied.'
		);
	}
);

abcc_test(
	'template prompt falls back to keywords arg for {keywords} when keywords_all missing',
	function () {
		update_option(
			'abcc_content_templates',
			array(
				'default' => array(
					'name'   => 'Default Template',
					'prompt' => 'List: {keywords}',
				),
			)
		);

		$prompt = abcc_build_content_template_prompt(
			'default',
			'Some Title',
			array( 'alpha', 'beta' ),
			array(
				'tone'       => 'friendly',
				'category'   => 0,
				'char_limit' => 200,
			)
		);

		abcc_assert_true(
			false !== strpos( $prompt, 'List: alpha, beta' ),
			'Expected {keywords} to fall back to the $keywords parameter when keywords_all is absent.'
		);
	}
);

abcc_test(
	'template prompt appends ABCC_CONTENT_FORMAT_REQUIREMENTS',
	function () {
		update_option(
			'abcc_content_templates',
			array(
				'default' => array(
					'name'   => 'Default Template',
					'prompt' => 'Write about {keyword}.',
				),
			)
		);

		$prompt = abcc_build_content_template_prompt(
			'default',
			'Some Title',
			array( 'chemistry' ),
			array(
				'tone'         => 'friendly',
				'category'     => 0,
				'char_limit'   => 200,
				'keywords_all' => array( 'chemistry' ),
			)
		);

		abcc_assert_true(
			false !== strpos( $prompt, 'Format requirements:' ),
			'Expected the canonical format requirements to be appended.'
		);
	}
);
