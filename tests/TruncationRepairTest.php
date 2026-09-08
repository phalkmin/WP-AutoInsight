<?php
/**
 * Truncation detection and repair.
 */

abcc_test(
	'trimmer drops a dangling HTML section',
	function () {
		$lines = array(
			'<h2>First Section</h2>',
			'<p>Complete paragraph.</p>',
			'<h2>Second Section</h2>',
			'<p>This paragraph was cut off mid-sen',
		);

		$trimmed = abcc_trim_to_content_boundary( $lines );

		abcc_assert_same( 2, count( $trimmed ), 'Should keep only the complete first section.' );
		abcc_assert_same( '<p>Complete paragraph.</p>', end( $trimmed ), 'Should end on a closed block.' );
	}
);

abcc_test(
	'trimmer drops a dangling Markdown section',
	function () {
		$lines = array(
			'## First Section',
			'Complete paragraph.',
			'## Second Section',
			'This paragraph was cut off mid-sen',
		);

		$trimmed = abcc_trim_to_content_boundary( $lines );

		abcc_assert_same( 2, count( $trimmed ), 'Should keep only the complete first section.' );
		abcc_assert_same( 'Complete paragraph.', end( $trimmed ), 'Should end on the last complete paragraph.' );
	}
);

abcc_test(
	'trimmer leaves already-complete content untouched',
	function () {
		foreach (
			array(
				array( '<h2>Done</h2>', '<p>All complete.</p>' ),
				array( '## Done', 'All complete.' ),
			) as $lines
		) {
			abcc_assert_same(
				$lines,
				abcc_trim_to_content_boundary( $lines ),
				'Complete content must pass through unchanged.'
			);
		}
	}
);

abcc_test(
	'trimmer never returns an empty array when input has any content',
	function () {
		// A single truncated line with no boundary at all: keep it rather than
		// destroying the only content the user paid for.
		$lines   = array( 'One truncated line with no boundary' );
		$trimmed = abcc_trim_to_content_boundary( $lines );

		abcc_assert_true( count( $trimmed ) >= 1, 'Must not return nothing when input had content.' );
	}
);

abcc_test(
	'a truncated response is trimmed and given a conclusion',
	function () {
		$GLOBALS['abcc_test_locale'] = 'en_US';
		abcc_update_setting( 'abcc_content_language', 'site' );

		// The conclusion follow-up call.
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>In short, widgets matter.</p>' ) );

		$result = abcc_repair_truncated_content(
			array(
				'<h2>First</h2>',
				'<p>Complete.</p>',
				'<h2>Second</h2>',
				'<p>Cut off mid-sen',
			),
			array(
				'model'   => 'gpt-4.1-mini-2025-04-14',
				'api_key' => 'sk-test',
				'title'   => 'Widgets',
			)
		);

		abcc_assert_true( $result['repaired'], 'Repair should report success.' );

		$joined = implode( "\n", $result['lines'] );
		abcc_assert_false(
			false !== strpos( $joined, 'Cut off mid-sen' ),
			'The dangling fragment must be gone: ' . $joined
		);
		abcc_assert_true(
			false !== strpos( $joined, 'In short, widgets matter.' ),
			'The conclusion must be appended: ' . $joined
		);
	}
);

abcc_test(
	'a failed conclusion call still returns trimmed content',
	function () {
		// No canned response queued: the follow-up call errors.
		$result = abcc_repair_truncated_content(
			array( '<h2>First</h2>', '<p>Complete.</p>', '<p>Cut off mid-sen' ),
			array(
				'model'   => 'gpt-4.1-mini-2025-04-14',
				'api_key' => 'sk-test',
				'title'   => 'Widgets',
			)
		);

		$joined = implode( "\n", $result['lines'] );
		abcc_assert_false(
			false !== strpos( $joined, 'Cut off mid-sen' ),
			'Trimming must happen even when the conclusion call fails.'
		);
		abcc_assert_true(
			false !== strpos( $joined, 'Complete.' ),
			'Existing content must survive a failed conclusion call.'
		);
	}
);

abcc_test(
	'the conclusion request is made in the configured content language',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		abcc_update_setting( 'abcc_content_language', 'site' );

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Em resumo.</p>' ) );

		abcc_repair_truncated_content(
			array( '<h2>Um</h2>', '<p>Completo.</p>', '<p>Cortado no meio' ),
			array(
				'model'   => 'gpt-4.1-mini-2025-04-14',
				'api_key' => 'sk-test',
				'title'   => 'Engrenagens',
			)
		);

		$sent = wp_json_encode( json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true ) );
		abcc_assert_true(
			false !== strpos( $sent, 'Brazilian Portuguese' ),
			'The conclusion prompt must name the content language.'
		);
	}
);
