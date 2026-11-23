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

abcc_test(
	'topic list renders next and last run in site-local time',
	function () {
		if ( ! class_exists( 'WP_List_Table' ) ) {
			class WP_List_Table {
				public function __construct( $args = array() ) {}
				public function row_actions( $actions ) {
					return '';
				}
				protected function handle_row_actions( $item, $column_name, $primary ) {
					return '';
				}
			}
		}
		require_once dirname( __DIR__ ) . '/includes/class-abcc-topics-list-table.php';

		$table = new ABCC_Topics_List_Table();
		$ts    = 1700000000; // 2023-11-14 22:13 UTC.
		$row   = array(
			'active'      => true,
			'next_run'    => $ts,
			'last_run'    => $ts,
			'last_job_id' => 0,
		);
		abcc_assert_same( gmdate( 'Y-m-d H:i', $ts + 3 * HOUR_IN_SECONDS ), $table->column_next_run( $row ) );
		abcc_assert_same( gmdate( 'Y-m-d H:i', $ts + 3 * HOUR_IN_SECONDS ), $table->column_last_run( $row ) );
	}
);

abcc_test(
	'the Topics table names the model each topic will use',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		abcc_assert_same( 'GPT-5.4 mini (global)', abcc_get_topic_model_label( array( 'provider_override' => '' ) ) );
		abcc_assert_same( 'Claude Haiku 5.5', abcc_get_topic_model_label( array( 'provider_override' => 'claude::claude-haiku-5-5' ) ) );

		// An override that names no known model is skipped at run time, so the global model is shown.
		abcc_assert_same( 'GPT-5.4 mini (global)', abcc_get_topic_model_label( array( 'provider_override' => 'nowhere::no-such-model' ) ) );

		abcc_assert_same( array( 'abcc_topic_notice' ), abcc_register_topic_notice_query_arg( array() ) );
	}
);

abcc_test(
	'the log names the topic instead of a content template for a topic run',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		$topic_id = abcc_create_topic(
			array(
				'title'     => 'Home coffee brewing',
				'prompt'    => 'Write about one brewing method.',
				'frequency' => 'weekly',
			)
		);
		abcc_assert_false( is_wp_error( $topic_id ) );

		$job_id = abcc_queue_topic_generation( $topic_id );
		abcc_assert_false( is_wp_error( $job_id ) );

		abcc_assert_same( 'Topic: Home coffee brewing', abcc_get_job_template_label( $job_id, 'default' ) );

		// Regenerating a topic's post keeps the topic in its payload under another source.
		$regen_id = abcc_queue_generation_job(
			abcc_build_generation_payload(
				array(
					'keywords' => array( 'coffee' ),
					'topic_id' => $topic_id,
					'prompt'   => 'Write about one brewing method.',
					'source'   => 'regenerate',
				)
			)
		);
		abcc_assert_same( 'Topic: Home coffee brewing', abcc_get_job_template_label( $regen_id, 'default' ) );

		// A job whose topic is gone still says it ran from a topic prompt.
		$orphan_id = abcc_queue_generation_job(
			abcc_build_generation_payload(
				array(
					'keywords' => array( 'coffee' ),
					'topic_id' => 987654321,
					'source'   => 'topic',
				)
			)
		);
		abcc_assert_same( 'Topic prompt', abcc_get_job_template_label( $orphan_id, 'default' ) );

		// A job without a topic keeps its template name.
		$plain_id = abcc_queue_generation_job( abcc_build_generation_payload( array( 'keywords' => array( 'coffee' ) ) ) );
		abcc_assert_same( abcc_get_content_template_name( 'default' ), abcc_get_job_template_label( $plain_id, '' ) );
	}
);
