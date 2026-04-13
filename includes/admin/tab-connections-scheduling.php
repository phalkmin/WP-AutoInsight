<?php
/**
 * Connections sub-tab: Scheduling
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auto_create   = abcc_get_setting( 'openai_auto_create', '' );
$email_notifs  = abcc_get_setting( 'openai_email_notifications', false );
$schedule_info = abcc_get_openai_event_schedule();
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="scheduling">

		<h2><?php esc_html_e( 'Automated Post Generation', 'automated-blog-content-creator' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Frequency', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'How often the plugin automatically generates and publishes a post. Uses WP-Cron — your site must receive traffic for cron to fire reliably.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<?php
					$frequencies = array(
						''       => __( 'Disabled', 'automated-blog-content-creator' ),
						'hourly' => __( 'Hourly', 'automated-blog-content-creator' ),
						'daily'  => __( 'Daily', 'automated-blog-content-creator' ),
						'weekly' => __( 'Weekly', 'automated-blog-content-creator' ),
					);
					foreach ( $frequencies as $value => $label ) :
						?>
						<label class="abcc-label-block">
							<input type="radio" name="openai_auto_create" value="<?php echo esc_attr( $value ); ?>"
								data-dirty-watch="openai_auto_create"
								<?php checked( $auto_create, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Email Notifications', 'automated-blog-content-creator' ); ?>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Send an email to the admin address when a scheduled post is created.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="openai_email_notifications"
							data-dirty-watch="openai_email_notifications"
							<?php checked( $email_notifs ); ?>>
						<?php esc_html_e( 'Send email when a scheduled post is created', 'automated-blog-content-creator' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php if ( $schedule_info ) : ?>
			<div class="abcc-schedule-status notice notice-info">
				<p>
					<?php
						printf(
							/* translators: 1: human time diff, 2: next run date */
							esc_html__( 'Next post in %1$s — %2$s', 'automated-blog-content-creator' ),
							'<strong>' . esc_html( human_time_diff( time(), $schedule_info['timestamp'] ) ) . '</strong>',
							'<strong>' . esc_html( $schedule_info['next_run'] ) . '</strong>'
						);
					if ( ! empty( $schedule_info['group_name'] ) ) {
						echo ' &bull; ' . esc_html__( 'Group:', 'automated-blog-content-creator' ) . ' <strong>' . esc_html( $schedule_info['group_name'] ) . '</strong>';
					}
					if ( ! empty( $schedule_info['model'] ) ) {
						echo ' &bull; ' . esc_html__( 'Model:', 'automated-blog-content-creator' ) . ' <strong>' . esc_html( $schedule_info['model'] ) . '</strong>';
					}
					?>
					</p>
				</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Scheduler is currently disabled. Select a frequency above to enable it.', 'automated-blog-content-creator' ); ?></p>
		<?php endif; ?>

		<?php submit_button( __( 'Save Scheduling Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
