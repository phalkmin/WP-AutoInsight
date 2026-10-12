<?php
/**
 * Infographics attach without a loopback request and never replace the featured image (C-06).
 */

require_once dirname( __DIR__ ) . '/includes/infographic.php';

abcc_test(
	'a generated infographic in uploads is attached from disk, titled, and leaves the thumbnail alone',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Hello World Guide',
			)
		);
		update_post_meta( $post_id, '_thumbnail_id', 55 );

		$uploads = wp_upload_dir();
		wp_mkdir_p( $uploads['basedir'] );
		file_put_contents( $uploads['basedir'] . '/openai-info.png', 'png' );

		$attachment_id = abcc_attach_infographic_to_post( $post_id, $uploads['baseurl'] . '/openai-info.png', 'Infographic for: Hello World Guide' );

		abcc_assert_true( ! is_wp_error( $attachment_id ) && $attachment_id > 0 );
		abcc_assert_same( 55, (int) get_post_meta( $post_id, '_thumbnail_id', true ) );
		abcc_assert_same( 'Infographic for: Hello World Guide', get_the_title( $attachment_id ) );
		abcc_assert_same( true, (bool) get_post_meta( $attachment_id, '_abcc_infographic', true ) );
		abcc_assert_same( $post_id, (int) get_post_meta( $attachment_id, '_abcc_infographic_post_id', true ) );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'No loopback HTTP request.' );

		unlink( $uploads['basedir'] . '/openai-info.png' );
	}
);

abcc_test(
	'a missing local file returns an error instead of a silent false',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'x',
			)
		);
		$uploads = wp_upload_dir();
		$result  = abcc_attach_infographic_to_post( $post_id, $uploads['baseurl'] . '/nope.png', 'T' );
		abcc_assert_true( is_wp_error( $result ) );
	}
);

abcc_test(
	'infographic handler surfaces the provider error for the description call',
	function () {
		$post_id                                        = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'T',
				'post_content' => '<p>body</p>',
			)
		);
		$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk';
		$GLOBALS['abcc_test_options']['prompt_select']  = 'gpt-5.4-mini';
		$GLOBALS['abcc_test_last_json']                 = null;
		$GLOBALS['abcc_http_queue'][]                   = array(
			'response' => array( 'code' => 401 ),
			'body'     => wp_json_encode( array( 'error' => array( 'message' => 'bad key' ) ) ),
		);
		$_POST = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
		);

		abcc_handle_create_infographic();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], 'rejected the API key' ), $GLOBALS['abcc_test_last_json']['data']['message'] );
		$_POST = array();
	}
);
