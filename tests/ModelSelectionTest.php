<?php
/**
 * Provider-qualified model selections (v4.6 §2.1).
 *
 * A saved selection names its provider as 'provider::model'. Legacy bare IDs
 * keep resolving, and catalog-style IDs are never routed by substring guess.
 */

abcc_test(
	'a qualified selection round-trips through build and parse',
	function () {
		$selection = abcc_build_model_selection( 'openai', 'gpt-5.4-mini' );
		abcc_assert_same( 'openai::gpt-5.4-mini', $selection );

		$parsed = abcc_parse_model_selection( $selection );
		abcc_assert_same( 'openai', $parsed['provider'] );
		abcc_assert_same( 'gpt-5.4-mini', $parsed['model'] );
		abcc_assert_true( $parsed['qualified'] );
		abcc_assert_true( $parsed['known'] );
		abcc_assert_same( 'gpt-5.4-mini', abcc_get_model_id_from_selection( $selection ) );
	}
);

abcc_test(
	'legacy bare IDs resolve by registry membership, then by the substring guess',
	function () {
		$listed = abcc_parse_model_selection( 'claude-sonnet-4-6' );
		abcc_assert_same( 'claude', $listed['provider'] );
		abcc_assert_true( $listed['known'] );
		abcc_assert_false( $listed['qualified'] );

		$guessed = abcc_parse_model_selection( 'claude-3-opus-20240229' );
		abcc_assert_same( 'claude', $guessed['provider'] );
		abcc_assert_false( $guessed['known'] );

		abcc_assert_same( 'openai', abcc_get_provider_for_model( 'gpt-4.1-mini' ) );
		abcc_assert_same( 'perplexity', abcc_get_provider_for_model( 'sonar-pro' ) );
		abcc_assert_same( 'openai', abcc_get_provider_for_model( 'o4-mini' ) );
	}
);

abcc_test(
	'catalog-style IDs are never routed to a native provider by substring',
	function () {
		$bare = abcc_parse_model_selection( 'anthropic/claude-sonnet-4.5' );
		abcc_assert_same( '', $bare['provider'] );

		// Qualified with a gateway that is not registered: no provider, never 'claude'.
		$gateway = abcc_parse_model_selection( 'somegateway::anthropic/claude-sonnet-4.5' );
		abcc_assert_same( '', $gateway['provider'] );
		abcc_assert_same( 'anthropic/claude-sonnet-4.5', $gateway['model'] );
		abcc_assert_true( $gateway['qualified'] );
		abcc_assert_false( abcc_is_valid_text_model( 'somegateway::anthropic/claude-sonnet-4.5' ) );

		// A registered gateway keeps the selection, even for a model it does not list.
		$openrouter = abcc_parse_model_selection( 'openrouter::anthropic/claude-sonnet-4.5' );
		abcc_assert_same( 'openrouter', $openrouter['provider'] );
		abcc_assert_false( $openrouter['known'] );
	}
);

abcc_test(
	'a qualified selection with an unlisted model keeps its provider but is not valid',
	function () {
		$parsed = abcc_parse_model_selection( 'openai::gpt-9-preview' );
		abcc_assert_same( 'openai', $parsed['provider'] );
		abcc_assert_false( $parsed['known'] );
		abcc_assert_false( abcc_is_valid_text_model( 'openai::gpt-9-preview' ) );

		// Image-only providers never resolve as a text provider.
		abcc_assert_same( '', abcc_parse_model_selection( 'stability::sd3' )['provider'] );
	}
);

abcc_test(
	'validity requires registry membership for bare and qualified forms alike',
	function () {
		abcc_assert_true( abcc_is_valid_text_model( 'gpt-5.4-mini' ) );
		abcc_assert_true( abcc_is_valid_text_model( 'openai::gpt-5.4-mini' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'claude::gpt-5.4-mini' ) );
		abcc_assert_false( abcc_is_valid_text_model( 'gpt-made-up' ) );
		abcc_assert_false( abcc_is_valid_text_model( '' ) );
	}
);

abcc_test(
	'normalization rewrites resolvable bare IDs and leaves unknown strings alone',
	function () {
		abcc_assert_same( 'openai::gpt-5.4-mini', abcc_normalize_model_selection( 'gpt-5.4-mini' ) );
		abcc_assert_same( 'openai::gpt-5.4-mini', abcc_normalize_model_selection( 'openai::gpt-5.4-mini' ) );
		abcc_assert_same( 'gemini::gemini-2.5-flash', abcc_normalize_model_selection( ' gemini-2.5-flash ' ) );
		abcc_assert_same( 'not-a-model', abcc_normalize_model_selection( 'not-a-model' ) );
		abcc_assert_same( '', abcc_normalize_model_selection( '' ) );
	}
);

abcc_test(
	'display names and definitions resolve through either form',
	function () {
		abcc_assert_same( 'GPT-5.4 mini', abcc_get_model_display_name( 'openai::gpt-5.4-mini' ) );
		abcc_assert_same( 'GPT-5.4 mini', abcc_get_model_display_name( 'gpt-5.4-mini' ) );
		abcc_assert_same( 'foo/bar', abcc_get_model_display_name( 'openrouter::foo/bar' ) );

		$definition = abcc_get_model_definition( 'claude::claude-sonnet-4-6' );
		abcc_assert_same( 'Claude Sonnet 4.6', $definition['name'] );
		abcc_assert_same( null, abcc_get_model_definition( 'openai::gpt-9-preview' ) );
	}
);

abcc_test(
	'the default model is qualified and the empty selection falls back to it',
	function () {
		abcc_assert_same( 'openai::gpt-5.4-mini', abcc_get_default_text_model() );
		abcc_assert_same( 'openai', abcc_get_provider_for_model( '' ) );
	}
);

abcc_test(
	'save points store the qualified form',
	function () {
		$payload = abcc_apply_composer_overrides( array( 'model' => 'openai::gpt-5.4-mini' ), array( 'model' => 'claude-sonnet-4-6' ) );
		abcc_assert_same( 'claude::claude-sonnet-4-6', $payload['model'] );

		$rejected = abcc_apply_composer_overrides( array( 'model' => 'openai::gpt-5.4-mini' ), array( 'model' => 'gpt-made-up' ) );
		abcc_assert_same( 'openai::gpt-5.4-mini', $rejected['model'] );

		$topic = abcc_validate_topic_args( array( 'provider_override' => 'sonar' ), true );
		abcc_assert_same( 'perplexity::sonar', $topic['provider_override'] );

		$bad = abcc_validate_topic_args( array( 'provider_override' => 'anthropic/claude-sonnet-4.5' ), true );
		abcc_assert_true( is_wp_error( $bad ) );
	}
);

abcc_test(
	'the first available selection is built from the grouped options',
	function () {
		$grouped = array(
			'gemini' => array(
				'group'   => 'Google Gemini Models',
				'options' => array(
					'gemini-2.5-flash-lite' => array( 'name' => 'Flash-Lite' ),
					'gemini-2.5-flash'      => array( 'name' => 'Flash' ),
				),
			),
		);
		abcc_assert_same( 'gemini::gemini-2.5-flash-lite', abcc_get_first_available_model_selection( $grouped ) );
		abcc_assert_same( '', abcc_get_first_available_model_selection( array() ) );
	}
);
