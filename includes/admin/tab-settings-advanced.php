<?php
/**
 * Settings sub-tab: Advanced
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="advanced">

		<h2><?php esc_html_e( 'Debug & Diagnostics', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Debug Logging', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Writes detailed API request/response logs to wp-content/debug.log. Requires WP_DEBUG enabled in wp-config.php. Disable after troubleshooting.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="abcc_debug_logging"
							data-autosave-key="abcc_debug_logging"
							<?php checked( abcc_get_setting( 'abcc_debug_logging', false ) ); ?>>
						<?php esc_html_e( 'Enable debug logging to wp-content/debug.log', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<div class="abcc-migration-status">
			<h3><?php esc_html_e( 'Plugin & Migration Status', 'automated-blog-content-creator' ); ?></h3>
			<table class="widefat">
				<tr>
					<td><?php esc_html_e( 'Plugin version:', 'automated-blog-content-creator' ); ?></td>
					<td><strong><?php echo esc_html( ABCC_VERSION ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Settings schema:', 'automated-blog-content-creator' ); ?></td>
					<td><strong><?php echo esc_html( abcc_get_settings_schema()['version'] ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'WP Connectors:', 'automated-blog-content-creator' ); ?></td>
					<td><strong><?php echo abcc_wp_ai_client_available() ? esc_html__( 'Available', 'automated-blog-content-creator' ) : esc_html__( 'Not available', 'automated-blog-content-creator' ); ?></strong></td>
				</tr>
			</table>
		</div>

		<p class="abcc-export-row">
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'abcc_export_settings' => '1',
						'_wpnonce'             => wp_create_nonce( 'abcc_export_settings' ),
					)
				)
			);
			?>
			" class="button">
				<?php esc_html_e( 'Export Settings as JSON', 'automated-blog-content-creator' ); ?>
			</a>
		</p>

		<hr>
		<div class="abcc-danger-zone">
			<h3><?php esc_html_e( 'Danger Zone', 'automated-blog-content-creator' ); ?></h3>
			<p>
				<button type="submit" name="abcc_action" value="reset_settings" class="button"
					onclick="return confirm('<?php esc_attr_e( 'This will reset all plugin settings to defaults. Are you sure?', 'automated-blog-content-creator' ); ?>')">
					<?php esc_html_e( 'Reset All Settings', 'automated-blog-content-creator' ); ?>
				</button>
				&nbsp;
				<button type="submit" name="abcc_action" value="delete_history" class="button"
					onclick="return confirm('<?php esc_attr_e( 'This will delete all generation job history. Are you sure?', 'automated-blog-content-creator' ); ?>')">
					<?php esc_html_e( 'Delete All Generation History', 'automated-blog-content-creator' ); ?>
				</button>
			</p>
		</div>

	</form>
</div>
