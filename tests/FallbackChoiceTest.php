<?php
/**
 * The backup slot is offered beside the field and saved only when chosen.
 */

abcc_test(
	'a backup is suggested only while none is saved',
	function () {
		$choices = array(
			'claude' => array(
				'name'   => 'Claude',
				'models' => array(
					'claude-haiku-5-5'  => 'Claude Haiku 5.5',
					'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
				),
			),
		);

		abcc_assert_same( array(), abcc_get_fallback_chain(), 'Nothing is active before a save.' );

		$suggestion = abcc_get_fallback_suggestion( 'openai', $choices );
		abcc_assert_same( 'claude', $suggestion['provider'] );
		abcc_assert_true( isset( $choices['claude']['models'][ $suggestion['model'] ] ), 'The suggested model must be one the form lists.' );

		abcc_assert_same( array(), abcc_get_fallback_suggestion( 'openai', array() ), 'No second provider, nothing to offer.' );

		abcc_save_fallback_choice( 'claude', 'claude-haiku-5-5' );
		$chain = abcc_get_fallback_chain();
		abcc_assert_same( 'claude', $chain[0]['provider'] );
		abcc_assert_same( 'claude-haiku-5-5', $chain[0]['model'] );
		abcc_assert_same( array(), abcc_get_fallback_suggestion( 'openai', $choices ), 'A saved backup is not suggested over.' );
	}
);

abcc_test(
	'a suggested model the form does not list falls back to one it does',
	function () {
		$choices = array(
			'claude' => array(
				'name'   => 'Claude',
				'models' => array( 'my-custom-model' => 'My custom model' ),
			),
		);

		$suggestion = abcc_get_fallback_suggestion( 'openai', $choices );
		abcc_assert_same( 'my-custom-model', $suggestion['model'] );

		$choices['claude']['models'] = array();
		abcc_assert_same( array(), abcc_get_fallback_suggestion( 'openai', $choices ) );
	}
);

abcc_test(
	'saving "nothing" leaves no backup',
	function () {
		abcc_save_fallback_choice( '', '' );
		abcc_assert_same( array(), abcc_get_fallback_chain() );

		// A provider without a model is no backup either.
		abcc_save_fallback_choice( 'claude', '' );
		abcc_assert_same( array(), abcc_get_fallback_chain() );
	}
);

abcc_test(
	'next-step hints stop suggesting a backup once one is set',
	function () {
		foreach ( array( 'abcc_provider_rate_limited', 'abcc_provider_server_error', 'abcc_provider_payment_required' ) as $code ) {
			abcc_assert_true( false !== strpos( abcc_get_generation_error_next_step( $code ), 'fallback provider' ), $code );
		}

		// With a backup set the error message is the whole advice.
		abcc_save_fallback_choice( 'claude', 'claude-haiku-5-5' );
		foreach ( array( 'abcc_provider_rate_limited', 'abcc_provider_server_error', 'abcc_provider_payment_required' ) as $code ) {
			abcc_assert_same( '', abcc_get_generation_error_next_step( $code ), $code );
		}
	}
);

abcc_test(
	'a repeated job note is stored once',
	function () {
		$job = wp_insert_post(
			array(
				'post_type'   => ABCC_Job::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		abcc_append_job_log_note( $job, 'Custom endpoint failed (server error) → completed via Claude' );
		abcc_append_job_log_note( $job, 'Title chosen' );
		abcc_append_job_log_note( $job, 'Custom endpoint failed (server error) → completed via Claude' );
		abcc_assert_same( 2, count( get_post_meta( $job, '_abcc_job_notes', true ) ) );
	}
);
