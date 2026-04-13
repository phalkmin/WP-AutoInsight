<?php
/**
 * Content sub-tab: Generation Log
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tab-pane active">
	<h2><?php esc_html_e( 'Generation Log', 'automated-blog-content-creator' ); ?></h2>
	<div class="abcc-job-log-toolbar">
		<label for="abcc-job-filter">
			<?php esc_html_e( 'Show:', 'automated-blog-content-creator' ); ?>
		</label>
		<select id="abcc-job-filter">
			<option value=""><?php esc_html_e( 'All jobs', 'automated-blog-content-creator' ); ?></option>
			<option value="queued"><?php esc_html_e( 'Queued', 'automated-blog-content-creator' ); ?></option>
			<option value="running"><?php esc_html_e( 'Running', 'automated-blog-content-creator' ); ?></option>
			<option value="failed"><?php esc_html_e( 'Failed', 'automated-blog-content-creator' ); ?></option>
			<option value="succeeded"><?php esc_html_e( 'Succeeded', 'automated-blog-content-creator' ); ?></option>
		</select>
		<label class="abcc-job-log-autorefresh">
			<input type="checkbox" id="abcc-job-auto-refresh" checked>
			<?php esc_html_e( 'Auto-refresh', 'automated-blog-content-creator' ); ?>
		</label>
		<button type="button" id="abcc-job-refresh" class="button button-secondary">
			<?php esc_html_e( 'Refresh now', 'automated-blog-content-creator' ); ?>
		</button>
	</div>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Status', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Source', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Keywords Used', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Template', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Created', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Runtime', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Result', 'automated-blog-content-creator' ); ?></th>
			</tr>
		</thead>
		<tbody id="abcc-job-log-body">
			<?php echo abcc_render_job_log_rows(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</tbody>
	</table>
	<p>
		<?php esc_html_e( 'The latest jobs update automatically while generation runs in the background.', 'automated-blog-content-creator' ); ?>
	</p>
</div>
