<?php
/**
 * Onboarding page render contract: step 1 visible without JS, notices kept
 * out of the header, configured providers passed as IDs only.
 */

/**
 * Render the onboarding page and return its markup.
 *
 * @return string
 */
function abcc_test_render_onboarding() {
	ob_start();
	abcc_show_onboarding_page();
	return ob_get_clean();
}

abcc_test(
	'onboarding renders step 1 visible and every later step hidden',
	function () {
		$html = abcc_test_render_onboarding();

		// .abcc-onboarding-step is display:none in CSS unless .active.
		abcc_assert_true(
			1 === preg_match( '/class="abcc-onboarding-step abcc-step-1 active"/', $html ),
			'Step 1 must carry .active, or new installs see an empty wizard.'
		);
		abcc_assert_same(
			1,
			preg_match_all( '/class="abcc-onboarding-step[^"]*\bactive\b/', $html ),
			'Exactly one step may be active on first render.'
		);

		foreach ( array( 'abcc-step-2', 'abcc-step-3', 'abcc-step-4', 'abcc-step-success' ) as $step ) {
			abcc_assert_true(
				1 === preg_match( '/class="abcc-onboarding-step ' . $step . '"[^>]*style="display:\s*none;?"/', $html ),
				$step . ' must start hidden.'
			);
		}

		abcc_assert_true( false !== strpos( $html, 'id="abcc-next-step-2"' ), 'Step 1 Continue button must render.' );
		abcc_assert_true( false !== strpos( $html, 'id="abcc-onboarding-finish"' ), 'Finish button must render.' );
	}
);

abcc_test(
	'onboarding places the wp-header-end marker before the header h1',
	function () {
		$html = abcc_test_render_onboarding();

		$marker = strpos( $html, 'class="wp-header-end"' );
		$h1     = strpos( $html, '<h1>' );

		abcc_assert_true( false !== $marker, 'The wp-header-end marker must render.' );
		abcc_assert_true(
			$marker < $h1,
			'The marker must come before the header h1 so core moves notices out of the white-text header.'
		);
	}
);

abcc_test(
	'onboarding exposes configured provider IDs but never key values',
	function () {
		abcc_update_setting( 'claude_api_key', 'sk-ant-secret-value' );

		$html = abcc_test_render_onboarding();
		$data = $GLOBALS['abcc_test_localized']['abccOnboarding'] ?? array();

		abcc_assert_true( in_array( 'claude', $data['configuredProviders'] ?? array(), true ), 'A saved Claude key must unlock Continue on restart.' );
		abcc_assert_true( in_array( 'openai', $data['configuredProviders'] ?? array(), true ), 'A wp-config key counts as configured.' );
		abcc_assert_false( in_array( 'gemini', $data['configuredProviders'] ?? array(), true ), 'Providers without keys must not be marked configured.' );

		$payload = wp_json_encode( $data ) . $html;
		abcc_assert_false( false !== strpos( $payload, 'sk-ant-secret-value' ), 'Saved key values must never reach the page.' );
		abcc_assert_false( false !== strpos( $payload, 'const-openai-key' ), 'wp-config key values must never reach the page.' );
	}
);

/**
 * Queue a successful Gemini connection-test response.
 *
 * @return void
 */
function abcc_test_queue_gemini_ok() {
	$GLOBALS['abcc_http_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'Hi' ) ) ) ) ) )
		),
	);
}

abcc_test(
	'onboarding connection test keeps a model the site can already use',
	function () {
		abcc_update_setting( 'claude_api_key', 'sk-ant-existing' );
		abcc_update_setting( 'prompt_select', 'claude-sonnet-4-6' );

		abcc_test_queue_gemini_ok();
		$_POST = array(
			'nonce'    => 'x',
			'provider' => 'gemini',
			'api_key'  => 'gemini-new-key',
		);
		abcc_handle_onboarding_test_api();
		$_POST = array();

		abcc_assert_true( ! empty( $GLOBALS['abcc_test_last_json']['success'] ), 'Precondition: the connection test succeeds.' );
		abcc_assert_same( 'claude-sonnet-4-6', abcc_get_setting( 'prompt_select' ), 'Restart must not overwrite a usable model choice.' );
	}
);

abcc_test(
	'onboarding connection test switches to the new provider when the current model has no key',
	function () {
		abcc_update_setting( 'prompt_select', 'claude-sonnet-4-6' ); // No Claude key saved.

		abcc_test_queue_gemini_ok();
		$_POST = array(
			'nonce'    => 'x',
			'provider' => 'gemini',
			'api_key'  => 'gemini-new-key',
		);
		abcc_handle_onboarding_test_api();
		$_POST = array();

		abcc_assert_same(
			abcc_get_provider_default_model( 'gemini' ),
			abcc_get_setting( 'prompt_select' ),
			'A fresh install connecting Gemini must get a Gemini model.'
		);
	}
);

abcc_test(
	'wizard step 2 preselects the saved default post status',
	function () {
		$GLOBALS['abcc_test_options']['abcc_default_post_status'] = 'publish';

		$html = abcc_test_render_onboarding();

		abcc_assert_true(
			1 === preg_match( '/name="abcc_onboarding_status" value="publish"\s+checked/', $html ),
			'Publish must be preselected when it is the saved default.'
		);
		abcc_assert_false(
			1 === preg_match( '/name="abcc_onboarding_status" value="draft"\s+checked/', $html ),
			'Draft must not be checked when Publish is saved.'
		);
	}
);

abcc_test(
	'wizard skip buttons say Skip setup and the onboarding CSS styles step 2',
	function () {
		$html = abcc_test_render_onboarding();
		abcc_assert_false( false !== strpos( $html, 'Skip to settings' ), 'The skip lands on the Dashboard; the label must not promise Settings.' );
		abcc_assert_true( false !== strpos( $html, 'Skip setup' ) );

		$css = file_get_contents( dirname( __DIR__ ) . '/css/onboarding.css' );
		abcc_assert_true( false !== strpos( $css, '.abcc-onboarding-radio' ), 'Step 2 radios need styles.' );
		abcc_assert_true( false !== strpos( $css, '.abcc-onboarding-language' ) );
	}
);

abcc_test(
	'wizard finish and skip report failures',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/onboarding.js' );
		abcc_assert_same( 0, preg_match( '/function handleSkip[\s\S]*?success:\s*function \(response\) \{\s*if \(response\.success\) \{[\s\S]*?\}\s*\},\s*\}\);/', $js ), 'handleSkip must have an else branch and an error callback.' );
		abcc_assert_true( substr_count( $js, 'showStepError(' ) >= 5, 'Finish and skip must call showStepError on failure.' );
	}
);

abcc_test(
	'wizard key inputs are labelled for screen readers',
	function () {
		$html = abcc_test_render_onboarding();
		foreach ( array( 'claude', 'gemini', 'perplexity' ) as $p ) {
			abcc_assert_true( 1 === preg_match( '/id="' . $p . '-api-key"[^>]*aria-label="[^"]*API key/', $html ), $p . ' key input needs an aria-label.' );
		}

		// The test bootstrap defines OPENAI_API, so the OpenAI input never renders here; check the source.
		$src = file_get_contents( dirname( __DIR__ ) . '/includes/admin/onboarding/step-providers.php' );
		abcc_assert_true( 1 === preg_match( '/id="openai-api-key" aria-label="/', $src ), 'OpenAI key input needs an aria-label.' );
	}
);
