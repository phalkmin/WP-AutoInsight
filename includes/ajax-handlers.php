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
 *
 * @throws Exception On generation failure.
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
		$job_id  = abcc_queue_generation_job( $payload );

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
 *
 * @throws Exception On generation failure.
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
		$prompt_select = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
		$tone          = abcc_get_setting( 'openai_tone', 'default' );
		$char_limit    = abcc_get_setting( 'openai_char_limit', 200 );

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

	$provider        = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
	$submitted_key   = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	$api_key         = ! empty( $submitted_key ) ? $submitted_key : abcc_get_provider_api_key( $provider );
	$result          = abcc_test_provider_connection( $provider, $api_key );

	if ( is_wp_error( $result ) || ( is_array( $result ) && empty( $result['success'] ) ) ) {
		$error_message = is_wp_error( $result ) ? $result->get_error_message() : ( $result['error'] ?? __( 'Validation failed', 'automated-blog-content-creator' ) );
		set_transient(
			'abcc_last_validation_' . $provider,
			array(
				'status'  => 'failed',
				'message' => $error_message,
			),
			HOUR_IN_SECONDS
		);
		wp_send_json_error( array( 'message' => $error_message ) );
	} elseif ( ! empty( $result['success'] ) ) {
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
 * @throws Exception On generation failure.
 */
function abcc_handle_bulk_generate_single() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$keyword    = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	$template   = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : 'default';
	$model      = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
	$run_id     = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : '';
	$draft_only = isset( $_POST['draft'] ) && rest_sanitize_boolean( wp_unslash( $_POST['draft'] ) );

	if ( empty( $keyword ) ) {
		wp_send_json_error( array( 'message' => __( 'Empty keyword.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$payload = abcc_build_generation_payload(
			array(
				'keywords'    => array( $keyword ),
				'model'       => $model,
				'template'    => $template,
				'source'      => 'bulk',
				'draft_only'  => $draft_only,
				'post_author' => get_current_user_id(),
			)
		);
		$job_id  = abcc_queue_generation_job(
			$payload,
			array(
				'run_id' => $run_id,
			)
		);

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		// Process the job inline so the UI reflects the real outcome.
		abcc_process_generation_job( $job_id );

		$job_status  = get_post_meta( $job_id, '_abcc_job_status', true );
		$result_post = (int) get_post_meta( $job_id, '_abcc_job_result_post_id', true );

		if ( ABCC_Job::STATUS_SUCCESS === $job_status && $result_post ) {
			wp_send_json_success(
				array(
					'message'  => __( 'Success', 'automated-blog-content-creator' ),
					'job_id'   => $job_id,
					'run_id'   => $run_id,
					'edit_url' => get_edit_post_link( $result_post, 'raw' ),
				)
			);
		} else {
			$error = get_post_meta( $job_id, '_abcc_job_error', true );
			throw new Exception( $error ? $error : __( 'Generation failed.', 'automated-blog-content-creator' ) );
		}
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
			'connectors_url' => admin_url( 'options-general.php?page=ai-connectors' ),
		)
	);
}
add_action( 'wp_ajax_abcc_check_wp_connectors', 'abcc_handle_check_wp_connectors' );

/**
 * AJAX handler for regenerating a post.
 *
 * @throws Exception On generation failure.
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
		$job_id  = abcc_queue_generation_job( $payload );

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

/**
 * Auto-save a single scalar plugin setting via AJAX.
 *
 * API keys and array-type settings are excluded — those require form submit.
 *
 * @since 4.0.0
 * @return void
 */
function abcc_handle_autosave_setting() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';

	if ( empty( $key ) ) {
		wp_send_json_error( array( 'message' => __( 'No setting key provided.', 'automated-blog-content-creator' ) ) );
		return;
	}

	// Validate key exists in schema.
	$definition = abcc_get_setting_definition( $key );
	if ( null === $definition ) {
		wp_send_json_error( array( 'message' => __( 'Unknown setting key.', 'automated-blog-content-creator' ) ) );
		return;
	}

	// Explicitly block sensitive or array-type keys.
	$blocked_keys = array(
		'openai_api_key',
		'gemini_api_key',
		'claude_api_key',
		'perplexity_api_key',
		'stability_api_key',
		'abcc_keyword_groups',
		'abcc_content_templates',
		'abcc_selected_post_types',
		'abcc_supported_audio_formats',
	);
	if ( in_array( $key, $blocked_keys, true ) ) {
		wp_send_json_error( array( 'message' => __( 'This setting cannot be auto-saved.', 'automated-blog-content-creator' ) ) );
		return;
	}

	// Sanitize value based on the type of the schema default.
	$default = $definition['default'];
	$raw     = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( is_bool( $default ) ) {
		$value = ( '1' === $raw || 'true' === $raw );
	} elseif ( is_int( $default ) ) {
		$value = absint( $raw );
	} else {
		$value = sanitize_text_field( $raw );
	}

	abcc_update_setting( $key, $value );

	wp_send_json_success( array( 'key' => $key ) );
}
add_action( 'wp_ajax_abcc_autosave_setting', 'abcc_handle_autosave_setting' );

