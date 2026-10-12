<?php
/**
 * Image failures carry the provider's reason to the editor (P3-07).
 */

abcc_test(
	'last image error is recorded by provider failures and reset per attempt',
	function () {
		abcc_set_last_image_error( 'stale' );
		$GLOBALS['abcc_test_options']['preferred_image_service'] = 'stability';
		$GLOBALS['abcc_test_options']['stability_api_key']       = 'sk';
		$GLOBALS['abcc_http_queue'][]                            = array(
			'response' => array( 'code' => 400 ),
			'body'     => wp_json_encode( array( 'message' => 'Invalid prompts detected' ) ),
		);

		$url = abcc_generate_featured_image( 'gpt-5.4-mini', array( 'anime' ), array() );

		abcc_assert_same( false, $url );
		abcc_assert_true( false !== strpos( abcc_get_last_image_error(), 'Invalid prompts detected' ), 'Got: ' . abcc_get_last_image_error() );
	}
);

abcc_test(
	'the failure notice renders without the core notice class so the editor does not hide it',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'x',
			)
		);
		abcc_record_image_generation_attempt( $post_id, false, 'Safety system rejected the prompt' );
		$html = abcc_get_image_failure_notice_html( $post_id );

		abcc_assert_true( false !== strpos( $html, 'abcc-image-failure' ) );
		abcc_assert_false( 1 === preg_match( '/class="([^"]*\s)?notice(\s[^"]*)?"/', $html ), 'No .notice class: core moves and hides those in the block editor.' );
		abcc_assert_true( false !== strpos( $html, 'abcc-retry-image' ) );
	}
);

abcc_test(
	'the image failure notice is not hooked to admin_notices anymore',
	function () {
		abcc_assert_false( function_exists( 'abcc_show_image_failure_notice' ), 'The admin_notices renderer must be removed; the meta box renders the notice.' );
	}
);

abcc_test(
	'a failed Stability fallback keeps the OpenAI reason and appends its own',
	function () {
		$GLOBALS['abcc_test_options']['preferred_image_service'] = 'openai';
		$GLOBALS['abcc_test_options']['openai_api_key']          = 'sk-openai';
		$GLOBALS['abcc_test_options']['stability_api_key']       = 'sk-stab';
		$GLOBALS['abcc_http_queue'][]                            = array(
			'response' => array( 'code' => 400 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Your request was rejected by the safety system' ) ) ),
		);
		$GLOBALS['abcc_http_queue'][]                            = array(
			'response' => array( 'code' => 400 ),
			'body'     => wp_json_encode( array( 'message' => 'Invalid prompts detected' ) ),
		);

		$url    = abcc_generate_featured_image( 'gpt-5.4-mini', array( 'anime' ), array() );
		$reason = abcc_get_last_image_error();

		abcc_assert_same( false, $url );
		abcc_assert_true( 0 === strpos( $reason, 'OpenAI: Your request was rejected by the safety system. ' ), 'OpenAI reason kept: ' . $reason );
		abcc_assert_true( false !== strpos( $reason, 'Stability AI' ) && false !== strpos( $reason, 'Invalid prompts detected' ), 'Stability reason appended: ' . $reason );
	}
);
