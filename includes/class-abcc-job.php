<?php
/**
 * Generation job handling.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-abcc-job-type.php';

/**
 * Register the hidden job post type.
 *
 * @return void
 */
function abcc_register_job_post_type() {
	register_post_type(
		ABCC_Job::POST_TYPE,
		array(
			'labels'              => array(
				'name' => __( 'Generation Jobs', 'automated-blog-content-creator' ),
			),
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
		)
	);
}
add_action( 'init', 'abcc_register_job_post_type' );

/**
 * Create and queue a generation job.
 *
 * @param array $payload Normalized generation payload.
 * @param array $args    Optional job metadata.
 * @return int|WP_Error Job ID on success.
 */
function abcc_queue_generation_job( $payload, $args = array() ) {
	$job_title = sprintf(
		/* translators: %s: generation source. */
		__( 'Generation Job: %s', 'automated-blog-content-creator' ),
		ucfirst( $payload['source'] ?? 'manual' )
	);

	$job_id = wp_insert_post(
		array(
			'post_type'   => ABCC_Job::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $job_title,
		),
		true
	);

	if ( is_wp_error( $job_id ) ) {
		return $job_id;
	}

	$created_by = isset( $args['created_by'] ) ? (int) $args['created_by'] : get_current_user_id();
	$run_id     = isset( $args['run_id'] ) ? sanitize_key( $args['run_id'] ) : '';

	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_QUEUED );
	update_post_meta( $job_id, '_abcc_job_payload', $payload );
	update_post_meta( $job_id, '_abcc_job_source', $payload['source'] ?? 'manual' );
	update_post_meta( $job_id, '_abcc_job_model', $payload['model'] ?? '' );
	update_post_meta( $job_id, '_abcc_job_keywords', (array) ( $payload['keywords'] ?? array() ) );
	update_post_meta( $job_id, '_abcc_job_template', $payload['template'] ?? 'default' );
	update_post_meta( $job_id, '_abcc_job_created_by', $created_by );
	update_post_meta( $job_id, '_abcc_job_created_at', current_time( 'mysql' ) );

	if ( ! empty( $run_id ) ) {
		update_post_meta( $job_id, '_abcc_job_run_id', $run_id );
	}

	if ( abcc_is_wp_cron_available() ) {
		wp_schedule_single_event( time(), 'abcc_process_generation_job', array( $job_id ) );
		spawn_cron();
	} else {
		// WP-Cron is disabled (e.g. managed hosting with external cron). Process inline
		// so generation still completes — the job record still tracks status normally.
		abcc_process_generation_job( $job_id );
	}

	return $job_id;
}

/**
 * Check whether WP-Cron is available for background jobs.
 *
 * @return bool
 */
function abcc_is_wp_cron_available() {
	return ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
}

/**
 * Check whether there are stale queued jobs.
 *
 * @param int $older_than_seconds Age threshold.
 * @return bool
 */
function abcc_has_stale_queued_jobs( $older_than_seconds = 300 ) {
	$jobs = get_posts(
		array(
			'post_type'      => ABCC_Job::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => '_abcc_job_status',
					'value' => ABCC_Job::STATUS_QUEUED,
				),
			),
		)
	);

	if ( empty( $jobs ) ) {
		return false;
	}

	$created_at = get_post_meta( $jobs[0]->ID, '_abcc_job_created_at', true );
	if ( empty( $created_at ) ) {
		return false;
	}

	$created_timestamp = strtotime( get_gmt_from_date( $created_at ) );
	return $created_timestamp && ( time() - $created_timestamp ) > (int) $older_than_seconds;
}

/**
 * Fail running jobs that appear abandoned after a crash or timeout.
 *
 * @param int $older_than_seconds Age threshold.
 * @return int Number of jobs recovered.
 */
function abcc_recover_stale_running_jobs( $older_than_seconds = 900 ) {
	$jobs = get_posts(
		array(
			'post_type'      => ABCC_Job::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_abcc_job_status',
					'value' => ABCC_Job::STATUS_RUNNING,
				),
			),
		)
	);

	if ( empty( $jobs ) ) {
		return 0;
	}

	$recovered = 0;

	foreach ( $jobs as $job ) {
		$started_at = get_post_meta( $job->ID, '_abcc_job_started_at', true );
		$reference  = $started_at ? $started_at : get_post_meta( $job->ID, '_abcc_job_created_at', true );

		if ( empty( $reference ) ) {
			continue;
		}

		$timestamp = strtotime( get_gmt_from_date( $reference ) );
		if ( ! $timestamp || ( time() - $timestamp ) <= (int) $older_than_seconds ) {
			continue;
		}

		abcc_mark_job_failed(
			$job->ID,
			__( 'Generation did not complete. The background process likely crashed or timed out.', 'automated-blog-content-creator' )
		);
		++$recovered;
	}

	return $recovered;
}

