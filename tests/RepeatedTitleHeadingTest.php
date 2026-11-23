<?php
/**
 * A body that opens with the post title as a heading loses that heading.
 */

abcc_test(
	'a leading heading that repeats the title is dropped',
	function () {
		$lines = array( '<h2>Grow Fresh Herbs in Small Balcony Spaces</h2>', '<p>Intro.</p>', '<h2>Soil</h2>' );
		abcc_assert_same(
			array( '<p>Intro.</p>', '<h2>Soil</h2>' ),
			abcc_drop_repeated_title_heading( $lines, 'Grow Fresh Herbs in Small Balcony Spaces' )
		);

		// Case, entities, inner tags and closing punctuation do not hide the repeat.
		abcc_assert_same(
			array( '<p>Intro.</p>' ),
			abcc_drop_repeated_title_heading( array( '<h1><strong>Tom &amp; Jerry: a guide!</strong></h1>', '<p>Intro.</p>' ), 'Tom & Jerry: A Guide' )
		);
	}
);

abcc_test(
	'other leading lines stay where they are',
	function () {
		$different = array( '<h2>Reviving an Inactive Culture</h2>', '<p>Text.</p>' );
		abcc_assert_same( $different, abcc_drop_repeated_title_heading( $different, 'Revive Dead Sourdough Starter Fast' ) );

		// A paragraph that happens to equal the title is prose, not a heading.
		$paragraph = array( '<p>Hello World</p>', '<p>Text.</p>' );
		abcc_assert_same( $paragraph, abcc_drop_repeated_title_heading( $paragraph, 'Hello World' ) );

		// Only the first line is checked.
		$later = array( '<p>Intro.</p>', '<h2>Hello World</h2>' );
		abcc_assert_same( $later, abcc_drop_repeated_title_heading( $later, 'Hello World' ) );

		abcc_assert_same( array(), abcc_drop_repeated_title_heading( array(), 'Hello World' ) );
		abcc_assert_same( $paragraph, abcc_drop_repeated_title_heading( $paragraph, '' ) );
	}
);
