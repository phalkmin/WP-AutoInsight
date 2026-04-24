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
