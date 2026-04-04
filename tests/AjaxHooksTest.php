<?php

abcc_test(
	'ajax handlers remain registered on the expected hooks',
	function () {
		$GLOBALS['abcc_test_actions'] = array();
		require dirname(__DIR__) . '/includes/ajax-handlers.php';

		abcc_assert_array_has_key( 'wp_ajax_abcc_create_post', $GLOBALS['abcc_test_actions'] );
		abcc_assert_array_has_key( 'wp_ajax_abcc_validate_api_key', $GLOBALS['abcc_test_actions'] );
		abcc_assert_array_has_key( 'wp_ajax_abcc_bulk_generate_single', $GLOBALS['abcc_test_actions'] );
		abcc_assert_array_has_key( 'wp_ajax_abcc_regenerate_post', $GLOBALS['abcc_test_actions'] );
		abcc_assert_array_has_key( 'wp_ajax_abcc_get_job_log', $GLOBALS['abcc_test_actions'] );
	}
);
