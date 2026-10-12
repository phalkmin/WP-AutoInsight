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

abcc_test(
	'bulk single-generation error payload carries the machine code',
	function () {
		$GLOBALS['abcc_test_options']['prompt_select'] = 'gpt-5.4-mini';
		$GLOBALS['abcc_test_last_json']                = null;
		$GLOBALS['abcc_http_queue']                    = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$GLOBALS['abcc_http_queue'][] = array(
				'response' => array( 'code' => 401 ),
				'body'     => '{"error":{"message":"Incorrect API key"}}',
			);
		}
		$_POST = array(
			'keyword' => 'alpha',
			'draft'   => '1',
		);

		abcc_handle_bulk_generate_single();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'abcc_provider_auth_error', $GLOBALS['abcc_test_last_json']['data']['code'] );
		$_POST                      = array();
		$GLOBALS['abcc_http_queue'] = array();
	}
);