/**
 * Run a provider health check for all text-capable providers and cache results.
 *
 * Stores results under option `abcc_provider_health` as an associative array:
 *   [ provider_id => [ 'status' => 'connected|no_key|failed', 'timestamp' => int ] ]
 *
 * @return void
 */
function abcc_run_provider_health_check() {
	if ( ! function_exists( 'abcc_get_provider_ids' ) || ! function_exists( 'abcc_provider_supports_text_generation' ) ) {
		return;
	}

	$results = get_option( 'abcc_provider_health', array() );

	foreach ( abcc_get_provider_ids() as $provider_id ) {
		if ( ! abcc_provider_supports_text_generation( $provider_id ) ) {
			continue;
		}

		$api_key = abcc_get_provider_api_key_for_health_check( $provider_id );

		if ( empty( $api_key ) ) {
			$results[ $provider_id ] = array(
				'status'    => 'no_key',
				'timestamp' => time(),
			);
			delete_transient( 'abcc_last_validation_' . $provider_id );
			continue;
		}

		$valid = abcc_validate_provider_api_key_probe( $provider_id, $api_key );

		$results[ $provider_id ] = array(
			'status'    => $valid ? 'connected' : 'failed',
			'timestamp' => time(),
		);
		set_transient(
			'abcc_last_validation_' . $provider_id,
			array(
				'status'    => $valid ? 'verified' : 'failed',
				'message'   => $valid
					? __( 'Validated automatically', 'automated-blog-content-creator' )
					: __( 'Connection failed', 'automated-blog-content-creator' ),
				'timestamp' => time(),
			),
			2 * DAY_IN_SECONDS
		);
	}

	update_option( 'abcc_provider_health', $results );
}

/**
 * Get the stored API key option value for a provider (used by health check only).
 *
 * @param string $provider_id Provider ID from registry.
 * @return string
 */
function abcc_get_provider_api_key_for_health_check( $provider_id ) {
	return (string) abcc_get_provider_saved_api_key( $provider_id );
}

/**
 * Validate a provider API key with an optimistic probe.
 *
 * @param string $provider_id Provider ID.
 * @param string $api_key     API key to test.
 * @return bool True if key appears valid.
 */
function abcc_validate_provider_api_key_probe( $provider_id, $api_key ) {
	if ( empty( $api_key ) ) {
		return false;
	}

	// Use the provider's existing check mechanism if available.
	if ( function_exists( 'abcc_check_provider_api_key' ) ) {
		$result = abcc_check_provider_api_key( $provider_id, $api_key );
		return ! is_wp_error( $result ) && false !== $result;
	}

	return true; // Optimistic if no probe available.
}
