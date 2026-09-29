<?php
/**
 * The Permissions tab must govern the plugin's own prompt_ai grant.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'unchecking editors revokes the plugin-managed prompt_ai grant',
	function () {
		get_role( 'administrator' )->add_cap( 'prompt_ai' );
		get_role( 'editor' )->add_cap( 'prompt_ai' );

		abcc_sync_prompt_ai_capability( array( 'administrator' ) );

		abcc_assert_true( get_role( 'administrator' )->has_cap( 'prompt_ai' ) );
		abcc_assert_false( get_role( 'editor' )->has_cap( 'prompt_ai' ), 'Unchecked Editors lose the cap.' );
	}
);

abcc_test(
	'sync grants checked built-in roles, always keeps administrators, and leaves custom roles alone',
	function () {
		$GLOBALS['abcc_test_roles']['shop_manager'] = new ABCC_Test_Role( 'shop_manager', array( 'prompt_ai' => true ) );

		abcc_sync_prompt_ai_capability( array( 'contributor' ) );

		abcc_assert_true( get_role( 'administrator' )->has_cap( 'prompt_ai' ), 'Administrators are implicit.' );
		abcc_assert_true( get_role( 'contributor' )->has_cap( 'prompt_ai' ) );
		abcc_assert_false( get_role( 'editor' )->has_cap( 'prompt_ai' ) );
		abcc_assert_true( get_role( 'shop_manager' )->has_cap( 'prompt_ai' ), 'Third-party grants survive.' );
	}
);

abcc_test(
	'an editor unchecked in Permissions is denied once the grant is synced',
	function () {
		$GLOBALS['abcc_test_options']['abcc_allowed_roles'] = array( 'administrator' );
		$GLOBALS['abcc_test_current_user_roles']            = array( 'editor' );
		get_role( 'editor' )->add_cap( 'prompt_ai' );

		abcc_sync_prompt_ai_capability( $GLOBALS['abcc_test_options']['abcc_allowed_roles'] );
		$GLOBALS['abcc_test_current_user_caps'] = array( 'prompt_ai' => get_role( 'editor' )->has_cap( 'prompt_ai' ) );

		abcc_assert_false( abcc_current_user_can_prompt(), 'Editor without the cap and outside the list is denied.' );
	}
);
