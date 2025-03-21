<?php
/**
 * Scheduling and automation functions
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
function get_openai_event_schedule() {
	$timestamp = wp_next_scheduled( 'abcc_openai_generate_post_hook' );

	if ( false === $timestamp ) {
		return false;
	}

	$schedule = wp_get_schedule( 'abcc_openai_generate_post_hook' );

	return array(
		'scheduled' => true,
		'schedule'  => $schedule,
		'next_run'  => date_i18n( 'Y-m-d H:i:s', $timestamp ),
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
		// Get required parameters
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$tone          = get_option( 'openai_tone', 'default' );
		$auto_create   = get_option( 'openai_auto_create', 'none' );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );

		// Log scheduled attempt
		// translators: %1$s: Auto create setting, %2$s: Model name, %3$d: Keywords count
		error_log(
			sprintf(
				'Scheduled post generation attempt - Auto Create: %1$s, Model: %2$s, Keywords count: %3$d',
				$auto_create,
				$prompt_select,
				count( $keywords )
			)
		);

		// Validate conditions
		if ( empty( $api_key ) ) {
			throw new Exception( 'API key not configured for scheduled post generation' );
		}

		if ( empty( $keywords ) ) {
			throw new Exception( 'No keywords configured for scheduled post generation' );
		}

		if ( 'none' === $auto_create ) {
			throw new Exception( 'Auto-create is disabled' );
		}

		// Generate the post
		$post_id = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			$auto_create,
			$char_limit
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		return $post_id;

	} catch ( Exception $e ) {
		error_log( 'Scheduled Post Generation Error: ' . $e->getMessage() );

		// Send admin notification about the failure if enabled
		if ( get_option( 'openai_email_notifications', false ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				__( 'Scheduled Post Generation Failed', 'automated-blog-content-creator' ),
				sprintf(
					// translators: %s: Error message
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

	// translators: %s: Post title
	$subject = sprintf(
		__( 'New AI Generated Post: %s', 'automated-blog-content-creator' ),
		$post->post_title
	);

	// translators: %1$s: Post title, %2$s: Edit post URL
	$message = sprintf(
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
