<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

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
		abcc_assert_array_has_key( 'wp_ajax_abcc_autosave_setting', $GLOBALS['abcc_test_actions'], 'wp_ajax_abcc_autosave_setting must be registered in ajax-handlers.php' );
		// audio.php registers this at file scope during bootstrap load; assert
		// against the load-time snapshot (re-requiring audio.php would fatal).
		abcc_assert_array_has_key( 'wp_ajax_abcc_audio_generate_post', $GLOBALS['abcc_test_actions_at_load'], 'audio generate-post handler must be registered in audio.php' );
	}
);
