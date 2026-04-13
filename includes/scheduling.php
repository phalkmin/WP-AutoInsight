<?php
/**
 * Scheduling and automation functions.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Resolve the author ID to use for scheduled posts.
 *
 * @return int
 */
function abcc_get_scheduled_post_author_id() {
	$current_user_id = get_current_user_id();
	if ( $current_user_id > 0 ) {
		return (int) $current_user_id;
	}

	if ( function_exists( 'get_users' ) ) {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'fields'  => 'ID',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( ! empty( $admins[0] ) ) {
			return (int) $admins[0];
		}
	}

	return 1;
}

/**
 * Check if the 'openai_generate_post_hook' event is scheduled and get schedule details.
 *
 * @return array|bool An array with schedule details if the event is scheduled, false otherwise.
 */
function abcc_get_openai_event_schedule() {
	$timestamp = wp_next_scheduled( 'abcc_openai_generate_post_hook' );

	if ( false === $timestamp ) {
		return false;
	}

	$schedule = wp_get_schedule( 'abcc_openai_generate_post_hook' );
	$groups   = abcc_get_setting( 'abcc_keyword_groups', array() );
	$model    = abcc_get_setting( 'prompt_select', '' );
	$next     = null;

	if ( ! empty( $groups ) ) {
		$last_index = (int) get_option( 'abcc_last_group_index', -1 );
		$next_index = ( $last_index + 1 ) % count( $groups );

		if ( ! empty( $groups[ $next_index ]['name'] ) ) {
			$next = $groups[ $next_index ]['name'];
		}
	}

	return array(
		'scheduled'  => true,
		'schedule'   => $schedule,
		'next_run'   => date_i18n( 'l, F j \a\t g:i A', $timestamp ),
		'timestamp'  => $timestamp,
		'group_name' => $next,
		'model'      => $model,
	);
}

/**
 * Generates a post on schedule using AI services.
 *
 * @return bool|int Returns post ID on success, false if conditions aren't met or on failure
 */
function abcc_openai_generate_post_scheduled() {
	try {
		// Get required parameters.
		$tone          = abcc_get_setting( 'openai_tone', 'default' );
		$auto_create   = get_option( 'openai_auto_create', 'none' );
		$char_limit    = abcc_get_setting( 'openai_char_limit', 200 );
		$prompt_select = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );

		if ( 'none' === $auto_create ) {
			throw new Exception( 'Auto-create is disabled' );
		}

		$groups = get_option( 'abcc_keyword_groups', array() );
		if ( empty( $groups ) ) {
			throw new Exception( 'No keyword groups configured for scheduled post generation' );
		}

		// Rotate through groups.
		$last_index = get_option( 'abcc_last_group_index', -1 );
		$next_index = ( $last_index + 1 ) % count( $groups );
		update_option( 'abcc_last_group_index', $next_index );

		$selected_group = $groups[ $next_index ];

		if ( empty( $selected_group['keywords'] ) ) {
			// Find first group with keywords as fallback.
			foreach ( $groups as $group ) {
				if ( ! empty( $group['keywords'] ) ) {
					$selected_group = $group;
					break;
				}
			}
		}

		if ( empty( $selected_group['keywords'] ) ) {
			throw new Exception( 'No keywords found in any configured group' );
		}

		$keywords = (array) $selected_group['keywords'];
		$category = $selected_group['category'] ?? 0;
		$template = $selected_group['template'] ?? 'default';

		$payload = abcc_build_generation_payload(
			array(
				'keywords'   => $keywords,
				'model'      => $prompt_select,
				'tone'       => $tone,
				'char_limit' => $char_limit,
				'category'   => $category,
				'template'   => $template,
				'source'     => 'scheduled',
			)
		);
		$job_id = abcc_queue_generation_job(
			$payload,
			array(
				'created_by' => abcc_get_scheduled_post_author_id(),
			)
		);

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		return $job_id;

	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Schedule or unschedule the event based on the selected option.
 */
function abcc_schedule_openai_event() {
	$selected_option = get_option( 'openai_auto_create', 'none' );

	// Unscheduling the event if it was scheduled previously.
	wp_clear_scheduled_hook( 'abcc_openai_generate_post_hook' );

	// Scheduling the event based on the selected option.
	if ( ! empty( $selected_option ) && 'none' !== $selected_option ) {
		if ( 'hourly' === $selected_option ) {
			$schedule_interval  = 'hourly';
			$first_run_delay    = HOUR_IN_SECONDS;
		} elseif ( 'weekly' === $selected_option ) {
			$schedule_interval  = 'weekly';
			$first_run_delay    = WEEK_IN_SECONDS;
		} else {
			$schedule_interval  = 'daily';
			$first_run_delay    = DAY_IN_SECONDS;
		}
		wp_schedule_event( time() + $first_run_delay, $schedule_interval, 'abcc_openai_generate_post_hook' );
	}
}

/**
 * Send email notification about new post creation.
 *
 * @param int $post_id The ID of the created post.
 * @return bool Whether the email was sent successfully.
 */
function abcc_send_post_notification( $post_id ) {
	$admin_email = get_option( 'admin_email' );
	$post        = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	$subject = sprintf(
		/* translators: %s: Post title */
		__( 'New AI Generated Post: %s', 'automated-blog-content-creator' ),
		$post->post_title
	);

	$message = sprintf(
		/* translators: %1$s: Post title, %2$s: Edit post URL */
		__( 'A new post "%1$s" has been created automatically.\n\nYou can edit it here: %2$s', 'automated-blog-content-creator' ),
		$post->post_title,
		get_edit_post_link( $post_id, '' )
	);

	return wp_mail( $admin_email, $subject, $message );
}

// Trigger the OpenAI post generation.
add_action( 'abcc_openai_generate_post_hook', 'abcc_openai_generate_post_scheduled' );
