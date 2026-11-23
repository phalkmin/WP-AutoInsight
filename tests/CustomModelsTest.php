<?php
/**
 * User-added model IDs on native providers (v4.6 §4).
 */

abcc_test(
	'a custom model ID merges into the registry with its context window and max output',
	function () {
		abcc_assert_false( abcc_is_valid_text_model( 'openai::gpt-7-preview' ) );

		$added = abcc_add_custom_model(
			'openai',
			'gpt-7-preview',
			array(
				'name'           => 'GPT-7 Preview',
				'context_window' => 500000,
				'max_output'     => 2000,
			)
		);
		abcc_assert_true( true === $added );

		abcc_assert_true( abcc_is_valid_text_model( 'openai::gpt-7-preview' ) );
		abcc_assert_same( 'GPT-7 Preview', abcc_get_model_display_name( 'openai::gpt-7-preview' ) );
		abcc_assert_same( 500000, abcc_get_model_context_window( 'gpt-7-preview', 'openai' ) );
		abcc_assert_same( 2000, abcc_get_model_max_output( 'gpt-7-preview', 'openai' ) );

		// The declared ceiling shapes the request.
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Hi.' ) );
		abcc_call_provider_api(
			'openai',
			'openai::gpt-7-preview',
			'Prompt.',
			array(
				'api_key'    => 'k',
				'max_tokens' => 9000,
			)
		);
		$body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		abcc_assert_same( 'gpt-7-preview', $body['model'] );
		abcc_assert_same( 2000, isset( $body['max_completion_tokens'] ) ? $body['max_completion_tokens'] : $body['max_tokens'] );

		// No price is known for a typed ID.
		abcc_assert_same( null, abcc_estimate_post_cost( 'openai::gpt-7-preview', 900, array( 'seo' => false ) )['cost'] );

		abcc_assert_true( abcc_remove_custom_model( 'openai', 'gpt-7-preview' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'openai::gpt-7-preview' ) );
	}
);

abcc_test(
	'custom models never replace a built-in model, and bad IDs or providers are refused',
	function () {
		$builtin = abcc_get_model_definition( 'openai::gpt-5.4-mini' );

		$exists = abcc_add_custom_model( 'openai', 'gpt-5.4-mini', array( 'context_window' => 2048 ) );
		abcc_assert_same( 'abcc_custom_model_exists', $exists->get_error_code() );

		// A stored entry that shadows a built-in ID is ignored on merge.
		abcc_update_setting( 'abcc_custom_models', array( 'openai' => array( 'gpt-5.4-mini' => array( 'context_window' => 2048 ) ) ) );
		abcc_flush_provider_registry_cache();
		abcc_assert_same( $builtin['context_window'], abcc_get_model_definition( 'openai::gpt-5.4-mini' )['context_window'] );

		abcc_update_setting( 'abcc_custom_models', array() );
		abcc_flush_provider_registry_cache();

		abcc_assert_same( 'abcc_custom_model_id', abcc_add_custom_model( 'openai', 'bad id with spaces' )->get_error_code() );
		abcc_assert_same( 'abcc_custom_model_id', abcc_add_custom_model( 'openai', 'evil::model' )->get_error_code() );
		abcc_assert_same( 'abcc_custom_model_id', abcc_add_custom_model( 'openai', '<script>' )->get_error_code() );
		abcc_assert_same( 'abcc_custom_model_provider', abcc_add_custom_model( 'stability', 'sd-9' )->get_error_code() );
		abcc_assert_same( 'abcc_custom_model_provider', abcc_add_custom_model( 'nope', 'model-1' )->get_error_code() );
	}
);

abcc_test(
	'stored custom models are sanitized on read',
	function () {
		abcc_update_setting(
			'abcc_custom_models',
			array(
				'claude'    => array(
					'claude-next' => array(
						'name'           => ' Claude Next ',
						'context_window' => 5,
						'max_output'     => 999999999,
						'price_input'    => -3,
						'made_up_key'    => 'x',
					),
					'bad id'      => array(),
				),
				'<script>'  => array( 'm' => array() ),
				'stability' => 'not-an-array',
			)
		);

		$models = abcc_get_custom_models();
		abcc_assert_true( isset( $models['claude'] ) );
		abcc_assert_false( isset( $models['stability'] ), 'A provider entry that is not a list of models is dropped.' );
		abcc_assert_false( isset( $models['<script>'] ) );

		$next = $models['claude']['claude-next'];
		abcc_assert_same( 'Claude Next', $next['name'] );
		abcc_assert_same( 1024, $next['context_window'] );
		abcc_assert_same( 2000000, $next['max_output'] );
		abcc_assert_false( isset( $next['price_input'] ) );
		abcc_assert_false( isset( $next['made_up_key'] ) );
		abcc_assert_false( isset( $models['claude']['bad id'] ) );

		abcc_update_setting( 'abcc_custom_models', array() );
		abcc_flush_provider_registry_cache();
	}
);

abcc_test(
	'add and remove endpoints require the nonce and manage_options',
	function () {
		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$_POST                                  = array(
			'nonce'    => 'x',
			'provider' => 'gemini',
			'model_id' => 'gemini-9-flash',
		);
		abcc_handle_add_custom_model();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_false( abcc_is_valid_text_model( 'gemini::gemini-9-flash' ) );

		$GLOBALS['abcc_test_current_user_caps'] = array();
		$_POST                                  = array(
			'nonce'          => 'x',
			'provider'       => 'gemini',
			'model_id'       => 'gemini-9-flash',
			'name'           => 'Gemini 9 Flash',
			'context_window' => '1000000',
			'max_output'     => '65536',
		);
		abcc_handle_add_custom_model();
		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_same( 'gemini::gemini-9-flash', $GLOBALS['abcc_test_last_json']['data']['selection'] );
		abcc_assert_true( abcc_is_valid_text_model( 'gemini::gemini-9-flash' ) );

		$GLOBALS['abcc_test_current_user_caps'] = array( 'manage_options' => false );
		$_POST                                  = array(
			'nonce'    => 'x',
			'provider' => 'gemini',
			'model_id' => 'gemini-9-flash',
		);
		abcc_handle_remove_custom_model();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( abcc_is_valid_text_model( 'gemini::gemini-9-flash' ) );

		$GLOBALS['abcc_test_current_user_caps'] = array();
		abcc_handle_remove_custom_model();
		$_POST = array();
		abcc_assert_true( $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_false( abcc_is_valid_text_model( 'gemini::gemini-9-flash' ) );
	}
);
