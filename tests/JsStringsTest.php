<?php
/**
 * User-facing JS strings must be translatable (P1-18 / C-19).
 */

abcc_test(
	'admin.js has no hardcoded user-facing English literals from the QA list',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/admin.js' );
		foreach ( array( '"Queueing generation job', '"Post created successfully', '"Error generating post', '"Done! Redirecting', '"An error occurred."', '"Network error occurred."', 'placeholder="New Group Name"', '"✗ Connection error"', '"Selected: "' ) as $literal ) {
			abcc_assert_false( false !== strpos( $js, $literal ), 'admin.js still hardcodes ' . $literal );
		}
	}
);

abcc_test(
	'audio and onboarding scripts have no hardcoded user-facing literals from the QA list',
	function () {
		$audio = file_get_contents( dirname( __DIR__ ) . '/js/audio-transcriptions.js' );
		foreach ( array( "'Request timed out", "'Edit Post'", "'Transcribing and generating", "'File size: '", "'(Too large", '"Request timed out', '"Edit Post"', '"Transcribing and generating', '"File size: "', '"(Too large' ) as $literal ) {
			abcc_assert_false( false !== strpos( $audio, $literal ), 'audio-transcriptions.js still hardcodes ' . $literal );
		}
		$onb = file_get_contents( dirname( __DIR__ ) . '/js/onboarding.js' );
		foreach ( array( "'✓ Connectors Active'", 'title="Copy API key"' ) as $literal ) {
			abcc_assert_false( false !== strpos( $onb, $literal ), 'onboarding.js still hardcodes ' . $literal );
		}
	}
);

abcc_test(
	'every i18n key the scripts read exists in the PHP localize arrays',
	function () {
		$root  = dirname( __DIR__ );
		$cases = array(
			array( 'js/admin.js', 'admin.php', '/abcc\.i18n\(\s*["\']([A-Za-z]+)["\']\s*\)/' ),
			array( 'js/admin.js', 'admin.php', '/abccAdmin\.i18n\.([A-Za-z]+)/' ),
			array( 'js/audio-transcriptions.js', 'includes/audio.php', '/abccAudio\.i18n\.([A-Za-z]+)/' ),
			array( 'js/onboarding.js', 'includes/onboarding.php', '/abccOnboarding\.i18n\.([A-Za-z]+)/' ),
		);
		foreach ( $cases as $case ) {
			$js  = file_get_contents( $root . '/' . $case[0] );
			$php = file_get_contents( $root . '/' . $case[1] );
			preg_match_all( $case[2], $js, $m );
			foreach ( array_unique( $m[1] ) as $key ) {
				abcc_assert_true( 1 === preg_match( "/'" . $key . "'\s*=>/", $php ), $case[0] . ' reads i18n key ' . $key . ' missing from ' . $case[1] );
			}
		}
	}
);
