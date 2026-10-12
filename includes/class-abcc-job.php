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

	// Generation jobs carry their publication decision from here on; a topic
	// deleted or a default flipped while the job waits must not change it.
	if ( empty( $payload['type'] ) ) {
		$payload = abcc_finalize_payload_post_status( $payload, $created_by );
	}

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

	$process_inline = ! empty( $args['process_inline'] );

	if ( $process_inline ) {
		// Caller (e.g. bulk-generate AJAX) processes the job itself; do not
		// also schedule a cron worker that could claim it first.
		abcc_process_generation_job( $job_id );
	} elseif ( abcc_should_defer_job_to_cron() ) {
		$scheduled = wp_schedule_single_event( time(), 'abcc_process_generation_job', array( $job_id ) );

		if ( ! $scheduled || is_wp_error( $scheduled ) ) {
			abcc_append_job_log_note( $job_id, __( 'Background scheduling failed; ran immediately instead.', 'automated-blog-content-creator' ) );
			abcc_process_generation_job( $job_id );
		} elseif ( abcc_is_wp_cron_available() && empty( $args['defer_spawn'] ) ) {
			// Batch callers (bulk SEO, bulk generate) pass defer_spawn and call
			// spawn_cron() once after the whole batch is queued.
			spawn_cron();
		}
	} else {
		// Page-load cron is off and this is an interactive request: nothing
		// guarantees a cron runner exists, so finish the job now.
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
 * Whether a queued job should run as its own cron event.
 *
 * Inside a cron request a runner exists even when DISABLE_WP_CRON is set
 * (external cron hits wp-cron.php), so each job gets its own event instead
 * of running back-to-back inside the topic sweep.
 *
 * @since 4.5.1
 * @return bool
 */
function abcc_should_defer_job_to_cron() {
	return abcc_is_wp_cron_available() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
}

/**
 * Fail jobs that appear abandoned: running past a timeout, or queued past
 * the point where a worker could still be coming.
 *
 * Queued jobs stall when the WP-Cron loopback is blocked. Batch runs
 * (bulk SEO) legitimately queue hundreds at once, so they get a longer window.
 *
 * @param int       $older_than_seconds              Running-job age threshold.
 * @param int       $queued_older_than_seconds       Queued-job threshold.
 * @param int       $batch_queued_older_than_seconds Queued threshold for batch jobs (run_id or bulk SEO).
 * @param bool|null $external_cron                   Whether DISABLE_WP_CRON hands jobs to an
 *                                                   external cron; null reads the constant.
 * @return int Number of jobs recovered.
 */
function abcc_recover_stale_running_jobs( $older_than_seconds = 900, $queued_older_than_seconds = 1800, $batch_queued_older_than_seconds = 10800, $external_cron = null ) {
	// External cron cadence is unknown (often hourly), so every job gets the batch window.
	$external_cron = null === $external_cron ? ! abcc_is_wp_cron_available() : (bool) $external_cron;

	$jobs = get_posts(
		array(
			'post_type'      => ABCC_Job::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded hidden CPT.
				array(
					'key'     => '_abcc_job_status',
					'value'   => array( ABCC_Job::STATUS_RUNNING, ABCC_Job::STATUS_QUEUED ),
					'compare' => 'IN',
				),
			),
		)
	);

	if ( empty( $jobs ) ) {
		return 0;
	}

	$recovered = 0;
	$spawned   = false;

	foreach ( $jobs as $job ) {
		$status     = get_post_meta( $job->ID, '_abcc_job_status', true );
		$started_at = get_post_meta( $job->ID, '_abcc_job_started_at', true );
		$reference  = $started_at ? $started_at : get_post_meta( $job->ID, '_abcc_job_created_at', true );

		if ( empty( $reference ) ) {
			continue;
		}

		$timestamp = strtotime( get_gmt_from_date( $reference ) );
		if ( ! $timestamp ) {
			continue;
		}

		if ( ABCC_Job::STATUS_QUEUED === $status ) {
			// Bulk SEO jobs queued before 4.5.2 carry no run_id but are still a batch.
			$is_batch  = '' !== (string) get_post_meta( $job->ID, '_abcc_job_run_id', true )
				|| 'seo_regen' === get_post_meta( $job->ID, '_abcc_job_source', true );
			$threshold = ( $is_batch || $external_cron ) ? (int) $batch_queued_older_than_seconds : (int) $queued_older_than_seconds;
			if ( ( time() - $timestamp ) <= $threshold ) {
				continue;
			}
			// First sighting re-spawns cron and waits: the request that found the job may be
			// the one whose loopback is about to run it. Only a job still queued later fails.
			$seen = (int) get_post_meta( $job->ID, '_abcc_job_stale_seen', true );
			if ( ! $seen ) {
				update_post_meta( $job->ID, '_abcc_job_stale_seen', time() );
				if ( ! $spawned && abcc_is_wp_cron_available() && function_exists( 'spawn_cron' ) ) {
					$spawned = true;
					spawn_cron();
				}
				continue;
			}
			if ( ( time() - $seen ) < 2 * MINUTE_IN_SECONDS ) {
				continue;
			}
			wp_clear_scheduled_hook( 'abcc_process_generation_job', array( $job->ID ) );
			abcc_mark_job_failed(
				$job->ID,
				__( 'This job was queued but never started. The background runner may be disabled or WP-Cron may not be firing.', 'automated-blog-content-creator' ),
				false,
				'abcc_job_never_started'
			);
			++$recovered;
			continue;
		}

		if ( ( time() - $timestamp ) <= (int) $older_than_seconds ) {
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
	$job_id  = absint( $job_id );
	$started = false;

	if ( ! $job_id || ABCC_Job::POST_TYPE !== get_post_type( $job_id ) ) {
		return;
	}

	$status = get_post_meta( $job_id, '_abcc_job_status', true );
	if ( ABCC_Job::STATUS_RUNNING === $status || ABCC_Job::STATUS_SUCCESS === $status ) {
		return;
	}

	// Claim the job atomically: cron worker and inline bulk handler can both reach
	// this point with STATUS_QUEUED, so the status flip must be a conditional UPDATE.
	global $wpdb;
	$claimed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic claim requires a conditional UPDATE; meta cache flushed below.
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = %s
			 WHERE post_id = %d AND meta_key = '_abcc_job_status' AND meta_value = %s",
			ABCC_Job::STATUS_RUNNING,
			$job_id,
			ABCC_Job::STATUS_QUEUED
		)
	);

	if ( ! $claimed ) {
		return; // Another worker already claimed it.
	}

	wp_cache_delete( $job_id, 'post_meta' );

	$payload = get_post_meta( $job_id, '_abcc_job_payload', true );
	if ( empty( $payload ) || ! is_array( $payload ) ) {
		abcc_mark_job_failed( $job_id, __( 'Invalid generation payload.', 'automated-blog-content-creator' ) );
		return;
	}

	// Preserve the intended post author for background/cron execution.
	$created_by = (int) get_post_meta( $job_id, '_abcc_job_created_by', true );
	if ( $created_by ) {
		$payload['post_author'] = $created_by;
	}

	// Thread the job ID through so the citation transient is keyed per job,
	// not per user (cron has no current user; see abcc_citation_transient_key()).
	$payload['job_id'] = $job_id;

	update_post_meta( $job_id, '_abcc_job_started_at', current_time( 'mysql' ) );

	try {
		$started = microtime( true );

		// Jobs queued before the site turned AI off must not reach a provider.
		if ( ! abcc_site_ai_enabled() ) {
			abcc_mark_job_failed( $job_id, __( 'AI features are turned off for this site.', 'automated-blog-content-creator' ), $started, 'abcc_ai_disabled' );
			return;
		}

		// SEO-regen jobs update an existing post in place.
		// @since 4.3.0
		if ( isset( $payload['type'] ) && 'seo_regen' === $payload['type'] ) {
			$target_post_id = isset( $payload['post_id'] ) ? (int) $payload['post_id'] : 0;
			$model          = isset( $payload['model'] ) ? $payload['model'] : '';
			$regen          = abcc_run_seo_regen( $target_post_id, $model );

			if ( is_wp_error( $regen ) ) {
				abcc_mark_job_failed( $job_id, $regen->get_error_message(), $started, $regen->get_error_code() );
				return;
			}

			$duration = microtime( true ) - $started;
			update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_SUCCESS );
			update_post_meta( $job_id, '_abcc_job_completed_at', current_time( 'mysql' ) );
			update_post_meta( $job_id, '_abcc_job_duration', round( $duration, 2 ) );
			update_post_meta( $job_id, '_abcc_job_result_post_id', $target_post_id );
			delete_post_meta( $job_id, '_abcc_job_error' );
			abcc_mark_job_succeeded_for_topic( abcc_get_job_topic_id( $job_id ) );
			return;
		}

		$api_key = abcc_check_api_key( $payload['model'] ?? '' );

		if ( empty( $api_key ) ) {
			abcc_mark_job_failed( $job_id, __( 'API key not configured for the selected model.', 'automated-blog-content-creator' ), $started, 'abcc_no_api_key' );
			return;
		}

		$result = abcc_openai_generate_post(
			$api_key,
			(array) ( $payload['keywords'] ?? array() ),
			$payload['model'] ?? abcc_get_setting( 'prompt_select', abcc_get_default_text_model() ),
			$payload['tone'] ?? abcc_get_setting( 'openai_tone', 'default' ),
			'scheduled' === ( $payload['source'] ?? '' ),
			(int) ( $payload['char_limit'] ?? abcc_get_setting( 'openai_char_limit', 200 ) ),
			$payload['post_type'] ?? 'post',
			$payload
		);

		if ( is_wp_error( $result ) ) {
			// The provider code rides in error data; the top-level code is generic.
			$error_data = $result->get_error_data();
			$error_code = is_array( $error_data ) && ! empty( $error_data['code'] ) ? (string) $error_data['code'] : (string) $result->get_error_code();
			abcc_mark_job_failed( $job_id, $result->get_error_message(), $started, $error_code );
			return;
		}

		$duration = microtime( true ) - $started;

		update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_SUCCESS );
		update_post_meta( $job_id, '_abcc_job_completed_at', current_time( 'mysql' ) );
		update_post_meta( $job_id, '_abcc_job_duration', round( $duration, 2 ) );
		update_post_meta( $job_id, '_abcc_job_result_post_id', (int) $result );
		delete_post_meta( $job_id, '_abcc_job_error' );
		abcc_mark_job_succeeded_for_topic( abcc_get_job_topic_id( $job_id ) );
	} catch ( Throwable $throwable ) {
		abcc_debug_log(
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
 * Append an informational note to a job.
 *
 * Distinct from _abcc_job_error, which marks a failure — notes attach to
 * successful jobs too (e.g. "completed with truncation repair").
 *
 * @since 4.4.0
 * @param int    $job_id Job ID.
 * @param string $note   Human-readable note.
 * @return void
 */
function abcc_append_job_log_note( $job_id, $note ) {
	$notes = get_post_meta( (int) $job_id, '_abcc_job_notes', true );
	$notes = is_array( $notes ) ? $notes : array();

	$notes[] = sanitize_text_field( $note );

	update_post_meta( (int) $job_id, '_abcc_job_notes', $notes );
}

/**
 * Topic a job was queued for, or 0.
 *
 * @since 4.5.2
 * @param int $job_id Job ID.
 * @return int
 */
function abcc_get_job_topic_id( $job_id ) {
	$payload = get_post_meta( (int) $job_id, '_abcc_job_payload', true );
	return is_array( $payload ) && ! empty( $payload['topic_id'] ) ? (int) $payload['topic_id'] : 0;
}

/**
 * A topic's job finished: clear its failure streak.
 *
 * @since 4.5.2
 * @param int $topic_id Topic ID.
 * @return void
 */
function abcc_mark_job_succeeded_for_topic( $topic_id ) {
	$topic_id = (int) $topic_id;
	if ( $topic_id > 0 && function_exists( 'abcc_get_topic' ) && null !== abcc_get_topic( $topic_id ) ) {
		update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 0 );
	}
}

/**
 * Mark a generation job as failed.
 *
 * @param int         $job_id  Job ID.
 * @param string      $message Error message.
 * @param float|false $started Optional start timestamp.
 * @param string      $code    Optional. Machine error code driving the next-step hint.
 * @return void
 */
function abcc_mark_job_failed( $job_id, $message, $started = false, $code = '' ) {
	update_post_meta( $job_id, '_abcc_job_status', ABCC_Job::STATUS_FAILED );
	update_post_meta( $job_id, '_abcc_job_completed_at', current_time( 'mysql' ) );
	// Provider error text can echo the key; the stored copy is shown in the dashboard.
	$stored_message = function_exists( 'abcc_redact_secrets' ) ? abcc_redact_secrets( (string) $message ) : (string) $message;
	update_post_meta( $job_id, '_abcc_job_error', sanitize_text_field( str_replace( array( '→', '←' ), array( '>', '<' ), $stored_message ) ) );
	update_post_meta( $job_id, '_abcc_job_error_code', sanitize_key( (string) $code ) );

	if ( false !== $started ) {
		update_post_meta( $job_id, '_abcc_job_duration', round( microtime( true ) - $started, 2 ) );
	}

	$source = get_post_meta( $job_id, '_abcc_job_source', true );

	// AI off, a missing key and a runner that never fired are site states, not
	// the topic failing; they shouldn't burn through its auto-pause budget.
	$counts_toward_pause = ! in_array( (string) $code, array( 'abcc_ai_disabled', 'abcc_no_api_key', 'abcc_job_never_started' ), true );
	$topic_id            = abcc_get_job_topic_id( $job_id );
	if ( $topic_id > 0 && $counts_toward_pause && function_exists( 'abcc_record_topic_failure' ) ) {
		abcc_record_topic_failure( $topic_id, $message );
	}

	if ( in_array( $source, array( 'scheduled', 'topic' ), true ) && abcc_get_setting( 'openai_email_notifications', false ) ) {
		abcc_send_job_failure_email( (string) $source, $topic_id, (string) $code, (string) $message );
	}
}

/**
 * E-mail the admin about a background failure, once per source, topic and code a day.
 *
 * A revoked key fails every run; one mail a day says it, 24 say nothing more.
 *
 * @since 4.5.2
 * @param string $source   Job source ('scheduled' or 'topic').
 * @param int    $topic_id Topic ID, or 0.
 * @param string $code     Machine error code.
 * @param string $message  Error message.
 * @return void
 */
function abcc_send_job_failure_email( $source, $topic_id, $code, $message ) {
	$dedupe_key = 'abcc_fail_mail_' . md5( $source . '|' . (int) $topic_id . '|' . $code );
	if ( get_transient( $dedupe_key ) ) {
		return;
	}

	$subject = __( 'Scheduled Post Generation Failed', 'automated-blog-content-creator' );
	$topic   = ( 'topic' === $source && $topic_id > 0 && function_exists( 'abcc_get_topic' ) ) ? abcc_get_topic( $topic_id ) : null;
	if ( ! empty( $topic['title'] ) ) {
		$subject = sprintf(
			/* translators: %s: topic name */
			__( 'Topic post generation failed: %s', 'automated-blog-content-creator' ),
			$topic['title']
		);
	}

	$sent = wp_mail(
		get_option( 'admin_email' ),
		$subject,
		sprintf(
			/* translators: %s: Error message */
			__( 'The scheduled post generation failed with error: %s', 'automated-blog-content-creator' ),
			$message
		)
	);

	// A failed send must not silence the next notice for a day.
	if ( $sent ) {
		set_transient( $dedupe_key, 1, DAY_IN_SECONDS );
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
		'seo_regen'  => __( 'SEO Regen', 'automated-blog-content-creator' ),
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

	// Non-admins see only the jobs they queued.
	if ( ! current_user_can( 'manage_options' ) ) {
		if ( empty( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = array();
		}
		$query_args['meta_query'][] = array(
			'key'   => '_abcc_job_created_by',
			'value' => (string) get_current_user_id(),
		);
	}

	$jobs = get_posts( $query_args );

	// Legacy rows list every author's posts, so only administrators get the fallback.
	if ( empty( $jobs ) && empty( $args['status'] ) && empty( $args['run_id'] ) && current_user_can( 'manage_options' ) ) {
		return abcc_render_legacy_history_rows( (int) $args['posts_per_page'] );
	}

	ob_start();

	if ( empty( $jobs ) ) :
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
			$next_step      = abcc_get_generation_error_next_step( (string) get_post_meta( $job->ID, '_abcc_job_error_code', true ) );
			$job_notes      = get_post_meta( $job->ID, '_abcc_job_notes', true );
			$job_notes      = is_array( $job_notes ) ? $job_notes : array();
			$created_at     = get_post_meta( $job->ID, '_abcc_job_created_at', true );
			?>
			<tr data-job-id="<?php echo esc_attr( $job->ID ); ?>">
				<td>
					<span class="abcc-job-status-badge abcc-job-status-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( abcc_get_job_status_label( $status ) ); ?>
					</span>
				</td>
				<td><?php echo esc_html( abcc_get_job_source_label( $source ) ); ?></td>
				<td><small><?php echo esc_html( $model ? abcc_get_model_display_name( $model ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( ! empty( $keywords ) ? implode( ', ', $keywords ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( abcc_get_content_template_name( $template ? $template : 'default' ) ); ?></small></td>
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
							<a href="<?php echo esc_url( (string) get_edit_post_link( $result_post_id ) ); ?>">
								<?php echo esc_html( get_the_title( $result_post_id ) ); ?>
							</a>
						</strong>
						<div class="row-actions">
							<span class="edit">
								<a href="<?php echo esc_url( (string) get_edit_post_link( $result_post_id ) ); ?>">
									<?php esc_html_e( 'Edit', 'automated-blog-content-creator' ); ?>
								</a> |
							</span>
							<span class="view">
								<a href="<?php echo esc_url( (string) get_permalink( $result_post_id ) ); ?>" target="_blank" rel="noopener noreferrer">
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
						<?php if ( '' !== $next_step ) : ?>
							<br><small class="abcc-job-next-step"><?php echo esc_html( $next_step ); ?></small>
						<?php endif; ?>
						<br>
						<button type="button" class="button-link abcc-copy-error" data-error="<?php echo esc_attr( $error_message ); ?>">
							<?php esc_html_e( 'Copy error', 'automated-blog-content-creator' ); ?>
						</button>
						<?php if ( abcc_should_offer_error_report( (string) get_post_meta( $job->ID, '_abcc_job_error_code', true ) ) ) : ?>
							|
							<a href="https://wordpress.org/support/plugin/automated-blog-content-creator/" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Report on the support forum', 'automated-blog-content-creator' ); ?>
							</a>
						<?php endif; ?>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
					<?php
					foreach ( $job_notes as $job_note ) :
						// Fallback notes carry the "→" from abcc_execute_fallback_chain().
						$note_class = false !== strpos( (string) $job_note, '→' ) ? 'abcc-job-note abcc-job-note--fallback' : 'abcc-job-note';
						?>
						<br><small class="<?php echo esc_attr( $note_class ); ?>"><?php echo esc_html( $job_note ); ?></small>
					<?php endforeach; ?>
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
				<td><small><?php echo esc_html( $model ? abcc_get_model_display_name( $model ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( ! empty( $keywords ) ? implode( ', ', $keywords ) : 'n/a' ); ?></small></td>
				<td><small><?php echo esc_html( $template ); ?></small></td>
				<td><small><?php echo esc_html( get_the_date( 'Y-m-d H:i', $post ) ); ?></small></td>
				<td>&mdash;</td>
				<td>
					<strong>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>">
							<?php echo esc_html( $post->post_title ); ?>
						</a>
					</strong>
					<div class="row-actions">
						<span class="edit">
							<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>">
								<?php esc_html_e( 'Edit', 'automated-blog-content-creator' ); ?>
							</a> |
						</span>
						<span class="view">
							<a href="<?php echo esc_url( (string) get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
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
 * Whether a user may read a job's status and log entry.
 *
 * Cron-created jobs carry the topic author or the scheduled-post author as
 * created_by; administrators see every job.
 *
 * @since 4.5.2
 * @param int $job_id  Job post ID.
 * @param int $user_id Optional. 0 means the current user.
 * @return bool
 */
function abcc_user_can_view_job( $job_id, $user_id = 0 ) {
	$job_id  = absint( $job_id );
	$user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();

	if ( ! $job_id || ABCC_Job::POST_TYPE !== get_post_type( $job_id ) ) {
		return false;
	}

	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	return $user_id > 0 && (int) get_post_meta( $job_id, '_abcc_job_created_by', true ) === $user_id;
}

/**
 * Return job data for AJAX polling.
 *
 * @param int $job_id Job ID.
 * @return array|null
 */
function abcc_get_job_data( $job_id ) {
	// Pollers call this every second; one recovery sweep a minute is plenty.
	if ( false === get_transient( 'abcc_job_recovery_ran' ) ) {
		set_transient( 'abcc_job_recovery_ran', 1, MINUTE_IN_SECONDS );
		abcc_recover_stale_running_jobs();
	}

	$job_id = absint( $job_id );

	if ( ! $job_id || ABCC_Job::POST_TYPE !== get_post_type( $job_id ) ) {
		return null;
	}

	$result_post_id = (int) get_post_meta( $job_id, '_abcc_job_result_post_id', true );
	$job_notes      = get_post_meta( $job_id, '_abcc_job_notes', true );

	return array(
		'id'          => $job_id,
		'status'      => get_post_meta( $job_id, '_abcc_job_status', true ),
		'statusLabel' => abcc_get_job_status_label( get_post_meta( $job_id, '_abcc_job_status', true ) ),
		'message'     => get_post_meta( $job_id, '_abcc_job_error', true ),
		'nextStep'    => abcc_get_generation_error_next_step( (string) get_post_meta( $job_id, '_abcc_job_error_code', true ) ),
		'notes'       => is_array( $job_notes ) ? $job_notes : array(),
		'post_id'     => $result_post_id,
		'edit_url'    => $result_post_id && current_user_can( 'edit_post', $result_post_id ) ? get_edit_post_link( $result_post_id, 'raw' ) : '',
	);
}
