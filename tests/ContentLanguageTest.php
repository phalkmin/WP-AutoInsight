<?php
/**
 * Content language resolution.
 */

abcc_test(
	'language labels use the regional form when a region is present',
	function () {
		$expected = array(
			'pt_BR' => 'Brazilian Portuguese',
			'pt_PT' => 'European Portuguese',
			'pt'    => 'Portuguese',
			'es_MX' => 'Mexican Spanish',
			'es_ES' => 'European Spanish',
			'en_US' => 'American English',
			'en_GB' => 'British English',
			'fr_FR' => 'French',
			'de_DE' => 'German',
			'ja'    => 'Japanese',
		);

		foreach ( $expected as $locale => $label ) {
			abcc_assert_same(
				$label,
				abcc_get_content_language_label( $locale ),
				'Locale ' . $locale . ' should map to "' . $label . '".'
			);
		}
	}
);

abcc_test(
	'unknown locales fall back to the site language rather than an empty string',
	function () {
		$label = abcc_get_content_language_label( 'xx_YY' );
		abcc_assert_true( '' !== $label, 'An unknown locale must still yield a usable label.' );
	}
);

abcc_test(
	'resolve honours the site setting and explicit overrides',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';

		abcc_update_setting( 'abcc_content_language', 'site' );
		abcc_assert_same(
			'Brazilian Portuguese',
			abcc_resolve_content_language(),
			'"site" should follow get_locale().'
		);

		abcc_update_setting( 'abcc_content_language', 'de_DE' );
		abcc_assert_same(
			'German',
			abcc_resolve_content_language(),
			'An explicit locale should win over the site locale.'
		);
	}
);

abcc_test(
	'sanitizer rejects junk and the removed auto mode',
	function () {
		abcc_assert_same( 'site', abcc_sanitize_content_language( 'auto' ), '"auto" was dropped; must fall back to site.' );
		abcc_assert_same( 'site', abcc_sanitize_content_language( '<script>' ), 'Junk must fall back to site.' );
		abcc_assert_same( 'site', abcc_sanitize_content_language( '' ), 'Empty must fall back to site.' );
		abcc_assert_same( 'pt_BR', abcc_sanitize_content_language( 'pt_BR' ), 'A known locale must pass through.' );
	}
);

abcc_test(
	'content prompts substitute {language} and append it when absent',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		abcc_update_setting( 'abcc_content_language', 'site' );

		$explicit = abcc_expand_content_prompt(
			'Write about {keyword} in {language}.',
			'A Title',
			array( 'widgets' ),
			array()
		);
		abcc_assert_true(
			false !== strpos( $explicit, 'Brazilian Portuguese' ),
			'{language} must be substituted: ' . $explicit
		);
		abcc_assert_false(
			false !== strpos( $explicit, '{language}' ),
			'No unsubstituted placeholder may remain: ' . $explicit
		);

		// A custom template written before v4.4 has no {language} token, but the
		// post still has to come out in the right language.
		$legacy = abcc_expand_content_prompt(
			'Write about {keyword}.',
			'A Title',
			array( 'widgets' ),
			array()
		);
		abcc_assert_true(
			false !== strpos( $legacy, 'Brazilian Portuguese' ),
			'Templates without the token must still get a language instruction: ' . $legacy
		);
	}
);

abcc_test(
	'title, SEO and audio prompts all carry a language instruction',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		abcc_update_setting( 'abcc_content_language', 'site' );

		// Title prompt.
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Um Título' ) );
		abcc_generate_title( 'sk-test', array( 'widgets' ), 'gpt-4.1-mini-2025-04-14' );
		$title_body = json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
		$title_sent = wp_json_encode( $title_body );
		abcc_assert_true(
			false !== strpos( $title_sent, 'Brazilian Portuguese' ),
			'Title prompt must name the language.'
		);
		abcc_assert_true(
			false !== strpos( $title_sent, '60' ),
			'Title prompt must carry the 60-character constraint.'
		);

		// Audio prompts.
		foreach ( array( 'abcc_audio_build_intro_prompt', 'abcc_audio_build_rewrite_prompt' ) as $builder ) {
			$prompt = $builder( 'Some spoken words.', array() );
			abcc_assert_true(
				false !== strpos( $prompt, 'Brazilian Portuguese' ),
				$builder . ' must name the language.'
			);
		}
	}
);

abcc_test(
	'SEO prompt carries a language instruction',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		abcc_update_setting( 'abcc_content_language', 'site' );

		abcc_test_queue_http_response( abcc_test_fake_seo_json( 'T', 'D', 'k' ) );
		abcc_generate_title_and_seo( 'sk-test', array( 'widgets' ), 'gpt-4.1-mini-2025-04-14', array() );

		$sent = wp_json_encode( json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true ) );
		abcc_assert_true(
			false !== strpos( $sent, 'Brazilian Portuguese' ),
			'SEO prompt must name the language.'
		);
	}
);

abcc_test(
	'default template carries language, tone, hook guidance and both keyword tokens',
	function () {
		$template = abcc_get_default_content_template();
		$prompt   = $template['prompt'];

		foreach ( array( '{language}', '{tone}', '{title}', '{keyword}', '{keywords}' ) as $token ) {
			abcc_assert_true(
				false !== strpos( $prompt, $token ),
				'Default template must use ' . $token . ': ' . $prompt
			);
		}

		abcc_assert_false(
			false !== strpos( strtolower( $prompt ), 'in today' ),
			'The anti-cliche rule should name the cliche, not contain it as prose.'
		);
		abcc_assert_true(
			false !== strpos( $prompt, 'hook' ),
			'Default template must include hook guidance.'
		);
	}
);

abcc_test(
	'locales added in the 4.4 review map to usable language names',
	function () {
		$expected = array(
			'zh_HK' => 'Traditional Chinese',
			'sk_SK' => 'Slovak',
			'sr_RS' => 'Serbian',
			'fa_IR' => 'Persian',
			'ms_MY' => 'Malay',
			'tl'    => 'Tagalog',
		);

		foreach ( $expected as $locale => $label ) {
			abcc_assert_same(
				$label,
				abcc_get_content_language_label( $locale ),
				'Locale ' . $locale . ' should map to "' . $label . '".'
			);
		}
	}
);
