<?php
/**
 * Settings-form credential and unsaved-data regressions.
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Tests read local source files.

abcc_test(
	'a rejected custom endpoint skips its key submission and inline validation',
	function () {
		$source = file_get_contents( dirname( __DIR__ ) . '/admin.php' );

		$failed = strpos( $source, '$custom_save_failed = true;' );
		$skip   = strpos( $source, '! abcc_current_user_can_manage_custom_endpoint() || $custom_save_failed' );
		abcc_assert_true( false !== $failed && $skip > $failed, 'The provider key loop must skip custom after endpoint validation fails.' );

		$footer_guard = strpos( $source, 'if ( ! $custom_save_failed ) {' );
		$footer_hook  = strpos( $source, "add_action( 'admin_footer', 'abcc_trigger_inline_api_validation' );" );
		abcc_assert_true( false !== $footer_guard && $footer_hook > $footer_guard, 'A rejected endpoint must not schedule validation against the old host.' );
	}
);

abcc_test(
	'custom endpoint policy edits disable every browser validation path until save',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/admin.js' );

		abcc_assert_true( false !== strpos( $js, '#abcc_custom_endpoint_base_url, input[name="abcc_custom_endpoint[allow_local]"]' ) );
		abcc_assert_true( false !== strpos( $js, 'if (provider === "custom" && abccCustomEndpointDirty)' ), 'The shared validation function must block clicks, key blur, and bulk validation.' );
		abcc_assert_true( false !== strpos( $js, 'abcc.i18n("saveEndpointFirst")' ) );
		abcc_assert_true( false !== strpos( $js, '.prop("disabled", true)' ) );
	}
);

abcc_test(
	'model changes stop before AJAX when the surrounding settings form is dirty',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/admin.js' );

		abcc_assert_true( false !== strpos( $js, '$form.serialize() !== snapshot' ) );
		abcc_assert_true( false !== strpos( $js, 'abcc.i18n("saveBeforeModelChange")' ) );

		$add_start = strpos( $js, 'function abccAddModel($box, data)' );
		$add_guard = strpos( $js, 'if (!abccModelSettingsAreSaved($box))', $add_start );
		$add_post  = strpos( $js, 'abccModelPost("abcc_add_custom_model"', $add_start );
		abcc_assert_true( false !== $add_start && $add_guard > $add_start && $add_post > $add_guard, 'Adding a model must check for unsaved fields before posting.' );

		$remove_start = strpos( $js, '$(document).on("click", ".abcc-custom-model-remove"' );
		$remove_guard = strpos( $js, '!abccModelSettingsAreSaved($box)', $remove_start );
		$remove_post  = strpos( $js, 'abccModelPost("abcc_remove_custom_model"', $remove_start );
		abcc_assert_true( false !== $remove_start && $remove_guard > $remove_start && $remove_post > $remove_guard, 'Removing a model must check for unsaved fields before posting.' );
	}
);

abcc_test(
	'a group added after a template gets the current template options',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/admin.js' );

		abcc_assert_true(
			false !== strpos( $js, '$container.append(tpl.replace(/__INDEX__/g, index));' . "\n    updateTemplateSelectors();" ),
			'The new group select must be rebuilt after insertion instead of keeping the stale template options.'
		);
	}
);
