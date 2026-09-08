<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'schedule info exposes next group and model for admin screens',
	function () {
		$GLOBALS['abcc_test_schedule'] = array(
			'timestamp' => time() + HOUR_IN_SECONDS,
			'schedule'  => 'hourly',
		);
		$GLOBALS['abcc_test_options']['abcc_keyword_groups'] = array(
			array(
				'name'     => 'First Group',
				'keywords' => array( 'alpha' ),
			),
			array(
				'name'     => 'Second Group',
				'keywords' => array( 'beta' ),
			),
		);
		$GLOBALS['abcc_test_options']['abcc_last_group_index'] = 0;
		$GLOBALS['abcc_test_options']['prompt_select']         = 'gpt-4.1-mini-2025-04-14';

		$schedule = abcc_get_openai_event_schedule();

		abcc_assert_same( 'hourly', $schedule['schedule'] );
		abcc_assert_same( 'Second Group', $schedule['group_name'] );
		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', $schedule['model'] );
	}
);

abcc_test(
	'cron events are scheduled on activation, not on construction',
	function () {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-abcc-plugin.php' );

		preg_match( '/function __construct\(\).*?\n\t\}/s', $source, $ctor );
		abcc_assert_true( ! empty( $ctor[0] ), 'Could not locate the constructor.' );
		abcc_assert_false(
			false !== strpos( $ctor[0], 'wp_next_scheduled' ),
			'The constructor must not query cron schedules on every request.'
		);

		preg_match( '/function activate_plugin\(\).*?\n\t\}/s', $source, $activate );
		abcc_assert_true( ! empty( $activate[0] ), 'Could not locate activate_plugin().' );
		abcc_assert_true(
			false !== strpos( $activate[0], 'abcc_daily_provider_health_check' ),
			'activate_plugin() must schedule the health-check event.'
		);
		abcc_assert_true(
			false !== strpos( $activate[0], 'abcc_run_topic_schedules' ),
			'activate_plugin() must schedule the topic-schedule event.'
		);
	}
);
