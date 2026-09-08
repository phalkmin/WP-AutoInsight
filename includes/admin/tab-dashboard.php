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

// ── Composer (Zone 1) ──────────────────────────────────────────────────────
$composer_groups   = (array) abcc_get_setting( 'abcc_keyword_groups', array() );
$composer_topics   = function_exists( 'abcc_get_topics' ) ? abcc_get_topics() : array();
$composer_models   = abcc_get_available_text_model_options();
$composer_has_key  = ! empty( $composer_models ); // No connected provider => no model options.
$composer_resolved = abcc_resolve_composer_source( abcc_get_setting( 'abcc_composer_last_source', '' ) );
$composer_ready    = ! is_wp_error( $composer_resolved );
$composer_status   = abcc_get_setting( 'abcc_default_post_status', 'draft' );
$composer_model    = abcc_get_setting( 'prompt_select', '' );

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

// ── Capability grid (Zone 2) ───────────────────────────────────────────────
$cap_topic_count  = count( $composer_topics );
$cap_images_on    = (bool) abcc_get_setting( 'openai_generate_images', true );
$cap_infogr_on    = (bool) abcc_get_setting( 'abcc_enable_infographics', true );
$cap_providers_on = 0;
foreach ( $provider_cards as $card ) {
	if ( 'connected' === $card['health'] ) {
		++$cap_providers_on;
	}
}
?>

