<?php
/**
 * Regression tests for Topic Library CRUD and frequency math (v4.2 Unit D).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'create topic persists fields and seeds scheduling meta',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Weekly roundup',
				'prompt'    => 'Write a weekly news roundup about {keyword}.',
				'frequency' => 'weekly',
			)
		);

		abcc_assert_true( ! is_wp_error( $topic_id ), 'Valid args must create a topic.' );

		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( 'Weekly roundup', $topic['title'] );
		abcc_assert_same( 'weekly', $topic['frequency'] );
		abcc_assert_true( $topic['active'], 'New topics start active.' );
		abcc_assert_same( 0, $topic['last_run'] );
		abcc_assert_true( $topic['next_run'] > time(), 'next_run must be seeded in the future.' );
		abcc_assert_same( 0, $topic['consecutive_failures'] );
	}
);

abcc_test(
	'create topic rejects missing title, prompt, and bad frequency',
	function () {
		$no_title = abcc_create_topic(
			array(
				'title'     => '',
				'prompt'    => 'p',
				'frequency' => 'daily',
			)
		);
		abcc_assert_true( is_wp_error( $no_title ), 'Empty title must be rejected.' );

		$no_prompt = abcc_create_topic(
			array(
				'title'     => 't',
				'prompt'    => '',
				'frequency' => 'daily',
			)
		);
		abcc_assert_true( is_wp_error( $no_prompt ), 'Empty prompt must be rejected.' );

		$bad_frequency = abcc_create_topic(
			array(
				'title'     => 't',
				'prompt'    => 'p',
				'frequency' => 'fortnightly',
			)
		);
		abcc_assert_true( is_wp_error( $bad_frequency ), 'Unknown frequency must be rejected.' );
	}
);

abcc_test(
	'update topic changes provided fields only and recomputes next_run on frequency change',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Original',
				'prompt'    => 'Original prompt',
				'frequency' => 'daily',
			)
		);

		$old_next_run = abcc_get_topic( $topic_id )['next_run'];

		abcc_assert_true( true === abcc_update_topic( $topic_id, array( 'title' => 'Renamed' ) ) );
		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( 'Renamed', $topic['title'] );
		abcc_assert_same( 'Original prompt', $topic['prompt'], 'Prompt must be untouched by partial update.' );
		abcc_assert_same( $old_next_run, $topic['next_run'], 'next_run must not change when frequency is unchanged.' );

		abcc_assert_true( true === abcc_update_topic( $topic_id, array( 'frequency' => 'hourly' ) ) );
		$topic = abcc_get_topic( $topic_id );
		abcc_assert_same( 'hourly', $topic['frequency'] );
		abcc_assert_true( $topic['next_run'] < $old_next_run, 'Switching daily→hourly must pull next_run closer.' );

		$invalid = abcc_update_topic( $topic_id, array( 'post_status_override' => 'pending' ) );
		abcc_assert_true( is_wp_error( $invalid ), 'Invalid status override must be rejected.' );

		abcc_assert_true( is_wp_error( abcc_update_topic( 999999, array( 'title' => 'x' ) ) ), 'Unknown topic must error.' );
	}
);

abcc_test(
	'pause and resume flip active state; resume recomputes next_run and clears failures',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Pausable',
				'prompt'    => 'p',
				'frequency' => 'hourly',
			)
		);

		update_post_meta( $topic_id, '_abcc_topic_next_run', 1000 ); // Long overdue.
		update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 3 );

		abcc_assert_true( abcc_pause_topic( $topic_id ) );
		abcc_assert_false( abcc_get_topic( $topic_id )['active'] );

		abcc_assert_true( abcc_resume_topic( $topic_id ) );
		$topic = abcc_get_topic( $topic_id );
		abcc_assert_true( $topic['active'] );
		abcc_assert_true( $topic['next_run'] > time(), 'Resume must reschedule from now, not fire immediately.' );
		abcc_assert_same( 0, $topic['consecutive_failures'], 'Resume must reset the failure counter.' );
	}
);

abcc_test(
	'delete removes the topic without touching other posts',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Doomed',
				'prompt'    => 'p',
				'frequency' => 'daily',
			)
		);
		$post_id  = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Generated article',
			)
		);

		abcc_assert_true( abcc_delete_topic( $topic_id ) );
		abcc_assert_same( null, abcc_get_topic( $topic_id ) );
		abcc_assert_true( null !== get_post( $post_id ), 'Generated posts must survive topic deletion.' );

		abcc_assert_false( abcc_delete_topic( $post_id ), 'Non-topic posts must not be deletable through the topic API.' );
	}
);

abcc_test(
	'calculate_next_run adds the right interval for all five frequencies',
	function () {
		$from = 1750000000; // Fixed base for determinism.

		abcc_assert_same( $from + HOUR_IN_SECONDS, abcc_calculate_next_run( 'hourly', $from ) );
		abcc_assert_same( $from + 2 * HOUR_IN_SECONDS, abcc_calculate_next_run( 'every_2h', $from ) );
		abcc_assert_same( $from + 6 * HOUR_IN_SECONDS, abcc_calculate_next_run( 'every_6h', $from ) );
		abcc_assert_same( $from + DAY_IN_SECONDS, abcc_calculate_next_run( 'daily', $from ) );
		abcc_assert_same( $from + 7 * DAY_IN_SECONDS, abcc_calculate_next_run( 'weekly', $from ) );

		// Unknown frequency falls back to daily rather than breaking the schedule.
		abcc_assert_same( $from + DAY_IN_SECONDS, abcc_calculate_next_run( 'bogus', $from ) );

		// DST boundary (US spring-forward 2026-03-08): fixed-interval math is
		// timezone-agnostic — daily means +86400s, never 23/25 wall-clock hours.
		$before_dst = strtotime( '2026-03-08 01:00:00 UTC' );
		abcc_assert_same( $before_dst + DAY_IN_SECONDS, abcc_calculate_next_run( 'daily', $before_dst ) );
	}
);

abcc_test(
	'topic post-status override resolves through abcc_resolve_post_status',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'draft';

		$topic_id = abcc_create_topic(
			array(
				'title'                => 'Publisher',
				'prompt'               => 'p',
				'frequency'            => 'daily',
				'post_status_override' => 'publish',
			)
		);

		abcc_assert_same( 'publish', abcc_resolve_topic_post_status( $topic_id ) );

		abcc_update_topic( $topic_id, array( 'post_status_override' => '' ) );
		abcc_assert_same( 'draft', abcc_resolve_topic_post_status( $topic_id ), 'Empty override must inherit the global setting.' );
	}
);
