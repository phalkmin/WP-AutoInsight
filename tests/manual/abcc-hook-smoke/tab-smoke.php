<?php
/**
 * The smoke test's admin tab: prints the setting it registered.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tab-pane active">
	<h2>Smoke Test</h2>
	<p id="abccsmoke-note">Setting abccsmoke_note: <strong><?php echo esc_html( (string) abcc_get_setting( 'abccsmoke_note', '' ) ); ?></strong></p>
	<p id="abccsmoke-provider">Provider registered: <strong><?php echo esc_html( abcc_provider_supports_text_generation( 'abccsmoke' ) ? 'yes' : 'no' ); ?></strong>, ready: <strong><?php echo esc_html( abcc_provider_is_ready( 'abccsmoke' ) ? 'yes' : 'no' ); ?></strong></p>
</div>
