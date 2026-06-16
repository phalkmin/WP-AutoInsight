<?php
/**
 * Topic Library — reusable generation topics with per-topic scheduling.
 *
 * Topics are stored as a hidden custom post type (mirroring the abcc_job
 * pattern): post_title = topic name, post_content = generation prompt,
 * post_status publish/draft = active/paused. Per-topic configuration lives
 * in post meta:
 *
 * - _abcc_topic_frequency             hourly|every_2h|every_6h|daily|weekly
 * - _abcc_topic_post_status_override  '' (inherit) | draft | publish
 * - _abcc_topic_provider_override     '' (inherit) | provider/model key
 * - _abcc_topic_target_post_type     defaults to 'post'
 * - _abcc_topic_author_id            owner (cron user context)
 * - _abcc_topic_last_run             unix timestamp
 * - _abcc_topic_next_run             unix timestamp
 * - _abcc_topic_last_job_id          most recent queued job
 * - _abcc_topic_consecutive_failures auto-pause counter
 *
 * Runs alongside the existing single-prompt scheduling (no migration).
 *
 * @since 4.2.0
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The topic post type slug.
 */
define( 'ABCC_TOPIC_POST_TYPE', 'abcc_topic' );

/**
 * Register the hidden topic post type.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_register_topic_post_type() {
	register_post_type(
		ABCC_TOPIC_POST_TYPE,
		array(
			'labels'              => array(
				'name' => __( 'Generation Topics', 'automated-blog-content-creator' ),
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
add_action( 'init', 'abcc_register_topic_post_type' );

/**
 * Get the valid topic frequencies and their intervals in seconds.
 *
 * @since 4.2.0
 * @return array Frequency slug => interval in seconds.
 */
function abcc_get_topic_frequencies() {
	return array(
		'hourly'   => HOUR_IN_SECONDS,
		'every_2h' => 2 * HOUR_IN_SECONDS,
		'every_6h' => 6 * HOUR_IN_SECONDS,
		'daily'    => DAY_IN_SECONDS,
		'weekly'   => 7 * DAY_IN_SECONDS,
	);
}

/**
 * Calculate the next run timestamp for a frequency.
 *
 * @since 4.2.0
 * @param string $frequency Frequency slug (hourly|every_2h|every_6h|daily|weekly).
 * @param int    $from      Base timestamp; 0 means now. Explicit for testability.
 * @return int Unix timestamp of the next run. Unknown frequencies fall back to daily.
 */
function abcc_calculate_next_run( $frequency, $from = 0 ) {
	$frequencies = abcc_get_topic_frequencies();
	$interval    = isset( $frequencies[ $frequency ] ) ? $frequencies[ $frequency ] : DAY_IN_SECONDS;
	$from        = $from > 0 ? (int) $from : time();

	return $from + $interval;
}

/**
 * Resolve the post status for a topic's generated posts.
 *
 * Thin wrapper over the Unit C resolver so topic callers don't build context
 * arrays by hand.
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return string 'draft' or 'publish'.
 */
function abcc_resolve_topic_post_status( $topic_id ) {
	return abcc_resolve_post_status( array( 'topic_id' => (int) $topic_id ) );
}

/**
 * Validate and normalize topic arguments shared by create and update.
 *
 * @since 4.2.0
 * @param array $args     Raw topic arguments.
 * @param bool  $partial  Whether missing keys are allowed (update).
 * @return array|WP_Error Normalized arguments, or WP_Error on invalid input.
 */
function abcc_validate_topic_args( $args, $partial = false ) {
	$clean = array();

	if ( isset( $args['title'] ) || ! $partial ) {
		$clean['title'] = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		if ( '' === $clean['title'] ) {
			return new WP_Error( 'abcc_topic_invalid_title', __( 'Topic name is required.', 'automated-blog-content-creator' ) );
		}
	}

	if ( isset( $args['prompt'] ) || ! $partial ) {
		$clean['prompt'] = isset( $args['prompt'] ) ? sanitize_textarea_field( $args['prompt'] ) : '';
		if ( '' === $clean['prompt'] ) {
			return new WP_Error( 'abcc_topic_invalid_prompt', __( 'Topic prompt is required.', 'automated-blog-content-creator' ) );
		}
	}

	if ( isset( $args['frequency'] ) || ! $partial ) {
		$frequency   = isset( $args['frequency'] ) ? sanitize_key( $args['frequency'] ) : '';
		$frequencies = abcc_get_topic_frequencies();
		if ( ! isset( $frequencies[ $frequency ] ) ) {
			return new WP_Error( 'abcc_topic_invalid_frequency', __( 'Invalid topic frequency.', 'automated-blog-content-creator' ) );
		}
		$clean['frequency'] = $frequency;
	}

	if ( isset( $args['post_status_override'] ) ) {
		$override = sanitize_key( $args['post_status_override'] );
		if ( ! in_array( $override, array( '', 'draft', 'publish' ), true ) ) {
			return new WP_Error( 'abcc_topic_invalid_status_override', __( 'Invalid post status override.', 'automated-blog-content-creator' ) );
		}
		$clean['post_status_override'] = $override;
	}

	if ( isset( $args['provider_override'] ) ) {
		$clean['provider_override'] = sanitize_text_field( $args['provider_override'] );
	}

	if ( isset( $args['target_post_type'] ) ) {
		$clean['target_post_type'] = sanitize_key( $args['target_post_type'] );
		if ( '' === $clean['target_post_type'] ) {
			$clean['target_post_type'] = 'post';
		}
	}

	if ( isset( $args['author_id'] ) ) {
		$clean['author_id'] = absint( $args['author_id'] );
	}

	return $clean;
}