<div class="abcc-dashboard">

	<!-- Zone 1: Composer -->
	<div class="abcc-composer" id="abcc-composer">
		<div class="abcc-composer__header">
			<span class="abcc-composer__lede"><?php esc_html_e( 'Next post will use', 'automated-blog-content-creator' ); ?></span>
			<?php if ( $composer_has_key && $composer_ready ) : ?>
				<button type="button" class="abcc-composer__toggle" id="abcc-composer-toggle" aria-expanded="false">
					<?php esc_html_e( 'change ▾', 'automated-blog-content-creator' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( ! $composer_has_key ) : ?>
			<p class="abcc-composer__empty">
				<?php esc_html_e( 'Connect a provider to start generating.', 'automated-blog-content-creator' ); ?>
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
					<?php esc_html_e( 'Connect a provider →', 'automated-blog-content-creator' ); ?>
				</a>
			</p>
		<?php elseif ( ! $composer_ready ) : ?>
			<p class="abcc-composer__empty">
				<?php esc_html_e( 'Choose what to write about.', 'automated-blog-content-creator' ); ?>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => $page_slug,
							'tab'  => 'content',
						)
					)
				);
				?>
							">
					<?php esc_html_e( 'Add a keyword group →', 'automated-blog-content-creator' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page' => $page_slug,
							'tab'  => 'topics',
						)
					)
				);
				?>
							">
					<?php esc_html_e( 'or create a Topic →', 'automated-blog-content-creator' ); ?>
				</a>
			</p>
		<?php else : ?>

			<!-- Collapsed summary -->
			<div class="abcc-composer__summary" id="abcc-composer-summary">
				<span class="abcc-composer__chip">
					<span class="abcc-composer__k"><?php esc_html_e( 'Source', 'automated-blog-content-creator' ); ?></span>
					<strong><?php echo esc_html( $composer_resolved['label'] ); ?></strong>
				</span>
				<span class="abcc-composer__chip">
					<span class="abcc-composer__k"><?php esc_html_e( 'Template', 'automated-blog-content-creator' ); ?></span>
					<strong><?php echo esc_html( $composer_resolved['template'] ); ?></strong>
				</span>
				<span class="abcc-composer__chip">
					<span class="abcc-composer__k"><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></span>
					<strong><?php echo esc_html( abcc_get_model_display_name( $composer_model ) ); ?></strong>
				</span>
				<span class="abcc-composer__chip">
					<span class="abcc-composer__k"><?php esc_html_e( 'Save as', 'automated-blog-content-creator' ); ?></span>
					<strong><?php echo 'publish' === $composer_status ? esc_html__( 'Publish', 'automated-blog-content-creator' ) : esc_html__( 'Draft', 'automated-blog-content-creator' ); ?></strong>
				</span>
			</div>

			<!-- Expanded editor (hidden by default) -->
			<div class="abcc-composer__fields" id="abcc-composer-fields" hidden>
				<p class="abcc-composer__field">
					<label for="abcc-composer-source"><?php esc_html_e( 'Source', 'automated-blog-content-creator' ); ?></label>
					<select id="abcc-composer-source">
						<?php if ( ! empty( $composer_groups ) ) : ?>
							<optgroup label="<?php esc_attr_e( 'Keyword groups', 'automated-blog-content-creator' ); ?>">
								<?php foreach ( $composer_groups as $i => $g ) : ?>
									<?php
									if ( empty( $g['keywords'] ) ) {
										continue; }
									?>
									<option value="group:<?php echo (int) $i; ?>" <?php selected( $composer_resolved['token'], 'group:' . (int) $i ); ?>>
										<?php echo esc_html( ! empty( $g['name'] ) ? $g['name'] : sprintf( /* translators: %d: group number */ __( 'Group %d', 'automated-blog-content-creator' ), (int) $i + 1 ) ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
						<?php if ( ! empty( $composer_topics ) ) : ?>
							<optgroup label="<?php esc_attr_e( 'Topics', 'automated-blog-content-creator' ); ?>">
								<?php foreach ( $composer_topics as $t ) : ?>
									<?php
									// Resolver rejects empty-title topics; don't offer them.
									if ( '' === trim( (string) $t['title'] ) ) {
										continue;
									}
									?>
									<option value="topic:<?php echo (int) $t['id']; ?>" <?php selected( $composer_resolved['token'], 'topic:' . (int) $t['id'] ); ?>>
										<?php echo esc_html( $t['title'] ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</p>
				<p class="abcc-composer__field">
					<label for="abcc-composer-model"><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></label>
					<select id="abcc-composer-model">
						<?php foreach ( $composer_models as $group ) : ?>
							<optgroup label="<?php echo esc_attr( $group['group'] ); ?>">
								<?php foreach ( $group['options'] as $model_id => $model_label ) : ?>
									<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $composer_model, $model_id ); ?>>
										<?php echo esc_html( abcc_format_model_option_label( $model_id, $model_label ) ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="abcc-composer__field">
					<label><?php esc_html_e( 'Save as', 'automated-blog-content-creator' ); ?></label>
					<span class="abcc-composer__seg">
						<label><input type="radio" name="abcc-composer-status" value="draft" <?php checked( $composer_status, 'draft' ); ?>> <?php esc_html_e( 'Draft', 'automated-blog-content-creator' ); ?></label>
						<label><input type="radio" name="abcc-composer-status" value="publish" <?php checked( $composer_status, 'publish' ); ?>> <?php esc_html_e( 'Publish', 'automated-blog-content-creator' ); ?></label>
					</span>
				</p>
				<p class="abcc-composer__note"><?php esc_html_e( 'Your Source choice sticks as the default for next time. Model and Save-as apply to this post only — your global settings are unchanged.', 'automated-blog-content-creator' ); ?></p>
			</div>

			<div class="abcc-composer__actions">
				<button type="button" class="button button-primary" id="abcc-composer-generate">
					<?php esc_html_e( '⚡ Generate Now', 'automated-blog-content-creator' ); ?>
				</button>
				<span class="abcc-composer__hint"><?php esc_html_e( 'One click runs with exactly what is shown.', 'automated-blog-content-creator' ); ?></span>
				<span class="abcc-status" id="abcc-composer-status" style="display:none;"></span>
			</div>

		<?php endif; ?>
	</div>

	<!-- Automation Status -->
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

	<!-- Zone 2: Capability grid -->
	<h2 class="abcc-section-label"><?php esc_html_e( 'Everything you can do', 'automated-blog-content-creator' ); ?></h2>
	<div class="abcc-capability-grid">
		<a class="abcc-tile" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page' => $page_slug,
					'tab'  => 'topics',
				)
			)
		);
		?>
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'Topics', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state">
				<?php
				/* translators: %d: number of topics */
				echo esc_html( sprintf( _n( '%d active', '%d active', $cap_topic_count, 'automated-blog-content-creator' ), $cap_topic_count ) );
				?>
			</span>
		</a>
		<a class="abcc-tile" href="
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
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'Bulk Generate', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state"><?php esc_html_e( 'Paste a list → drafts', 'automated-blog-content-creator' ); ?></span>
		</a>
		<a class="abcc-tile" href="
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
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'Post from Audio', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state abcc-tile__state--new"><?php esc_html_e( 'New in 4.3', 'automated-blog-content-creator' ); ?></span>
		</a>
		<a class="abcc-tile" href="
		<?php
		// Bulk SEO regeneration lives on the Posts list as a bulk action;
		// there is no plugin subtab for it.
		echo esc_url( admin_url( 'edit.php?post_type=post' ) );
		?>
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'SEO Refresh', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state"><?php esc_html_e( 'Select posts → Bulk actions', 'automated-blog-content-creator' ); ?></span>
		</a>
		<a class="abcc-tile" href="
		<?php
		echo esc_url(
			add_query_arg(
				array(
					'page'   => $page_slug,
					'tab'    => 'media',
					'subtab' => 'images',
				)
			)
		);
		?>
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'Featured Images', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state <?php echo $cap_images_on ? 'abcc-tile__state--on' : 'abcc-tile__state--off'; ?>">
				<?php echo $cap_images_on ? esc_html__( 'On', 'automated-blog-content-creator' ) : esc_html__( 'Off', 'automated-blog-content-creator' ); ?>
			</span>
		</a>
		<a class="abcc-tile" href="
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
		">
			<span class="abcc-tile__name"><?php esc_html_e( 'Infographics', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state <?php echo $cap_infogr_on ? 'abcc-tile__state--on' : 'abcc-tile__state--off'; ?>">
				<?php echo $cap_infogr_on ? esc_html__( 'On', 'automated-blog-content-creator' ) : esc_html__( 'Off', 'automated-blog-content-creator' ); ?>
			</span>
		</a>
		<a class="abcc-tile" href="
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
			<span class="abcc-tile__name"><?php esc_html_e( 'Schedule', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state <?php echo $schedule_info ? 'abcc-tile__state--on' : 'abcc-tile__state--off'; ?>">
				<?php
				echo $schedule_info
					? esc_html( sprintf( /* translators: %s: human time diff */ __( 'Next in %s', 'automated-blog-content-creator' ), human_time_diff( time(), $schedule_info['timestamp'] ) ) )
					: esc_html__( 'Off', 'automated-blog-content-creator' );
				?>
			</span>
		</a>
		<a class="abcc-tile" href="
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
			<span class="abcc-tile__name"><?php esc_html_e( 'Providers', 'automated-blog-content-creator' ); ?></span>
			<span class="abcc-tile__state <?php echo $cap_providers_on > 0 ? 'abcc-tile__state--on' : 'abcc-tile__state--off'; ?>">
				<?php
				/* translators: %d: number of connected providers */
				echo esc_html( sprintf( _n( '%d connected', '%d connected', $cap_providers_on, 'automated-blog-content-creator' ), $cap_providers_on ) );
				?>
			</span>
		</a>
	</div>

	<!-- Zone 3: Status rail -->
	<h2 class="abcc-section-label"><?php esc_html_e( 'At a glance', 'automated-blog-content-creator' ); ?></h2>
	<div class="abcc-status-rail">

	<!-- Recent Activity (full width) -->
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
			<p class="description"><?php esc_html_e( 'No generation jobs yet. Use the Composer above to create your first post.', 'automated-blog-content-creator' ); ?></p>
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
							<?php echo esc_html( abcc_get_model_display_name( $model ) ); ?>
							&bull; <?php echo esc_html( abcc_get_job_source_label( $source ) ); ?>
							&bull; <?php echo esc_html( human_time_diff( get_post_time( 'U', false, $job ), time() ) ); ?> <?php esc_html_e( 'ago', 'automated-blog-content-creator' ); ?>
						</span>
						<?php if ( $result && ABCC_Job::STATUS_SUCCESS === $job_status ) : ?>
							<a href="<?php echo esc_url( (string) get_edit_post_link( $result ) ); ?>" class="abcc-activity-link">
								<?php esc_html_e( 'View', 'automated-blog-content-creator' ); ?>
							</a>
						<?php elseif ( $job_error && ABCC_Job::STATUS_FAILED === $job_status ) : ?>
							<span class="abcc-activity-error">
								<?php
								// Inline, not a title attribute — hover-only
								// text is invisible on touch devices.
								echo esc_html( wp_trim_words( $job_error, 14, '…' ) );
								?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="abcc-activity-empty" style="display:none;"><?php esc_html_e( 'No jobs match the selected filter.', 'automated-blog-content-creator' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Provider Health -->
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
					esc_html__( 'Version %s (Fourze)', 'automated-blog-content-creator' ),
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
		</div><!-- /.abcc-dashboard-card--about -->

	</div><!-- /.abcc-status-rail -->
</div><!-- /.abcc-dashboard -->
