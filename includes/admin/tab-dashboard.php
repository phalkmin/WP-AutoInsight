<?php
/**
 * Tab: Dashboard
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Data preparation ──────────────────────────────────────────────────────

// Scheduling status.
$schedule_info = abcc_get_openai_event_schedule();

// Recent jobs (last 5).
$recent_jobs = get_posts(
	array(
		'post_type'      => ABCC_Job::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

// Provider health — four states with 24h freshness window.
// States: connected (validated ≤24h ago) | stale (key exists, never checked or >24h) | failed (last check failed) | no_key
$providers      = abcc_get_provider_ids();
$provider_cards = array();
foreach ( $providers as $provider_id ) {
	$provider = abcc_get_provider( $provider_id );
	$snapshot = abcc_get_provider_health_snapshot( $provider_id );

	$provider_cards[] = array(
		'id'     => $provider_id,
		'name'   => $provider['name'],
		'health' => $snapshot['health'],
		'last_v' => $snapshot['last_check'],
	);
}

// Sort: connected first, stale second, failed third, no_key last.
$health_order = array(
	'connected' => 0,
	'stale'     => 1,
	'failed'    => 2,
	'no_key'    => 3,
);
usort(
	$provider_cards,
	function ( $a, $b ) use ( $health_order ) {
		return $health_order[ $a['health'] ] - $health_order[ $b['health'] ];
	}
);

$page_slug = 'automated-blog-content-creator-post';
?>

<div class="abcc-dashboard">

	<!-- Row 1: Automation Status + Quick Actions -->
	<div class="abcc-dashboard-row abcc-dashboard-row--top">

		<div class="abcc-dashboard-card abcc-dashboard-card--status">
			<h2 class="abcc-dashboard-card__title"><?php esc_html_e( 'Automation Status', 'automated-blog-content-creator' ); ?></h2>
			<?php if ( $schedule_info ) : ?>
				<p class="abcc-status-next">
					<span class="abcc-status-dot abcc-status-dot--active"></span>
					<?php
					printf(
						/* translators: 1: human time diff, 2: next run date */
						esc_html__( 'Next post in %1$s — %2$s', 'automated-blog-content-creator' ),
						'<strong>' . esc_html( human_time_diff( time(), $schedule_info['timestamp'] ) ) . '</strong>',
						'<strong>' . esc_html( $schedule_info['next_run'] ) . '</strong>'
					);
					?>
				</p>
				<?php if ( ! empty( $schedule_info['group_name'] ) ) : ?>
					<p class="abcc-status-detail">
						<?php esc_html_e( 'Group:', 'automated-blog-content-creator' ); ?>
						<strong><?php echo esc_html( $schedule_info['group_name'] ); ?></strong>
						&nbsp;&bull;&nbsp;
						<?php esc_html_e( 'Model:', 'automated-blog-content-creator' ); ?>
						<strong><?php echo esc_html( ! empty( $schedule_info['model'] ) ? $schedule_info['model'] : '—' ); ?></strong>
					</p>
				<?php endif; ?>
				<p>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'   => $page_slug,
								'tab'    => 'connections',
								'subtab' => 'scheduling',
							)
						)
					);
					?>
								">
						<?php esc_html_e( 'Change schedule →', 'automated-blog-content-creator' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p class="abcc-status-next">
					<span class="abcc-status-dot abcc-status-dot--inactive"></span>
					<?php esc_html_e( 'Scheduler is off. No posts are queued.', 'automated-blog-content-creator' ); ?>
				</p>
				<p>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'   => $page_slug,
								'tab'    => 'connections',
								'subtab' => 'scheduling',
							)
						)
					);
					?>
								">
						<?php esc_html_e( 'Set up schedule →', 'automated-blog-content-creator' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<div class="abcc-dashboard-card abcc-dashboard-card--actions">
			<h2 class="abcc-dashboard-card__title"><?php esc_html_e( 'Quick Actions', 'automated-blog-content-creator' ); ?></h2>
			<div class="abcc-quick-actions">
				<button type="button" id="abcc-dash-generate" class="button button-primary abcc-quick-action-btn">
					<?php esc_html_e( 'Generate Post Now', 'automated-blog-content-creator' ); ?>
				</button>
				<p id="abcc-dash-generate-status" class="abcc-status" style="display:none;"></p>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'   => $page_slug,
							'tab'    => 'content',
							'subtab' => 'bulk',
						)
					)
				);
				?>
				" class="button abcc-quick-action-btn">
					<?php esc_html_e( 'Bulk Generate', 'automated-blog-content-creator' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'   => $page_slug,
							'tab'    => 'media',
							'subtab' => 'audio',
						)
					)
				);
				?>
				" class="button abcc-quick-action-btn">
					<?php esc_html_e( 'Upload Audio', 'automated-blog-content-creator' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'   => $page_slug,
							'tab'    => 'media',
							'subtab' => 'infographics',
						)
					)
				);
				?>
				" class="button abcc-quick-action-btn">
					<?php esc_html_e( 'Create Infographic', 'automated-blog-content-creator' ); ?>
				</a>
			</div>
		</div>

	</div>

	<!-- Row 2: Recent Activity (full width) -->
	<div class="abcc-dashboard-card abcc-dashboard-card--activity">
		<div class="abcc-dashboard-card__header">
			<h2 class="abcc-dashboard-card__title"><?php esc_html_e( 'Recent Activity', 'automated-blog-content-creator' ); ?></h2>
			<div class="abcc-activity-filters" role="group" aria-label="<?php esc_attr_e( 'Filter by status', 'automated-blog-content-creator' ); ?>">
				<button type="button" class="abcc-filter-btn abcc-filter-btn--active" data-filter="all"><?php esc_html_e( 'All', 'automated-blog-content-creator' ); ?></button>
				<button type="button" class="abcc-filter-btn" data-filter="<?php echo esc_attr( ABCC_Job::STATUS_QUEUED ); ?>"><?php esc_html_e( 'Queued', 'automated-blog-content-creator' ); ?></button>
				<button type="button" class="abcc-filter-btn" data-filter="<?php echo esc_attr( ABCC_Job::STATUS_RUNNING ); ?>"><?php esc_html_e( 'Running', 'automated-blog-content-creator' ); ?></button>
				<button type="button" class="abcc-filter-btn" data-filter="<?php echo esc_attr( ABCC_Job::STATUS_FAILED ); ?>"><?php esc_html_e( 'Failed', 'automated-blog-content-creator' ); ?></button>
			</div>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'   => $page_slug,
						'tab'    => 'content',
						'subtab' => 'log',
					)
				)
			);
			?>
			" class="abcc-view-all">
				<?php esc_html_e( 'View full log →', 'automated-blog-content-creator' ); ?>
			</a>
		</div>
		<?php if ( empty( $recent_jobs ) ) : ?>
			<p class="description"><?php esc_html_e( 'No generation jobs yet. Use Quick Actions above to create your first post.', 'automated-blog-content-creator' ); ?></p>
		<?php else : ?>
			<ul class="abcc-activity-list" id="abcc-dash-activity-list">
				<?php
				foreach ( $recent_jobs as $job ) :
					$job_status = get_post_meta( $job->ID, '_abcc_job_status', true );
					$model      = get_post_meta( $job->ID, '_abcc_job_model', true );
					$source     = get_post_meta( $job->ID, '_abcc_job_source', true );
					$result     = get_post_meta( $job->ID, '_abcc_job_result_post_id', true );
					$job_error  = get_post_meta( $job->ID, '_abcc_job_error', true );
					$icon       = ABCC_Job::STATUS_SUCCESS === $job_status ? '✓' : ( ABCC_Job::STATUS_FAILED === $job_status ? '✗' : '⟳' );
					$class      = 'abcc-activity-item abcc-activity-item--' . esc_attr( $job_status );
					?>
					<li class="<?php echo $class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" data-status="<?php echo esc_attr( $job_status ); ?>">
						<span class="abcc-activity-icon"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="abcc-activity-title"><?php echo esc_html( get_the_title( $job ) ); ?></span>
						<span class="abcc-activity-meta">
							<?php echo esc_html( $model ); ?>
							&bull; <?php echo esc_html( abcc_get_job_source_label( $source ) ); ?>
							&bull; <?php echo esc_html( human_time_diff( get_post_time( 'U', false, $job ), time() ) ); ?> <?php esc_html_e( 'ago', 'automated-blog-content-creator' ); ?>
						</span>
						<?php if ( $result && ABCC_Job::STATUS_SUCCESS === $job_status ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $result ) ); ?>" class="abcc-activity-link">
								<?php esc_html_e( 'View', 'automated-blog-content-creator' ); ?>
							</a>
						<?php elseif ( $job_error && ABCC_Job::STATUS_FAILED === $job_status ) : ?>
							<span class="abcc-activity-error" title="<?php echo esc_attr( $job_error ); ?>">
								<?php esc_html_e( 'Error', 'automated-blog-content-creator' ); ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="abcc-activity-empty" style="display:none;"><?php esc_html_e( 'No jobs match the selected filter.', 'automated-blog-content-creator' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Row 3: Provider Health + About -->
	<div class="abcc-dashboard-row abcc-dashboard-row--bottom">

		<div class="abcc-dashboard-card abcc-dashboard-card--health">
			<h2 class="abcc-dashboard-card__title"><?php esc_html_e( 'Provider Health', 'automated-blog-content-creator' ); ?></h2>
			<ul class="abcc-health-list">
				<?php
				foreach ( $provider_cards as $card ) :
					$health_labels = array(
						'connected' => __( 'Connected', 'automated-blog-content-creator' ),
						'stale'     => __( 'Not verified recently', 'automated-blog-content-creator' ),
						'failed'    => __( 'Not Working', 'automated-blog-content-creator' ),
						'no_key'    => __( 'No key', 'automated-blog-content-creator' ),
					);
					$dot_class     = 'abcc-status-dot--' . str_replace( '_', '-', $card['health'] );
					$stale_tooltip = '';
					if ( 'stale' === $card['health'] && ! empty( $card['last_v']['timestamp'] ) ) {
						$stale_tooltip = sprintf(
							/* translators: %s: human-readable time since last check */
							__( 'Last checked: %s ago', 'automated-blog-content-creator' ),
							human_time_diff( $card['last_v']['timestamp'], time() )
						);
					}
					?>
					<li class="abcc-health-item"<?php echo $stale_tooltip ? ' title="' . esc_attr( $stale_tooltip ) . '"' : ''; ?>>
						<span class="abcc-status-dot <?php echo esc_attr( $dot_class ); ?>"></span>
						<span class="abcc-health-name"><?php echo esc_html( $card['name'] ); ?></span>
						<span class="abcc-health-status"><?php echo esc_html( $health_labels[ $card['health'] ] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => $page_slug,
							'tab'  => 'connections',
						)
					)
				);
				?>
				">
					<?php esc_html_e( 'Manage connections →', 'automated-blog-content-creator' ); ?>
				</a>
			</p>
		</div>

		<div class="abcc-dashboard-card abcc-dashboard-card--about">
			<h2 class="abcc-dashboard-card__title"><?php esc_html_e( 'WP-AutoInsight', 'automated-blog-content-creator' ); ?></h2>
			<p class="abcc-about-version">
				<?php
				printf(
					/* translators: %s: version number */
					esc_html__( 'Version %s (Decade)', 'automated-blog-content-creator' ),
					esc_html( ABCC_VERSION )
				);
				?>
			</p>
			<ul class="abcc-about-links">
				<li>
					<span class="dashicons dashicons-book"></span>
					<a href="https://wpautoinsight.phalkmin.me/" target="_blank" rel="noopener">
						<?php esc_html_e( 'Documentation', 'automated-blog-content-creator' ); ?>
					</a>
				</li>
				<li>
					<span class="dashicons dashicons-sos"></span>
					<a href="https://wordpress.org/support/plugin/wp-autoinsight/" target="_blank" rel="noopener">
						<?php esc_html_e( 'Support Forum', 'automated-blog-content-creator' ); ?>
					</a>
				</li>
				<li>
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<a href="https://github.com/phalkmin/wp-autoinsight" target="_blank" rel="noopener">
						<?php esc_html_e( 'GitHub', 'automated-blog-content-creator' ); ?>
					</a>
				</li>
				<li>
					<span class="dashicons dashicons-heart"></span>
					<a href="https://ko-fi.com/phalkmin" target="_blank" rel="noopener">
						<?php esc_html_e( 'Buy Me a Coffee (Ko-fi)', 'automated-blog-content-creator' ); ?>
					</a>
				</li>
				<li>
					<span class="dashicons dashicons-businessman"></span>
					<a href="mailto:phalkmin@protonmail.com?subject=Consulting%20Inquiry">
						<?php esc_html_e( 'Work With Me', 'automated-blog-content-creator' ); ?>
					</a>
				</li>
			</ul>
		</div>

	</div>
</div>
