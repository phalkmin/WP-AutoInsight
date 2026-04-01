<?php
/**
 * AJAX request handlers
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * AJAX handler for generating a post.
 */
function abcc_handle_create_post() {
	check_ajax_referer( 'abcc_admin_buttons', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$groups              = get_option( 'abcc_keyword_groups', array() );
		$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );
		$post_type           = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

		if ( empty( $post_type ) ) {
			$post_type = ! empty( $selected_post_types ) ? $selected_post_types[0] : 'post';
		}

		if ( empty( $groups ) ) {
			throw new Exception( __( 'No keyword groups found. Please add at least one group in Content Settings.', 'automated-blog-content-creator' ) );
		}

		if ( ! post_type_exists( $post_type ) ) {
			throw new Exception( __( 'Invalid post type requested.', 'automated-blog-content-creator' ) );
		}

		if ( ! in_array( $post_type, $selected_post_types, true ) ) {
			throw new Exception( __( 'This post type is not enabled for manual generation.', 'automated-blog-content-creator' ) );
		}

		// Use the group index passed from the UI, falling back to the first group with keywords.
		$group_index    = isset( $_POST['group_index'] ) ? absint( $_POST['group_index'] ) : null;
		$selected_group = null;

		if ( null !== $group_index && isset( $groups[ $group_index ] ) && ! empty( $groups[ $group_index ]['keywords'] ) ) {
			$selected_group = $groups[ $group_index ];
		} else {
			foreach ( $groups as $group ) {
				if ( ! empty( $group['keywords'] ) ) {
					$selected_group = $group;
					break;
				}
			}
		}

		if ( ! $selected_group ) {
			throw new Exception( __( 'No keywords found in any group.', 'automated-blog-content-creator' ) );
		}

		$keywords = (array) $selected_group['keywords'];
		$category = $selected_group['category'] ?? 0;
		$template = $selected_group['template'] ?? 'default';

		$payload = abcc_build_generation_payload(
			array(
				'keywords'  => $keywords,
				'category'  => $category,
				'post_type' => $post_type,
				'template'  => $template,
				'source'    => 'manual',
			)
		);
		$job_id = abcc_queue_generation_job( $payload );

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Generation job queued successfully.', 'automated-blog-content-creator' ),
				'job_id'  => $job_id,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_create_post', 'abcc_handle_create_post' );

/**
 * Handle the AJAX request for rewriting a post.
 */
function abcc_handle_rewrite_post() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'abcc_rewrite_post_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed', 'automated-blog-content-creator' ) ) );
		return;
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'automated-blog-content-creator' ) ) );
		return;
	}

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		// Get the post.
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( __( 'Post not found', 'automated-blog-content-creator' ) );
		}

		// Get settings.
		$api_key       = abcc_check_api_key();
		$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );
		$tone          = get_option( 'openai_tone', 'default' );
		$char_limit    = get_option( 'openai_char_limit', 200 );

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'API key not configured', 'automated-blog-content-creator' ) );
		}

		// Build rewrite prompt.
		$prompt = sprintf(
			"Rewrite this blog post to improve its SEO and readability while maintaining the core message.\n\n" .
			"Original Title: %s\n\n" .
			"Original Content:\n%s\n\n" .
			"Instructions:\n" .
			"- Keep the same tone and style\n" .
			"- Improve readability and structure\n" .
			"- Use proper HTML formatting (<h2>, <h3>, <p> tags)\n" .
			"- Maintain all key points and information\n" .
			"- Make it more engaging and SEO-friendly\n" .
			'- Use clear headings and better paragraph structure',
			$post->post_title,
			wp_strip_all_tags( $post->post_content )
		);

		// Generate new content.
		$result = abcc_generate_content(
			$api_key,
			$prompt,
			$prompt_select,
			$char_limit
		);

		if ( false === $result ) {
			throw new Exception( __( 'Failed to generate new content', 'automated-blog-content-creator' ) );
		}

		// Process the content.
		$content_array = array_filter(
			$result,
			function ( $line ) {
				return ! empty( trim( $line ) );
			}
		);

		$format_content = abcc_create_blocks( $content_array );
		$post_content   = abcc_gutenberg_blocks( $format_content );

		// Update the post.
		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_kses_post( $post_content ),
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			throw new Exception( $updated->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Post rewritten successfully!', 'automated-blog-content-creator' ),
				'post_id' => $post_id,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
			)
		);
	}
}
add_action( 'wp_ajax_abcc_rewrite_post', 'abcc_handle_rewrite_post' );

/**
 * AJAX handler for validating API keys.
 *
 * @since 3.4.0
 */
