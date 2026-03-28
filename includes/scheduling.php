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

	return array(
		'scheduled' => true,
		'schedule'  => $schedule,
		'next_run'  => date_i18n( 'l, F j \a\t g:i A', $timestamp ),
		'timestamp' => $timestamp,
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
		$tone          = get_option( 'openai_tone', 'default' );
		$auto_create   = get_option( 'openai_auto_create', 'none' );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );

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
		$api_key = abcc_check_api_key( $payload['model'] );

		// Validate common conditions.
		if ( empty( $api_key ) ) {
			throw new Exception( 'API key not configured for scheduled post generation' );
		}

		// Generate the post.
		$post_id = abcc_openai_generate_post(
			$api_key,
			$payload['keywords'],
			$payload['model'],
			$payload['tone'],
			true,
			$payload['char_limit'],
			$payload['post_type'],
			$payload
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Note: success notification is sent inside abcc_openai_generate_post().

		return $post_id;

	} catch ( Exception $e ) {
		// Send admin notification about the failure if enabled.
		if ( true === get_option( 'openai_email_notifications', false ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				__( 'Scheduled Post Generation Failed', 'automated-blog-content-creator' ),
				sprintf(
					/* translators: %s: Error message */
					__( 'The scheduled post generation failed with error: %s', 'automated-blog-content-creator' ),
					$e->getMessage()
				)
			);
		}

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
	if ( 'none' !== $selected_option ) {
		$schedule_interval = ( 'hourly' === $selected_option ) ? 'hourly' : ( ( 'weekly' === $selected_option ) ? 'weekly' : 'daily' );
		wp_schedule_event( time(), $schedule_interval, 'abcc_openai_generate_post_hook' );
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

// Schedule or unschedule the event when the option is updated.
add_action( 'update_option_openai_auto_create', 'abcc_schedule_openai_event' );

// Trigger the OpenAI post generation.
add_action( 'abcc_openai_generate_post_hook', 'abcc_openai_generate_post_scheduled' );
