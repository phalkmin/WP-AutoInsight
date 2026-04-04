<?php

abcc_test(
	'generation payload and tracking meta keep the normalized shape',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'    => 'gemini-2.5-flash',
			'openai_tone'      => 'professional',
			'openai_char_limit'=> 333,
		);

		$payload = abcc_build_generation_payload(
			array(
				'keywords'  => array( 'alpha', 'beta' ),
				'post_type' => 'post',
				'category'  => 5,
				'template'  => 'default',
				'source'    => 'bulk',
			)
		);

		$meta   = abcc_build_generation_tracking_meta( $payload );
		$params = json_decode( $meta['_abcc_generation_params'], true );

		abcc_assert_same( 'gemini-2.5-flash', $payload['model'] );
		abcc_assert_same( 'professional', $payload['tone'] );
		abcc_assert_same( 333, $payload['char_limit'] );
		abcc_assert_same( 'bulk', $params['source'] );
		abcc_assert_same( 5, $params['category'] );
		abcc_assert_equals( array( 'alpha', 'beta' ), $params['keywords'] );
	}
);