/**
 * Process a queued generation job.
 *
 * @param int $job_id Job ID.
 * @return void
 */
function abcc_process_generation_job( $job_id ) {
	$job_id = absint( $job_id );
	$started = false;

	if ( ! $job_id || ABCC_Job::POST_TYPE !== get_post_type( $job_id ) ) {
		return;
	}

	$status = get_post_meta( $job_id, '_abcc_job_status', true );
	if ( ABCC_Job::STATUS_RUNNING === $status || ABCC_Job::STATUS_SUCCESS === $status ) {
		return;
	}

	$payload = get_post_meta( $job_id, '_abcc_job_payload', true );
	if ( empty( $payload ) || ! is_array( $payload ) ) {
		abcc_mark_job_failed( $job_id, __( 'Invalid generation payload.', 'automated-blog-content-creator' ) );
		return;
	}

	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_RUNNING );
	update_post_meta( $job_id, '_abcc_job_started_at', current_time( 'mysql' ) );

	try {
		$started = microtime( true );
		$api_key = abcc_check_api_key( $payload['model'] ?? '' );

		if ( empty( $api_key ) ) {
			abcc_mark_job_failed( $job_id, __( 'API key not configured for the selected model.', 'automated-blog-content-creator' ), $started );
			return;
		}

		$result = abcc_openai_generate_post(
			$api_key,
			(array) ( $payload['keywords'] ?? array() ),
			$payload['model'] ?? abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' ),
			$payload['tone'] ?? abcc_get_setting( 'openai_tone', 'default' ),
			'scheduled' === ( $payload['source'] ?? '' ),
			(int) ( $payload['char_limit'] ?? abcc_get_setting( 'openai_char_limit', 200 ) ),
			$payload['post_type'] ?? 'post',
			$payload
		);

		if ( is_wp_error( $result ) ) {
			abcc_mark_job_failed( $job_id, $result->get_error_message(), $started );
			return;
		}

		$duration = microtime( true ) - $started;

		update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_SUCCESS );
		update_post_meta( $job_id, '_abcc_job_completed_at', current_time( 'mysql' ) );
		update_post_meta( $job_id, '_abcc_job_duration', round( $duration, 2 ) );
		update_post_meta( $job_id, '_abcc_job_result_post_id', (int) $result );
		delete_post_meta( $job_id, '_abcc_job_error' );
	} catch ( Throwable $throwable ) {
		error_log(
			sprintf(
				'Generation job %d crashed: %s',
				$job_id,
				$throwable->getMessage()
			)
		);
		abcc_mark_job_failed( $job_id, $throwable->getMessage(), $started );
	}
}
add_action( 'abcc_process_generation_job', 'abcc_process_generation_job' );

/**
 * Mark a generation job as failed.
 *
 * @param int         $job_id  Job ID.
 * @param string      $message Error message.
 * @param float|false $started Optional start timestamp.
 * @return void
 */
function abcc_mark_job_failed( $job_id, $message, $started = false ) {
	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_FAILED );
	update_post_meta( $job_id, '_abcc_job_completed_at', current_time( 'mysql' ) );
	update_post_meta( $job_id, '_abcc_job_error', sanitize_text_field( $message ) );

	if ( false !== $started ) {
		update_post_meta( $job_id, '_abcc_job_duration', round( microtime( true ) - $started, 2 ) );
	}

	if ( 'scheduled' === get_post_meta( $job_id, '_abcc_job_source', true ) && true === get_option( 'openai_email_notifications', false ) ) {
		wp_mail(
			get_option( 'admin_email' ),
			__( 'Scheduled Post Generation Failed', 'automated-blog-content-creator' ),
			sprintf(
				/* translators: %s: Error message */
				__( 'The scheduled post generation failed with error: %s', 'automated-blog-content-creator' ),
				$message
			)
		);
	}
}

/**
 * Get a formatted label for a generation source.
 *
 * @param string $source Source slug.
 * @return string
 */
function abcc_get_job_source_label( $source ) {
	$labels = array(
		'manual'     => __( 'Manual', 'automated-blog-content-creator' ),
		'scheduled'  => __( 'Scheduled', 'automated-blog-content-creator' ),
		'bulk'       => __( 'Bulk', 'automated-blog-content-creator' ),
		'regenerate' => __( 'Regenerate', 'automated-blog-content-creator' ),
		'legacy'     => __( 'Legacy', 'automated-blog-content-creator' ),
	);

	return $labels[ $source ] ?? ucfirst( (string) $source );
}

