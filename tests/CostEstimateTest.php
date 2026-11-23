<?php
/**
 * Pre-run cost estimate (v4.6 §5.3): what it includes, unknown pricing, formatting.
 */

abcc_test(
	'estimate prices the title call and the body from registry prices and says what it covers',
	function () {
		$estimate = abcc_estimate_post_cost(
			'openai::gpt-5.4-mini',
			900,
			array(
				'seo'           => false,
				'locale'        => 'en_US',
				'prompt_tokens' => 400,
			)
		);

		$definition = abcc_get_model_definition( 'openai::gpt-5.4-mini' );
		$input      = 400 + 130;
		$output     = (int) ceil( 900 * 1.35 ) + 25;
		$expected   = round( ( $input * $definition['price_input'] + $output * $definition['price_output'] ) / 1000000, 6 );

		abcc_assert_same( $expected, $estimate['cost'] );
		abcc_assert_same( 900, $estimate['words'] );
		abcc_assert_same( 'USD', $estimate['currency'] );
		abcc_assert_same( array( 'title', 'body' ), $estimate['includes'] );
		abcc_assert_same( array( 'image', 'repair', 'fallback' ), $estimate['excludes'] );
		abcc_assert_true( $estimate['approximate'] );
		abcc_assert_same( '2026-11-05', $estimate['pricing_date'] );
		abcc_assert_same( 'GPT-5.4 mini', $estimate['model_name'] );
	}
);

abcc_test(
	'SEO adds to the title call and a reasoning model costs more for the same words',
	function () {
		$flags = array(
			'locale'        => 'en_US',
			'prompt_tokens' => 400,
		);

		$plain = abcc_estimate_post_cost( 'openai::gpt-5.4-mini', 900, $flags + array( 'seo' => false ) );
		$seo   = abcc_estimate_post_cost( 'openai::gpt-5.4-mini', 900, $flags + array( 'seo' => true ) );
		abcc_assert_true( $seo['cost'] > $plain['cost'] );
		abcc_assert_same( array( 'title_seo', 'body' ), $seo['includes'] );

		// Same prices would still differ: hidden reasoning is billed as output.
		$sol        = abcc_estimate_post_cost( 'openai::gpt-6.1-sol', 900, $flags + array( 'seo' => false ) );
		$definition = abcc_get_model_definition( 'openai::gpt-6.1-sol' );
		$output     = (int) ceil( 900 * 1.35 ) + 25 + 2000;
		abcc_assert_same( round( ( 530 * $definition['price_input'] + $output * $definition['price_output'] ) / 1000000, 6 ), $sol['cost'] );
	}
);

abcc_test(
	'a longer post and a costlier language both raise the estimate',
	function () {
		$short = abcc_estimate_post_cost( 'claude::claude-haiku-5-5', 150, array( 'seo' => false ) );
		$long  = abcc_estimate_post_cost( 'claude::claude-haiku-5-5', 900, array( 'seo' => false ) );
		abcc_assert_true( $long['cost'] > $short['cost'] );

		$en = abcc_estimate_post_cost(
			'claude::claude-haiku-5-5',
			900,
			array(
				'seo'    => false,
				'locale' => 'en_US',
			)
		);
		$pt = abcc_estimate_post_cost(
			'claude::claude-haiku-5-5',
			900,
			array(
				'seo'    => false,
				'locale' => 'pt_BR',
			)
		);
		abcc_assert_true( $pt['cost'] > $en['cost'] );
	}
);

abcc_test(
	'unknown pricing is null and reads "cost unknown", never $0',
	function () {
		// sonar-pro carries no prices in the registry.
		$estimate = abcc_estimate_post_cost( 'perplexity::sonar-pro', 900, array( 'seo' => false ) );
		abcc_assert_same( null, $estimate['cost'] );

		$text = abcc_format_post_cost_estimate( $estimate );
		abcc_assert_true( false !== strpos( $text['summary'], 'cost unknown' ) );
		abcc_assert_false( false !== strpos( $text['summary'], '$' ) );
		abcc_assert_true( false !== strpos( $text['detail'], 'No price is known' ) );

		// A model nobody registered.
		$unknown = abcc_estimate_post_cost( 'made-up-model', 900, array( 'seo' => false ) );
		abcc_assert_same( null, $unknown['cost'] );
	}
);

