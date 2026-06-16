<?php
/**
 * Regression tests for the abcc_draft_first → abcc_default_post_status migration (v4.2 Unit C).
 *
 * Note on the "explicit false" case: WordPress stores boolean false as ''
 * in wp_options, so get_option() returns '' for explicitly-saved false and
 * boolean false only when the option does not exist. The tests mirror that.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run migrations from a simulated pre-4.2 install state.
 *
 * @param array $options Initial option store.
 * @return void
 */
function abcc_test_run_migration_from( $options ) {
	$GLOBALS['abcc_test_options'] = array_merge(
		array( 'abcc_version' => '4.1.1' ),
		$options
	);
	abcc_run_settings_migrations();
}

abcc_test(
	'migration maps explicit draft_first=false (stored as empty string) to publish',
	function () {
		abcc_test_run_migration_from( array( 'abcc_draft_first' => '' ) );

		abcc_assert_same( 'publish', abcc_get_setting( 'abcc_default_post_status', 'draft' ) );
	}
);

abcc_test(
	'migration maps draft_first=true to draft',
	function () {
		abcc_test_run_migration_from( array( 'abcc_draft_first' => '1' ) );

		abcc_assert_same( 'draft', abcc_get_setting( 'abcc_default_post_status', 'publish' ) );
	}
);

abcc_test(
	'migration leaves schema default draft when draft_first was never set',
	function () {
		abcc_test_run_migration_from( array() );

		abcc_assert_true(
			! array_key_exists( 'abcc_default_post_status', $GLOBALS['abcc_test_options'] ),
			'Unset draft_first must not write the new option.'
		);
		abcc_assert_same( 'draft', abcc_get_setting( 'abcc_default_post_status', 'publish' ) );
	}
);

abcc_test(
	'upgrade from pre-4.2 sets the one-time draft-mode notice flag',
	function () {
		abcc_test_run_migration_from( array( 'abcc_draft_first' => '1' ) );

		abcc_assert_same( 1, get_option( 'abcc_draft_mode_notice' ), 'Real upgrades must queue the draft-mode notice.' );
	}
);

abcc_test(
	'fresh install does not set the draft-mode notice flag',
	function () {
		// No stored abcc_version → get_option falls back to 1.0.0 → fresh install.
		$GLOBALS['abcc_test_options'] = array();
		abcc_run_settings_migrations();

		abcc_assert_true(
			! array_key_exists( 'abcc_draft_mode_notice', $GLOBALS['abcc_test_options'] ),
			'Fresh installs must not see the upgrade notice.'
		);
	}
);

abcc_test(
	'autosave handler applies the schema sanitizer for abcc_default_post_status',
	function () {
		$GLOBALS['abcc_test_options'] = array();

		$_POST = array(
			'key'   => 'abcc_default_post_status',
			'value' => 'pending',
		);
		abcc_handle_autosave_setting();
		abcc_assert_same( 'draft', get_option( 'abcc_default_post_status' ), 'Invalid status must sanitize to draft via the schema callback.' );

		$_POST['value'] = 'publish';
		abcc_handle_autosave_setting();
		abcc_assert_same( 'publish', get_option( 'abcc_default_post_status' ) );

		$_POST = array();
	}
);

abcc_test(
	'sanitizer only allows publish, falls back to draft',
	function () {
		abcc_assert_same( 'publish', abcc_sanitize_post_status( 'publish' ) );
		abcc_assert_same( 'draft', abcc_sanitize_post_status( 'draft' ) );
		abcc_assert_same( 'draft', abcc_sanitize_post_status( 'pending' ) );
		abcc_assert_same( 'draft', abcc_sanitize_post_status( true ) );
		abcc_assert_same( 'draft', abcc_sanitize_post_status( null ) );
	}
);
