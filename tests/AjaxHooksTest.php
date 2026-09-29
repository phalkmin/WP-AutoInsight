<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'ajax handlers remain registered on the expected hooks',
	function () {
		// ajax-handlers.php and audio.php register at file scope during bootstrap
		// load; assert against the load-time snapshot (re-requiring would fatal).
		$hooks = $GLOBALS['abcc_test_actions_at_load'];

		abcc_assert_array_has_key( 'wp_ajax_abcc_create_post', $hooks );
		abcc_assert_array_has_key( 'wp_ajax_abcc_validate_api_key', $hooks );
		abcc_assert_array_has_key( 'wp_ajax_abcc_bulk_generate_single', $hooks );
		abcc_assert_array_has_key( 'wp_ajax_abcc_regenerate_post', $hooks );
		abcc_assert_array_has_key( 'wp_ajax_abcc_get_job_log', $hooks );
		abcc_assert_array_has_key( 'wp_ajax_abcc_autosave_setting', $hooks, 'wp_ajax_abcc_autosave_setting must be registered in ajax-handlers.php' );
		abcc_assert_array_has_key( 'wp_ajax_abcc_audio_generate_post', $hooks, 'audio generate-post handler must be registered in audio.php' );
	}
);
