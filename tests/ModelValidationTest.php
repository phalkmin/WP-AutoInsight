<?php
/**
 * Per-call model overrides are checked against the registry (C-10).
 */

abcc_test(
	'registered text models validate, unknown and empty ones do not',
	function () {
		abcc_assert_true( abcc_is_valid_text_model( 'gpt-5.4-mini' ) );
		abcc_assert_true( abcc_is_valid_text_model( 'claude-sonnet-4-6' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'gpt-9-ultra' ), 'Unknown IDs fail even when they contain a provider word.' );
		abcc_assert_false( abcc_is_valid_text_model( '' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'stable-diffusion-xl' ), 'Image-only providers have no text models.' );
	}
);

abcc_test(
	'composer override with an unknown model falls back to the payload model',
	function () {
		$payload = abcc_apply_composer_overrides( array( 'model' => 'gpt-5.4-mini' ), array( 'model' => 'gpt-9-ultra' ) );
		abcc_assert_same( 'gpt-5.4-mini', $payload['model'] );

		$payload = abcc_apply_composer_overrides( array( 'model' => 'gpt-5.4-mini' ), array( 'model' => 'claude-sonnet-4-6' ) );
		abcc_assert_same( 'claude-sonnet-4-6', $payload['model'] );
	}
);

abcc_test(
	'topic save rejects an unknown model override',
	function () {
		$result = abcc_validate_topic_args(
			array(
				'title'             => 'T',
				'prompt'            => 'P',
				'frequency'         => 'daily',
				'provider_override' => 'gpt-9-ultra',
			)
		);
		abcc_assert_true( is_wp_error( $result ), 'Unknown model must be rejected.' );
		abcc_assert_same( 'abcc_topic_invalid_model', $result->get_error_code() );

		$ok = abcc_validate_topic_args(
			array(
				'title'             => 'T',
				'prompt'            => 'P',
				'frequency'         => 'daily',
				'provider_override' => '',
			)
		);
		abcc_assert_same( '', $ok['provider_override'], 'Empty override means global model.' );
	}
);