function abcc_handle_validate_api_key() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized', 'automated-blog-content-creator' ) ) );
		return;
	}

	$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
	$api_key  = '';

	switch ( $provider ) {
		case 'openai':
			$api_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
			$result  = abcc_test_openai_connection( $api_key );
			break;
		case 'gemini':
			$api_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
			$result  = abcc_test_gemini_connection( $api_key );
			break;
		case 'claude':
			$api_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
			$result  = abcc_test_claude_connection( $api_key );
			break;
		case 'perplexity':
			$api_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : get_option( 'perplexity_api_key', '' );
			$result  = abcc_test_perplexity_connection( $api_key );
			break;
		case 'stability':
			$api_key = defined( 'STABILITY_API' ) ? STABILITY_API : get_option( 'stability_api_key', '' );
			// No built-in test for stability yet, let's just check if it's not empty for now or add one.
			$result = ! empty( $api_key ) ? true : new WP_Error( 'empty', __( 'API key is empty', 'automated-blog-content-creator' ) );
			break;
		default:
			wp_send_json_error( array( 'message' => __( 'Invalid provider', 'automated-blog-content-creator' ) ) );
			return;
	}

	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		set_transient(
			'abcc_last_validation_' . $provider,
			array(
				'status'  => 'failed',
				'message' => $error_message,
			),
			HOUR_IN_SECONDS
		);
		wp_send_json_error( array( 'message' => $error_message ) );
	} elseif ( $result ) {
		set_transient(
			'abcc_last_validation_' . $provider,
			array(
				'status'  => 'verified',
				'message' => __( 'Verified just now', 'automated-blog-content-creator' ),
			),
			HOUR_IN_SECONDS
		);
		wp_send_json_success( array( 'message' => __( 'Verified just now', 'automated-blog-content-creator' ) ) );
	} else {
		set_transient(
			'abcc_last_validation_' . $provider,
			array(
				'status'  => 'failed',
				'message' => __( 'Connection failed', 'automated-blog-content-creator' ),
			),
			HOUR_IN_SECONDS
		);
		wp_send_json_error( array( 'message' => __( 'Connection failed', 'automated-blog-content-creator' ) ) );
	}
}
add_action( 'wp_ajax_abcc_validate_api_key', 'abcc_handle_validate_api_key' );

/**
 * AJAX handler for bulk generating a single post.
 *
 * @since 3.6.0
 */
function abcc_handle_bulk_generate_single() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$keyword  = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : 'default';
	$model    = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
	$run_id   = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : '';

	if ( empty( $keyword ) ) {
		wp_send_json_error( array( 'message' => __( 'Empty keyword.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$payload = abcc_build_generation_payload(
			array(
				'keywords' => array( $keyword ),
				'model'    => $model,
				'template' => $template,
				'source'   => 'bulk',
			)
		);
		$job_id = abcc_queue_generation_job(
			$payload,
			array(
				'run_id' => $run_id,
			)
		);

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Success', 'automated-blog-content-creator' ),
				'job_id'  => $job_id,
				'run_id'  => $run_id,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_bulk_generate_single', 'abcc_handle_bulk_generate_single' );

/**
 * AJAX handler to check for WP 7.0 Connectors availability.
 *
 * @since 3.6.0
 */
function abcc_handle_check_wp_connectors() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized', 'automated-blog-content-creator' ) ) );
		return;
	}

	$has_connectors = abcc_wp_ai_client_available();
	$has_keys       = false;
	$providers      = array();

	if ( $has_connectors ) {
		$check = array( 'openai', 'claude', 'gemini' );
		foreach ( $check as $provider ) {
			if ( ! empty( abcc_get_wp_ai_credential( $provider ) ) ) {
				$has_keys    = true;
				$providers[] = $provider;
			}
		}
	}

	wp_send_json_success(
		array(
			'has_connectors' => $has_connectors,
			'has_keys'       => $has_keys,
			'providers'      => $providers,
			'connectors_url' => admin_url( 'options-general.php?page=connectors' ),
		)
	);
}
add_action( 'wp_ajax_abcc_check_wp_connectors', 'abcc_handle_check_wp_connectors' );

/**
 * AJAX handler for regenerating a post.
 */
function abcc_handle_regenerate_post() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'automated-blog-content-creator' ) ) );
		return;
	}

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$params_json = get_post_meta( $post_id, '_abcc_generation_params', true );
	if ( ! $params_json ) {
		wp_send_json_error( array( 'message' => __( 'Generation parameters not found for this post.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$params = json_decode( $params_json, true );
	if ( ! $params ) {
		wp_send_json_error( array( 'message' => __( 'Invalid generation parameters.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$payload = abcc_build_generation_payload(
			array(
				'keywords'   => $params['keywords'],
				'model'      => $params['model'],
				'tone'       => $params['tone'],
				'char_limit' => $params['char_limit'],
				'post_type'  => $params['post_type'] ?? 'post',
				'category'   => $params['category'] ?? 0,
				'template'   => $params['template'] ?? 'default',
				'source'     => 'regenerate',
			)
		);
		$job_id = abcc_queue_generation_job( $payload );

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Regeneration job queued successfully!', 'automated-blog-content-creator' ),
				'job_id'  => $job_id,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_regenerate_post', 'abcc_handle_regenerate_post' );

/**
 * AJAX handler for polling a generation job.
 *
 * @since 3.7.0
 */
function abcc_handle_get_job_status() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
	$job    = abcc_get_job_data( $job_id );

	if ( empty( $job ) ) {
		wp_send_json_error( array( 'message' => __( 'Generation job not found.', 'automated-blog-content-creator' ) ) );
		return;
	}

	wp_send_json_success( $job );
}
add_action( 'wp_ajax_abcc_get_job_status', 'abcc_handle_get_job_status' );

/**
 * AJAX handler for refreshing the generation log.
 *
 * @since 3.7.0
 */
function abcc_handle_get_job_log() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$run_id = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : '';
	$status = isset( $_POST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['status_filter'] ) ) : '';

	wp_send_json_success(
		array(
			'html' => abcc_render_job_log_rows(
				array(
					'run_id' => $run_id,
					'status' => $status,
				)
			),
		)
	);
}
add_action( 'wp_ajax_abcc_get_job_log', 'abcc_handle_get_job_log' );
