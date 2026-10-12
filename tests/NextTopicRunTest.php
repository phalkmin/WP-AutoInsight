<?php
/**
 * The dashboard reflects Topics, not only the legacy scheduler (P2-12, P1-15).
 */

abcc_test(
	'next topic run picks the earliest active topic and ignores paused ones',
	function () {
		$soon   = abcc_create_topic(
			array(
				'title'     => 'Soon',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);
		$later  = abcc_create_topic(
			array(
				'title'     => 'Later',
				'prompt'    => 'P',
				'frequency' => 'weekly',
			)
		);
		$paused = abcc_create_topic(
			array(
				'title'     => 'Paused',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);
		update_post_meta( $paused, '_abcc_topic_next_run', 1 );
		abcc_pause_topic( $paused );

		$next = abcc_get_next_topic_run();
		abcc_assert_same( $soon, $next['topic_id'] );
		abcc_assert_same( 'Soon', $next['title'] );
		abcc_assert_true( $next['next_run'] > time() );
	}
);

function abcc_test_delete_all_topics() {
	foreach ( $GLOBALS['abcc_test_posts'] as $id => $post ) {
		if ( ABCC_TOPIC_POST_TYPE === $post->post_type ) {
			wp_delete_post( $id, true );
		}
	}
}

abcc_test(
	'next topic run is null with no active topics',
	function () {
		// Posts persist across tests; earlier files may have created topics.
		abcc_test_delete_all_topics();
		abcc_assert_same( null, abcc_get_next_topic_run() );
	}
);

abcc_test(
	'an overdue topic is reported as overdue, not as a future run',
	function () {
		abcc_test_delete_all_topics();
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Late',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);
		update_post_meta( $topic_id, '_abcc_topic_next_run', time() - 3 * HOUR_IN_SECONDS );

		$html = abcc_test_render_dashboard();

		abcc_assert_true( false !== strpos( $html, 'Topic run overdue by' ), 'Overdue copy expected.' );
		abcc_assert_true( false !== strpos( $html, 'WP-Cron has not fired' ) );
		abcc_assert_false( false !== strpos( $html, 'Next topic run' ), 'A past run is not upcoming.' );
		abcc_test_delete_all_topics();
	}
);

abcc_test(
	'a topic due 20 minutes ago is due now, not overdue',
	function () {
		abcc_test_delete_all_topics();
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Soon',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);
		update_post_meta( $topic_id, '_abcc_topic_next_run', time() - 20 * MINUTE_IN_SECONDS );

		$html = abcc_test_render_dashboard();

		abcc_assert_true( false !== strpos( $html, 'Topic run due now' ), 'Within one sweep cycle the topic is due, not overdue.' );
		abcc_assert_false( false !== strpos( $html, 'overdue' ), 'No cron alarm inside the sweep window.' );
		abcc_assert_false( abcc_topic_run_is_overdue( time() - HOUR_IN_SECONDS ) );
		abcc_assert_true( abcc_topic_run_is_overdue( time() - 2 * HOUR_IN_SECONDS ) );
		abcc_test_delete_all_topics();
	}
);

abcc_test(
	'with site AI off the card says topics are paused instead of blaming WP-Cron',
	function () {
		abcc_test_delete_all_topics();
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Paused',
				'prompt'    => 'P',
				'frequency' => 'hourly',
			)
		);
		update_post_meta( $topic_id, '_abcc_topic_next_run', time() - 3 * HOUR_IN_SECONDS );
		$GLOBALS['abcc_test_ai_supported'] = false;

		$html = abcc_test_render_dashboard();

		$GLOBALS['abcc_test_ai_supported'] = true;
		abcc_assert_true( false !== strpos( $html, 'turned off for this site' ), 'AI-off copy expected.' );
		abcc_assert_false( false !== strpos( $html, 'WP-Cron has not fired' ) );
		abcc_test_delete_all_topics();
	}
);
