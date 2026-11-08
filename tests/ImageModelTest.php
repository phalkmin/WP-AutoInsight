<?php
/**
 * Retired image models map to their successor (4.5.3).
 *
 * OpenAI shut gpt-image-1 down on 2026-10-23 and Google shut
 * gemini-3-pro-image-preview down on 2026-06-25; saved selections must keep
 * producing images without a re-save.
 */

abcc_test(
	'retired image models map to their successor once the date has passed',
	function () {
		$before = strtotime( '2026-11-05 UTC' );
		$after  = strtotime( '2026-12-02 UTC' );

		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'gpt-image-1', $before ) );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'dall-e-3', $before ) );
		abcc_assert_same( 'gpt-image-1-mini', abcc_resolve_image_model( 'openai', 'gpt-image-1-mini', $before ), 'Still served until December 1, 2026.' );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'gpt-image-1-mini', $after ) );
		abcc_assert_same( 'gpt-image-2.5-sunburst', abcc_resolve_image_model( 'openai', 'gpt-image-1.5', $after ) );
		abcc_assert_same( 'gpt-image-2.5-sunburst', abcc_resolve_image_model( 'openai', 'gpt-image-2.5-sunburst', $before ) );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', '', $before ), 'Blank falls back to the first option.' );
		abcc_assert_same( 'gpt-image-2.5-flare', abcc_resolve_image_model( 'openai', 'made-up', $before ) );

		abcc_assert_same( 'gemini-3-pro-image', abcc_resolve_image_model( 'gemini', 'gemini-3-pro-image-preview', $before ) );
		abcc_assert_same( 'gemini-2.5-flash-image', abcc_resolve_image_model( 'gemini', 'gemini-2.5-flash-image', $before ) );
		abcc_assert_same( 'gemini-3.1-flash-lite-image', abcc_resolve_image_model( 'gemini', 'gemini-2.5-flash-image', strtotime( '2027-03-16 UTC' ) ) );

		abcc_assert_same( 'gpt-image-2.5-flare', abcc_get_setting_default( 'abcc_openai_image_model' ) );
		abcc_assert_same( strtotime( '2026-11-05 12:00:00 UTC' ), abcc_get_retirement_now(), 'The harness pins the retirement clock.' );
	}
);

abcc_test(
	'image generation sends the successor of a retired saved model',
	function () {
		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'down' );
		abcc_gemini_generate_images( 'k', 'a cat', 'gemini-3-pro-image-preview', '1K' );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_http_last_request']['url'], '/models/gemini-3-pro-image:generateContent' ) );

		$GLOBALS['abcc_http_queue'][] = new WP_Error( 'http_request_failed', 'down' );
		abcc_openai_generate_images( 'k', 'a cat', 1, '1024x1024', 'medium', 'gpt-image-1' );
		$request = $GLOBALS['abcc_http_last_request'];
		abcc_assert_true( false !== strpos( $request['url'], 'images/generations' ) );
		abcc_assert_same( 'gpt-image-2.5-flare', json_decode( $request['args']['body'], true )['model'] );
	}
);

abcc_test(
	'the picker offers no retired IDs, except a still-served saved one marked as retiring',
	function () {
		$markup = file_get_contents( dirname( __DIR__ ) . '/includes/admin/tab-media-images.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		abcc_assert_false( strpos( $markup, '"gpt-image-1"' ) );
		abcc_assert_false( strpos( $markup, 'gemini-3-pro-image-preview' ) );
		abcc_assert_true( false !== strpos( $markup, 'abcc_get_image_model_picker_options' ) );

		$plain = abcc_get_image_model_picker_options( 'openai', 'gpt-image-2.5-flare' );
		abcc_assert_same( array( 'gpt-image-2.5-flare', 'gpt-image-2.5-sunburst' ), array_keys( $plain ) );

		$retiring = abcc_get_image_model_picker_options( 'openai', 'gpt-image-1-mini' );
		abcc_assert_same( 'gpt-image-1-mini', key( $retiring ) );
		abcc_assert_true( false !== strpos( $retiring['gpt-image-1-mini'], 'retiring soon' ) );

		abcc_assert_array_has_key( 'gemini-3-pro-image', abcc_get_image_model_options( 'gemini' ) );
	}
);