/**
 * Get a formatted label for a job status.
 *
 * @param string $status Status slug.
 * @return string
 */
function abcc_get_job_status_label( $status ) {
	$labels = array(
		ABCC_Job::STATUS_QUEUED  => __( 'Queued', 'automated-blog-content-creator' ),
		ABCC_Job::STATUS_RUNNING => __( 'Running', 'automated-blog-content-creator' ),
		ABCC_Job::STATUS_SUCCESS => __( 'Succeeded', 'automated-blog-content-creator' ),
		ABCC_Job::STATUS_FAILED  => __( 'Failed', 'automated-blog-content-creator' ),
	);

	return $labels[ $status ] ?? ucfirst( (string) $status );
}

/**
 * Render generation job log rows.
 *
 * @param array $args Query arguments.
 * @return string
 */
function abcc_render_job_log_rows( $args = array() ) {
	abcc_recover_stale_running_jobs();

	$defaults = array(
		'posts_per_page' => 10,
		'run_id'         => '',
		'status'         => '',
	);
	$args     = wp_parse_args( $args, $defaults );

	$query_args = array(
		'post_type'      => ABCC_Job::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $args['posts_per_page'],
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	if ( ! empty( $args['run_id'] ) ) {
		$query_args['meta_query'] = array(
			array(
				'key'   => '_abcc_job_run_id',
				'value' => sanitize_key( $args['run_id'] ),
			),
		);
	}

	if ( ! empty( $args['status'] ) ) {
		if ( empty( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = array();
		}

		$query_args['meta_query'][] = array(
			'key'   => '_abcc_job_status',
			'value' => sanitize_text_field( $args['status'] ),
		);
	}

	$jobs = get_posts( $query_args );

	ob_start();

	if ( empty( $jobs ) ) :
		// No job records yet — fall back to legacy generated posts on unfiltered views.
		if ( empty( $args['status'] ) && empty( $args['run_id'] ) ) {
			return abcc_render_legacy_history_rows( (int) $args['posts_per_page'] );
		}
		?>
		<tr>
			<td colspan="8"><?php esc_html_e( 'No generation jobs found.', 'automated-blog-content-creator' ); ?></td>
		</tr>
		<?php
	else :
		foreach ( $jobs as $job ) :
			$status         = get_post_meta( $job->ID, '_abcc_job_status', true );
			$source         = get_post_meta( $job->ID, '_abcc_job_source', true );
			$model          = get_post_meta( $job->ID, '_abcc_job_model', true );
			$keywords       = (array) get_post_meta( $job->ID, '_abcc_job_keywords', true );
			$template       = get_post_meta( $job->ID, '_abcc_job_template', true );
			$duration       = get_post_meta( $job->ID, '_abcc_job_duration', true );
			$result_post_id = (int) get_post_meta( $job->ID, '_abcc_job_result_post_id', true );
			$error_message  = get_post_meta( $job->ID, '_abcc_job_error', true );
			$created_at     = get_post_meta( $job->ID, '_abcc_job_created_at', true );
			?>
			<tr data-job-id="<?php echo esc_attr( $job->ID ); ?>">
				<td>
					<span class="abcc-job-status-badge abcc-job-status-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( abcc_get_job_status_label( $status ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( abcc_get_job_source_label( $source ) ); ?></td>
				<td><small><?php echo esc_html( $model ? $model : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( ! empty( $keywords ) ? implode( ', ', $keywords ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( $template ? $template : 'default' ); ?></small></td>
				<td><small><?php echo esc_html( $created_at ? get_date_from_gmt( get_gmt_from_date( $created_at ), 'Y-m-d H:i' ) : 'n/a' ); ?></small></td>
				<td>
					<?php
					if ( '' !== (string) $duration ) {
						printf(
							/* translators: %s: runtime in seconds. */
							esc_html__( '%ss', 'automated-blog-content-creator' ),
							esc_html( $duration )
						);
					} else {
						echo '&mdash;';
					}
					?>
				</td>
				<td>
					<?php if ( $result_post_id ) : ?>
						<strong>
							<a href="<?php echo esc_url( get_edit_post_link( $result_post_id ) ); ?>">
								<?php echo esc_html( get_the_title( $result_post_id ) ); ?>
							</a>
						</strong>
						<div class="row-actions">
							<span class="edit">
								<a href="<?php echo esc_url( get_edit_post_link( $result_post_id ) ); ?>">
									<?php esc_html_e( 'Edit', 'automated-blog-content-creator' ); ?>
								</a> |
							</span>
							<span class="view">
								<a href="<?php echo esc_url( get_permalink( $result_post_id ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View', 'automated-blog-content-creator' ); ?>
								</a> |
							</span>
							<span class="abcc-regenerate-row">
								<a href="#" class="abcc-regenerate-post" data-post-id="<?php echo esc_attr( $result_post_id ); ?>">
									<?php esc_html_e( 'Regenerate', 'automated-blog-content-creator' ); ?>
								</a>
							</span>
						</div>
					<?php elseif ( ! empty( $error_message ) ) : ?>
						<span class="abcc-job-error"><?php echo esc_html( $error_message ); ?></span>
						<br>
						<button type="button" class="button-link abcc-copy-error" data-error="<?php echo esc_attr( $error_message ); ?>">
							<?php esc_html_e( 'Copy error', 'automated-blog-content-creator' ); ?>
						</button>
						|
						<?php
						$subject = rawurlencode( __( 'WP-AutoInsight generation error', 'automated-blog-content-creator' ) );
						$body    = rawurlencode( $error_message );
						?>
						<a href="mailto:phalkmin@protonmail.com?subject=<?php echo esc_attr( $subject ); ?>&body=<?php echo esc_attr( $body ); ?>">
							<?php esc_html_e( 'Report this error', 'automated-blog-content-creator' ); ?>
						</a>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
				</td>
			</tr>
			<?php
		endforeach;
	endif;

	return (string) ob_get_clean();
}

/**
 * Render legacy generated-post rows for sites upgrading from pre-3.7.
 *
 * Queries posts with _abcc_generated=1 that predate the job system and renders
 * them in the 8-column job log format so the table is not blank after upgrade.
 *
 * @param int $limit Maximum rows to render.
 * @return string
 */
function abcc_render_legacy_history_rows( $limit = 10 ) {
	$posts = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => '_abcc_generated',
			'meta_value'     => '1',
		)
	);

	ob_start();

	if ( empty( $posts ) ) :
		?>
		<tr>
			<td colspan="8"><?php esc_html_e( 'No generation jobs found.', 'automated-blog-content-creator' ); ?></td>
		</tr>
		<?php
	else :
		foreach ( $posts as $post ) :
			$model    = get_post_meta( $post->ID, '_abcc_model', true );
			$params   = json_decode( get_post_meta( $post->ID, '_abcc_generation_params', true ), true );
			$keywords = isset( $params['keywords'] ) ? (array) $params['keywords'] : array();
			$template = isset( $params['template'] ) ? $params['template'] : 'default';
			?>
			<tr>
				<td>
					<span class="abcc-job-status-badge abcc-job-status-badge--succeeded">
						<?php esc_html_e( 'Succeeded', 'automated-blog-content-creator' ); ?>
					</span>
				</td>
				<td><?php esc_html_e( 'Legacy', 'automated-blog-content-creator' ); ?></td>
				<td><small><?php echo esc_html( $model ? $model : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( ! empty( $keywords ) ? implode( ', ', $keywords ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( $template ); ?></small></td>
				<td><small><?php echo esc_html( get_the_date( 'Y-m-d H:i', $post ) ); ?></small></td>
				<td>&mdash;</td>
				<td>
					<strong>
						<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
							<?php echo esc_html( $post->post_title ); ?>
						</a>
					</strong>
					<div class="row-actions">
						<span class="edit">
							<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
								<?php esc_html_e( 'Edit', 'automated-blog-content-creator' ); ?>
							</a> |
						</span>
						<span class="view">
							<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'View', 'automated-blog-content-creator' ); ?>
							</a> |
						</span>
						<span class="abcc-regenerate-row">
							<a href="#" class="abcc-regenerate-post" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
								<?php esc_html_e( 'Regenerate', 'automated-blog-content-creator' ); ?>
							</a>
						</span>
					</div>
				</td>
			</tr>
			<?php
		endforeach;
	endif;

	return (string) ob_get_clean();
}

/**
 * Return job data for AJAX polling.
 *
 * @param int $job_id Job ID.
 * @return array|null
 */
function abcc_get_job_data( $job_id ) {
	abcc_recover_stale_running_jobs();

	$job_id = absint( $job_id );

	if ( ! $job_id || ABCC_Job::POST_TYPE !== get_post_type( $job_id ) ) {
		return null;
	}

	$result_post_id = (int) get_post_meta( $job_id, '_abcc_job_result_post_id', true );

	return array(
		'id'          => $job_id,
		'status'      => get_post_meta( $job_id, '_abcc_job_status', true ),
		'statusLabel' => abcc_get_job_status_label( get_post_meta( $job_id, '_abcc_job_status', true ) ),
		'message'     => get_post_meta( $job_id, '_abcc_job_error', true ),
		'post_id'     => $result_post_id,
		'edit_url'    => $result_post_id ? get_edit_post_link( $result_post_id, 'raw' ) : '',
	);
}
