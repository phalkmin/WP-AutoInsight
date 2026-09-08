<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'content filter excludes lines starting with <title>',
	function () {
		$result = abcc_filter_generated_content_lines(
			array(
				'<title>Generated Title</title>',
				'<p>Real paragraph.</p>',
			)
		);

		abcc_assert_equals( array( '<p>Real paragraph.</p>' ), $result );
	}
);

abcc_test(
	'content filter excludes [SEO] marker lines',
	function () {
		$result = abcc_filter_generated_content_lines(
			array(
				'<p>Intro paragraph.</p>',
				'[SEO] Meta Description: something',
				'<p>Closing paragraph.</p>',
			)
		);

		abcc_assert_equals(
			array( '<p>Intro paragraph.</p>', '<p>Closing paragraph.</p>' ),
			$result
		);
	}
);

abcc_test(
	'content filter excludes blank and whitespace-only lines',
	function () {
		$result = abcc_filter_generated_content_lines(
			array(
				'',
				"   \t  ",
				'<p>Kept paragraph.</p>',
			)
		);

		abcc_assert_equals( array( '<p>Kept paragraph.</p>' ), $result );
	}
);

abcc_test(
	'content filter throws when every line is filtered out',
	function () {
		$thrown  = false;
		$message = '';

		try {
			abcc_filter_generated_content_lines(
				array(
					'<title>Only Title</title>',
					'   ',
					'[SEO] Meta Description: nothing else',
				)
			);
		} catch ( Exception $e ) {
			$thrown  = true;
			$message = $e->getMessage();
		}

		abcc_assert_true( $thrown, 'Expected an Exception when every line is filtered out.' );
		abcc_assert_same( 'Content generation failed', $message );
	}
);

abcc_test(
	'heading block level is clamped to the valid HTML range',
	function () {
		foreach ( array( 0, -3, 7, 99 ) as $bad ) {
			$html = abcc_gutenberg_blocks(
				array(
					array(
						'name'       => 'heading',
						'attributes' => array( 'level' => $bad ),
						'content'    => 'Section',
					),
				)
			);

			abcc_assert_true(
				(bool) preg_match( '/<h([1-6]) /', $html ) || (bool) preg_match( '/<h([1-6])>/', $html ),
				'Level ' . $bad . ' should clamp into h1-h6, got: ' . $html
			);
		}
	}
);