/**
 * Create a topic.
 *
 * @since 4.2.0
 * @param array $args Topic arguments: title, prompt, frequency (required);
 *                    post_status_override, provider_override,
 *                    target_post_type, author_id (optional).
 * @return int|WP_Error Topic ID on success.
 */
function abcc_create_topic( $args ) {
	$clean = abcc_validate_topic_args( $args, false );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}

	$topic_id = wp_insert_post(
		array(
			'post_type'    => ABCC_TOPIC_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $clean['title'],
			'post_content' => $clean['prompt'],
		),
		true
	);

	if ( is_wp_error( $topic_id ) ) {
		return $topic_id;
	}

	update_post_meta( $topic_id, '_abcc_topic_frequency', $clean['frequency'] );
	update_post_meta( $topic_id, '_abcc_topic_post_status_override', $clean['post_status_override'] ?? '' );
	update_post_meta( $topic_id, '_abcc_topic_provider_override', $clean['provider_override'] ?? '' );
	update_post_meta( $topic_id, '_abcc_topic_target_post_type', $clean['target_post_type'] ?? 'post' );
	update_post_meta( $topic_id, '_abcc_topic_author_id', isset( $clean['author_id'] ) ? $clean['author_id'] : get_current_user_id() );
	update_post_meta( $topic_id, '_abcc_topic_last_run', 0 );
	update_post_meta( $topic_id, '_abcc_topic_next_run', abcc_calculate_next_run( $clean['frequency'] ) );
	update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 0 );

	return $topic_id;
}

/**
 * Update a topic.
 *
 * @since 4.2.0
 * @param int   $topic_id Topic ID.
 * @param array $args     Partial topic arguments (only provided keys change).
 * @return bool|WP_Error True on success.
 */
function abcc_update_topic( $topic_id, $args ) {
	$topic_id = absint( $topic_id );
	if ( ! $topic_id || ABCC_TOPIC_POST_TYPE !== get_post_type( $topic_id ) ) {
		return new WP_Error( 'abcc_topic_not_found', __( 'Topic not found.', 'automated-blog-content-creator' ) );
	}

	$clean = abcc_validate_topic_args( $args, true );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}

	$post_fields = array();
	if ( isset( $clean['title'] ) ) {
		$post_fields['post_title'] = $clean['title'];
	}
	if ( isset( $clean['prompt'] ) ) {
		$post_fields['post_content'] = $clean['prompt'];
	}

	if ( ! empty( $post_fields ) ) {
		$post_fields['ID'] = $topic_id;
		$updated           = wp_update_post( $post_fields, true );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
	}

	if ( isset( $clean['frequency'] ) ) {
		$previous = get_post_meta( $topic_id, '_abcc_topic_frequency', true );
		update_post_meta( $topic_id, '_abcc_topic_frequency', $clean['frequency'] );
		if ( $previous !== $clean['frequency'] ) {
			update_post_meta( $topic_id, '_abcc_topic_next_run', abcc_calculate_next_run( $clean['frequency'] ) );
		}
	}

	$meta_map = array(
		'post_status_override' => '_abcc_topic_post_status_override',
		'provider_override'    => '_abcc_topic_provider_override',
		'target_post_type'     => '_abcc_topic_target_post_type',
		'author_id'            => '_abcc_topic_author_id',
	);
	foreach ( $meta_map as $arg_key => $meta_key ) {
		if ( isset( $clean[ $arg_key ] ) ) {
			update_post_meta( $topic_id, $meta_key, $clean[ $arg_key ] );
		}
	}

	return true;
}

/**
 * Delete a topic.
 *
 * Generated posts are never cascade-deleted (orphan protection): they only
 * reference the topic through job payload history.
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return bool
 */
