<?php
/**
 * Regression: boolean settings must read back truthy after a save.
 * Guards the `true === get_setting()` class of bug (review #1).
 */

abcc_test(
	'email notification: saved true reads back truthy (not strict-equal true)',
	function () {
		update_option( 'openai_email_notifications', true );

		$value = abcc_get_setting( 'openai_email_notifications', false );

		// WP stored '1'; strict `true ===` would FAIL here, truthy passes.
		abcc_assert_true( (bool) $value, 'Saved true setting should be truthy on read' );
		abcc_assert_false( true === $value, 'Strict identity is the bug we are guarding against' );
	}
);

abcc_test(
	'email notification: saved false reads back falsy',
	function () {
		update_option( 'openai_email_notifications', false );

		$value = abcc_get_setting( 'openai_email_notifications', false );

		abcc_assert_false( (bool) $value, 'Saved false setting should be falsy on read' );
	}
);