abcc_test(
	'estimate text names words, cost, model, what is included and the pricing date',
	function () {
		$text = abcc_format_post_cost_estimate(
			abcc_estimate_post_cost(
				'openai::gpt-5.4-mini',
				900,
				array(
					'seo'    => true,
					'locale' => 'en_US',
				)
			)
		);

		abcc_assert_true( 1 === preg_match( '/^About 900 words · ≈\$0\.\d{4} per post with GPT-5\.4 mini$/u', $text['summary'] ), $text['summary'] );
		abcc_assert_true( 1 === preg_match( '/^≈\$0\.\d{4} per post with GPT-5\.4 mini$/u', $text['cost'] ) );
		abcc_assert_true( false !== strpos( $text['detail'], 'Estimate for the title, SEO fields and article text.' ) );
		abcc_assert_true( false !== strpos( $text['detail'], 'Images and retries cost extra.' ) );
		abcc_assert_true( false !== strpos( $text['detail'], 'Prices checked 2026-11-05.' ) );
	}
);

abcc_test(
	'a retired selection is priced as its successor',
	function () {
		$retired   = abcc_estimate_post_cost( 'gemini::gemini-3.5-flash', 900, array( 'seo' => false ) );
		$successor = abcc_estimate_post_cost( 'gemini::gemini-3.6-flash', 900, array( 'seo' => false ) );

		abcc_assert_same( 'gemini-3.6-flash', $retired['model'] );
		abcc_assert_same( $successor['cost'], $retired['cost'] );
	}
);

abcc_test(
	'estimate endpoint checks nonce and capability, validates the model and clamps the length',
	function () {
		abcc_update_setting( 'openai_api_key', 'sk-test' );
		abcc_update_setting( 'prompt_select', 'openai::gpt-5.4-mini' );

		$GLOBALS['abcc_test_current_user_caps']  = array( 'prompt_ai' => false );
		$GLOBALS['abcc_test_current_user_roles'] = array( 'subscriber' );
		$_POST                                   = array(
			'nonce'      => 'x',
			'char_limit' => '1200',
		);
		abcc_handle_estimate_cost();
		abcc_assert_false( $GLOBALS['abcc_test_last_json']['success'], 'A user who cannot prompt gets no estimate.' );

		$GLOBALS['abcc_test_current_user_caps']  = array();
		$GLOBALS['abcc_test_current_user_roles'] = array( 'administrator' );
		$_POST                                   = array(
			'nonce'      => 'x',
			'char_limit' => '999999',
			'model'      => 'made-up::model',
		);
		abcc_handle_estimate_cost();
		$_POST = array();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_true( $json['success'] );
		abcc_assert_same( 3000, $json['data']['words'], 'Length is clamped to the slider range.' );
		abcc_assert_true( false !== strpos( $json['data']['summary'], 'GPT-5.4 mini' ), 'An invalid model falls back to the global one.' );
		abcc_assert_true( '' !== $json['data']['detail'] );
	}
);

abcc_test(
	'a free model reads "free", not a row of zeros',
	function () {
		$text = abcc_format_post_cost_estimate(
			array(
				'words'        => 225,
				'cost'         => 0.0,
				'includes'     => array( 'title', 'body' ),
				'pricing_date' => '2026-11-13',
				'model'        => 'google/gemma-4-31b-it:free',
				'model_name'   => 'Google: Gemma 4 31B (free)',
				'notes'        => array(),
			)
		);
		abcc_assert_same( 'About 225 words · free with Google: Gemma 4 31B', $text['summary'] );
		abcc_assert_same( 'free with Google: Gemma 4 31B', $text['cost'] );
		abcc_assert_false( false !== strpos( $text['summary'], '$' ) );
	}
);