function abcc_delete_topic( $topic_id ) {
	$topic_id = absint( $topic_id );
	if ( ! $topic_id || ABCC_TOPIC_POST_TYPE !== get_post_type( $topic_id ) ) {
		return false;
	}

	return (bool) wp_delete_post( $topic_id, true );
}

/**
 * Pause a topic (post_status = draft).
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return bool
 */
function abcc_pause_topic( $topic_id ) {
	$topic_id = absint( $topic_id );
	if ( ! $topic_id || ABCC_TOPIC_POST_TYPE !== get_post_type( $topic_id ) ) {
		return false;
	}

	$updated = wp_update_post(
		array(
			'ID'          => $topic_id,
			'post_status' => 'draft',
		),
		true
	);

	return ! is_wp_error( $updated );
}

/**
 * Resume a paused topic (post_status = publish + recompute next_run).
 *
 * Resetting next_run from "now" prevents a long-paused topic from being
 * considered overdue and firing immediately on the next sweep.
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return bool
 */
function abcc_resume_topic( $topic_id ) {
	$topic_id = absint( $topic_id );
	if ( ! $topic_id || ABCC_TOPIC_POST_TYPE !== get_post_type( $topic_id ) ) {
		return false;
	}

	$updated = wp_update_post(
		array(
			'ID'          => $topic_id,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		return false;
	}

	$frequency = get_post_meta( $topic_id, '_abcc_topic_frequency', true );
	update_post_meta( $topic_id, '_abcc_topic_next_run', abcc_calculate_next_run( $frequency ) );
	update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 0 );

	return true;
}

/**
 * Get a topic as a flat array.
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return array|null
 */
function abcc_get_topic( $topic_id ) {
	$topic_id = absint( $topic_id );
	if ( ! $topic_id || ABCC_TOPIC_POST_TYPE !== get_post_type( $topic_id ) ) {
		return null;
	}

	$post = get_post( $topic_id );
	if ( ! $post ) {
		return null;
	}

	return array(
		'id'                   => $topic_id,
		'title'                => $post->post_title,
		'prompt'               => $post->post_content,
		'active'               => 'publish' === $post->post_status,
		'frequency'            => get_post_meta( $topic_id, '_abcc_topic_frequency', true ),
		'post_status_override' => get_post_meta( $topic_id, '_abcc_topic_post_status_override', true ),
		'provider_override'    => get_post_meta( $topic_id, '_abcc_topic_provider_override', true ),
		'target_post_type'     => get_post_meta( $topic_id, '_abcc_topic_target_post_type', true ),
		'author_id'            => (int) get_post_meta( $topic_id, '_abcc_topic_author_id', true ),
		'last_run'             => (int) get_post_meta( $topic_id, '_abcc_topic_last_run', true ),
		'next_run'             => (int) get_post_meta( $topic_id, '_abcc_topic_next_run', true ),
		'last_job_id'          => (int) get_post_meta( $topic_id, '_abcc_topic_last_job_id', true ),
		'consecutive_failures' => (int) get_post_meta( $topic_id, '_abcc_topic_consecutive_failures', true ),
	);
}

/**
 * Build a generation payload from a topic and queue it as a job.
 *
 * Shared by the cron sweep and the manual "Run Now" action. Does NOT touch
 * topic scheduling meta — the caller decides (cron updates next_run, manual
 * runs are out-of-band).
 *
 * @since 4.2.0
 * @param int $topic_id Topic ID.
 * @return int|WP_Error Job ID on success.
 */
function abcc_queue_topic_generation( $topic_id ) {
	$topic = abcc_get_topic( $topic_id );
	if ( null === $topic ) {
		return new WP_Error( 'abcc_topic_not_found', __( 'Topic not found.', 'automated-blog-content-creator' ) );
	}

	$payload_args = array(
		'keywords'  => array( $topic['title'] ),
		'prompt'    => $topic['prompt'],
		'topic_id'  => $topic['id'],
		'post_type' => $topic['target_post_type'] ? $topic['target_post_type'] : 'post',
		'source'    => 'topic',
	);

	if ( ! empty( $topic['provider_override'] ) ) {
		$payload_args['model'] = $topic['provider_override'];
	}

	$payload = abcc_build_generation_payload( $payload_args );

	return abcc_queue_generation_job(
		$payload,
		array(
			'created_by' => $topic['author_id'] ? $topic['author_id'] : abcc_get_scheduled_post_author_id(),
		)
	);
}

/**
 * Hourly sweep: queue a generation job for every due topic.
 *
 * Each topic's _abcc_topic_next_run is the authoritative gate — this sweep
 * only finds topics whose time has come. One topic failing must not halt
 * the sweep. On queue failure next_run is left unchanged (next sweep
 * retries) and the failure counter increments; 5 consecutive failures
 * auto-pause the topic and queue an admin notice.
 *
 * @since 4.2.0
 * @param int $now Current timestamp; 0 means now. Explicit for testability.
 * @return int Number of jobs queued.
 */
function abcc_run_topic_schedules( $now = 0 ) {
	$now = $now > 0 ? (int) $now : time();

	// Re-entry guard: cron glitches can fire the hook in quick succession.
	$last_sweep = (int) abcc_get_setting( 'abcc_topics_last_sweep', 0 );
	if ( $now - $last_sweep < 5 * MINUTE_IN_SECONDS ) {
		return 0;
	}
	abcc_update_setting( 'abcc_topics_last_sweep', $now );

	$due = get_posts(
		array(
			'post_type'      => ABCC_TOPIC_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded hidden CPT, hourly cron context.
				array(
					'key'     => '_abcc_topic_next_run',
					'value'   => $now,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	$queued = 0;

	foreach ( $due as $topic_post ) {
		$topic_id = $topic_post->ID;

		try {
			$job_id = abcc_queue_topic_generation( $topic_id );

			if ( is_wp_error( $job_id ) ) {
				abcc_record_topic_failure( $topic_id, $job_id->get_error_message() );
				continue;
			}

			$frequency = get_post_meta( $topic_id, '_abcc_topic_frequency', true );
			update_post_meta( $topic_id, '_abcc_topic_last_run', $now );
			update_post_meta( $topic_id, '_abcc_topic_next_run', abcc_calculate_next_run( $frequency, $now ) );
			update_post_meta( $topic_id, '_abcc_topic_last_job_id', (int) $job_id );
			update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', 0 );
			++$queued;
		} catch ( Throwable $throwable ) {
			abcc_record_topic_failure( $topic_id, $throwable->getMessage() );
		}
	}

	return $queued;
}
add_action( 'abcc_run_topic_schedules', 'abcc_run_topic_schedules' );

/**
 * Record a topic queue failure; auto-pause after 5 consecutive failures.
 *
 * next_run is intentionally left unchanged so the next sweep retries.
 *
 * @since 4.2.0
 * @param int    $topic_id Topic ID.
 * @param string $message  Failure message.
 * @return void
 */
function abcc_record_topic_failure( $topic_id, $message ) {
	error_log( sprintf( 'ABCC Topic [ID %d]: %s', $topic_id, $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Cron context; failures must reach the server log.

	$failures = (int) get_post_meta( $topic_id, '_abcc_topic_consecutive_failures', true ) + 1;
	update_post_meta( $topic_id, '_abcc_topic_consecutive_failures', $failures );

	if ( $failures >= 5 ) {
		abcc_pause_topic( $topic_id );

		$paused   = (array) get_option( 'abcc_topics_auto_paused', array() );
		$paused[] = $topic_id;
		update_option( 'abcc_topics_auto_paused', array_values( array_unique( array_map( 'absint', $paused ) ) ) );
	}
}

/**
 * Show an admin notice for topics that were auto-paused after repeat failures.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_display_auto_paused_topics_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$paused = (array) get_option( 'abcc_topics_auto_paused', array() );
	if ( empty( $paused ) ) {
		return;
	}

	// Delete immediately after reading to minimize the window in which a
	// concurrent sweep failure appending to this option would be lost.
	delete_option( 'abcc_topics_auto_paused' );

	$names = array();
	foreach ( $paused as $topic_id ) {
		$topic   = abcc_get_topic( (int) $topic_id );
		$names[] = $topic ? $topic['title'] : sprintf( '#%d', $topic_id );
	}
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: comma-separated topic names. */
					__( 'WP-AutoInsight paused these topics after 5 consecutive failures: %s. Fix the underlying issue (API key, provider) and resume them from the Topics tab.', 'automated-blog-content-creator' ),
					implode( ', ', $names )
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'abcc_display_auto_paused_topics_notice' );

/**
 * Get topics as flat arrays.
 *
 * @since 4.2.0
 * @param array $args Optional get_posts overrides (e.g. post_status).
 * @return array
 */
function abcc_get_topics( $args = array() ) {
	$query_args = wp_parse_args(
		$args,
		array(
			'post_type'      => ABCC_TOPIC_POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	// Post type is not overridable.
	$query_args['post_type'] = ABCC_TOPIC_POST_TYPE;

	$topics = array();
	foreach ( get_posts( $query_args ) as $post ) {
		$topic = abcc_get_topic( $post->ID );
		if ( null !== $topic ) {
			$topics[] = $topic;
		}
	}

	return $topics;
}
