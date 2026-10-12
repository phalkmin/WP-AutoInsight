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
		$selected_post_types = abcc_get_setting( 'abcc_selected_post_types', array( 'post' ) );
		$post_type           = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

		if ( empty( $post_type ) ) {
			$post_type = ! empty( $selected_post_types ) ? $selected_post_types[0] : 'post';
		}

		if ( ! post_type_exists( $post_type ) ) {
			throw new Exception( __( 'Invalid post type requested.', 'automated-blog-content-creator' ) );
		}

		if ( ! in_array( $post_type, $selected_post_types, true ) ) {
			throw new Exception( __( 'This post type is not enabled for manual generation.', 'automated-blog-content-creator' ) );
		}

		if ( ! abcc_user_can_create_post_type( $post_type ) ) {
			throw new Exception( __( 'You do not have permission to create this post type.', 'automated-blog-content-creator' ) );
		}

		// Resolve the source. Priority: explicit 'source' token > legacy
		// 'group_index' > sticky default. The resolver handles all fallbacks.
		$token = '';
		if ( isset( $_POST['source'] ) ) {
			$token = abcc_sanitize_composer_source( sanitize_text_field( wp_unslash( $_POST['source'] ) ) );
		} elseif ( isset( $_POST['group_index'] ) ) {
			$token = 'group:' . absint( $_POST['group_index'] );
		} else {
			$token = abcc_get_setting( 'abcc_composer_last_source', '' );
		}

		$source = abcc_resolve_composer_source( $token );
		if ( is_wp_error( $source ) ) {
			throw new Exception( $source->get_error_message() );
		}

		$payload_args = array(
			'keywords'   => $source['keywords'],
			'category'   => $source['category'],
			'post_type'  => $post_type,
			'template'   => $source['template'],
			'source'     => 'manual',
			// Group overrides (blank/0 for topics → global defaults).
			'model'      => $source['model'] ?? '',
			'char_limit' => $source['char_limit'] ?? 0,
		);
		if ( 'topic' === $source['type'] ) {
			$payload_args['prompt']   = $source['prompt'];
			$payload_args['topic_id'] = $source['topic_id'];
			$payload_args['source']   = 'topic';
		}

		$payload = abcc_build_generation_payload( $payload_args );

		// Per-call overrides (model / save-as) — applied to this payload only.
		$overrides = array();
		if ( isset( $_POST['model'] ) ) {
			$overrides['model'] = sanitize_text_field( wp_unslash( $_POST['model'] ) );
		}
		if ( isset( $_POST['post_status'] ) ) {
			$overrides['post_status'] = sanitize_text_field( wp_unslash( $_POST['post_status'] ) );
		}
		$payload = abcc_apply_composer_overrides( $payload, $overrides );

		// Resolved here so the response can say when a requested Publish was
		// downgraded; the queue applies the same resolution.
		$requested_publish = isset( $payload['post_status'] ) && 'publish' === $payload['post_status'];
		$payload           = abcc_finalize_payload_post_status( $payload );

		$job_id = abcc_queue_generation_job( $payload );

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		// Persist the resolved source as the sticky default — only after a
		// successful queue, never on validation failure.
		abcc_update_setting( 'abcc_composer_last_source', $source['token'] );

		$message = esc_html__( 'Generation job queued successfully.', 'automated-blog-content-creator' );
		if ( $requested_publish && 'draft' === $payload['post_status'] ) {
			$message = esc_html__( 'Generation job queued. Your role cannot publish this post type, so the post will be saved as a draft.', 'automated-blog-content-creator' );
		}

		wp_send_json_success(
			array(
				'message'     => $message,
				'job_id'      => $job_id,
				'post_status' => $payload['post_status'],
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
 * Returns block markup for the editor to apply; never writes post_content.
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

	// Role-level prompt access is not enough — the user must also be able to
	// edit THIS post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new Exception( __( 'Post not found', 'automated-blog-content-creator' ) );
		}

		$api_key       = abcc_check_api_key();
		$prompt_select = abcc_get_setting( 'prompt_select', abcc_get_default_text_model() );
		$char_limit    = (int) abcc_get_setting( 'openai_char_limit', 200 );

		if ( empty( $api_key ) ) {
			throw new Exception( __( 'API key not configured', 'automated-blog-content-creator' ) );
		}

		// The editor's current content when the JS sends it; unsaved edits
		// are what the user is looking at.
		$source_html = isset( $_POST['content'] )
			? wp_kses_post( wp_unslash( $_POST['content'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_kses_post sanitizes.
			: (string) $post->post_content;

		$input = abcc_prepare_rewrite_input( $source_html );
		if ( is_wp_error( $input ) ) {
			throw new Exception( $input->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- plain-text message.
		}
		if ( '' === trim( wp_strip_all_tags( $input ) ) ) {
			throw new Exception( __( 'This post has nothing to rewrite yet. Add some text first.', 'automated-blog-content-creator' ) );
		}

		$prompt = abcc_build_rewrite_prompt( $post->post_title, $input );

		// A rewrite is at least as long as its input; the Content Length
		// setting alone truncates anything longer than a stub.
		$budget = max( $char_limit, (int) ceil( abcc_estimate_tokens( $input ) * 1.3 ) );
		$budget = min( $budget, 8000 );

		$detailed = abcc_generate_content_detailed( $api_key, $prompt, $prompt_select, $budget, array( 'source' => 'rewrite' ) );

		if ( is_wp_error( $detailed['error'] ) || empty( $detailed['content'] ) ) {
			throw new Exception(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- plain-text message from the error formatter.
				abcc_format_generation_error(
					$detailed['error'],
					array(
						'provider' => abcc_get_provider_for_model( $prompt_select ),
						'model'    => $prompt_select,
					)
				)
			);
		}

		if ( ! empty( $detailed['truncated'] ) ) {
			throw new Exception( __( 'The rewrite was cut off at the model\'s output limit. Raise Content Length under Settings → General or shorten the post, then try again. Nothing was changed.', 'automated-blog-content-creator' ) );
		}

		$lines = abcc_clean_rewrite_output( $detailed['content'], $post->post_title );
		if ( empty( $lines ) ) {
			throw new Exception( __( 'The model returned no usable content. Try again.', 'automated-blog-content-creator' ) );
		}

		$markup = abcc_gutenberg_blocks( abcc_create_blocks( $lines ) );

		wp_send_json_success(
			array(
				'content'    => $markup,
				'word_count' => abcc_count_words_unicode( $markup ),
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

	// The provider ID is interpolated into an option name below — restrict it
	// to known providers.
	if ( ! in_array( $provider, abcc_get_provider_ids(), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	$api_key       = '' !== $submitted_key ? $submitted_key : (string) abcc_get_provider_api_key( $provider );

	if ( '' === trim( $api_key ) ) {
		wp_send_json_error( array( 'message' => __( 'Enter an API key first.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$result = abcc_test_provider_connection( $provider, $api_key );

	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
	} elseif ( is_array( $result ) && ! empty( $result['success'] ) ) {
		$error_message = '';
	} else {
		$error_message = is_array( $result ) && ! empty( $result['error'] ) ? (string) $result['error'] : __( 'Connection failed', 'automated-blog-content-creator' );
	}

	// Health follows the key generation will use; a typed, unsaved key is only tested.
	$record_health = '' === $submitted_key;

	if ( '' !== $error_message ) {
		if ( $record_health ) {
			abcc_record_provider_validation( $provider, false, $error_message );
		}
		wp_send_json_error( array( 'message' => $error_message ) );
		return;
	}

	if ( $record_health ) {
		abcc_record_provider_validation( $provider, true );
	}
	wp_send_json_success( array( 'message' => __( 'Verified just now', 'automated-blog-content-creator' ) ) );
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

	if ( '' !== $model && ! abcc_is_valid_text_model( $model ) ) {
		$model = '';
	}

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
				'run_id'         => $run_id,
				'process_inline' => true,
			)
		);
		// Job is processed inline inside abcc_queue_generation_job() via process_inline; no separate dispatch needed.

		if ( is_wp_error( $job_id ) ) {
			throw new Exception( $job_id->get_error_message() );
		}

		$job_status  = get_post_meta( $job_id, '_abcc_job_status', true );
		$result_post = (int) get_post_meta( $job_id, '_abcc_job_result_post_id', true );

		if ( ABCC_Job::STATUS_SUCCESS === $job_status && $result_post ) {
			$image_attempt = (string) get_post_meta( $result_post, '_abcc_image_generation_attempted', true );
			wp_send_json_success(
				array(
					'message'      => __( 'Success', 'automated-blog-content-creator' ),
					'job_id'       => $job_id,
					'run_id'       => $run_id,
					'edit_url'     => get_edit_post_link( $result_post, 'raw' ),
					'image_failed' => '' !== $image_attempt && '1' !== $image_attempt,
				)
			);
		} else {
			$error = get_post_meta( $job_id, '_abcc_job_error', true );
			wp_send_json_error(
				array(
					'message' => $error ? $error : __( 'Generation failed.', 'automated-blog-content-creator' ),
					'code'    => (string) get_post_meta( $job_id, '_abcc_job_error_code', true ),
				)
			);
		}
	} catch ( Exception $e ) {
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
				'code'    => '',
			)
		);
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
			'connectors_url' => abcc_get_connectors_url(),
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

	// Role-level prompt access is not enough — the user must also be able to
	// edit THIS post.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'automated-blog-content-creator' ) ) );
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
				// The button promises a new draft regardless of the global default.
				'draft_only' => true,
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
 * AJAX: retry featured-image generation for a post whose image failed.
 *
 * @since 4.4.0
 * @return void
 */
function abcc_handle_retry_featured_image() {
	check_ajax_referer( 'abcc_retry_image', 'nonce' );

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'automated-blog-content-creator' ) ) );
		return;
	}

	if ( ! abcc_current_user_can_prompt() || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$retry     = abcc_get_image_retry_context( $post_id );
		$image_url = abcc_generate_featured_image( $retry['model'], $retry['keywords'] );

		if ( ! $image_url ) {
			$reason = abcc_get_last_image_error();
			wp_send_json_error( array( 'message' => '' !== $reason ? $reason : __( 'The image provider did not return an image. Check the provider key and image settings.', 'automated-blog-content-creator' ) ) );
			return;
		}

		$alt_text = abcc_get_setting( 'abcc_auto_alt_text', true )
			? abcc_build_featured_image_alt_text( get_the_title( $post_id ), isset( $retry['keywords'][0] ) ? $retry['keywords'][0] : '' )
			: '';
		$attached = abcc_set_featured_image( $post_id, $image_url, $alt_text, array( 'title' => get_the_title( $post_id ) ) );

		if ( false === $attached ) {
			wp_send_json_error( array( 'message' => __( 'The image was generated but could not be attached to the post.', 'automated-blog-content-creator' ) ) );
			return;
		}

		// Clear the failure record so the notice disappears.
		delete_post_meta( $post_id, '_abcc_image_generation_attempted' );

		wp_send_json_success( array( 'message' => __( 'Featured image generated.', 'automated-blog-content-creator' ) ) );
	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => __( 'Image generation failed. Check the debug log for details.', 'automated-blog-content-creator' ) ) );
	}
}
add_action( 'wp_ajax_abcc_retry_featured_image', 'abcc_handle_retry_featured_image' );

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

	// Same message as a missing job: don't confirm that another user's job exists.
	if ( ! abcc_user_can_view_job( $job_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Generation job not found.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$job = abcc_get_job_data( $job_id );

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

	$default = $definition['default'];

	// Autosave accepts scalar settings only; structured values go through the
	// form submit. A scalar stored where consumers expect an array fatals in
	// array_intersect() on PHP 8.
	if ( is_array( $default ) ) {
		wp_send_json_error( array( 'message' => __( 'This setting cannot be auto-saved.', 'automated-blog-content-creator' ) ) );
		return;
	}

	// Secrets never travel through autosave regardless of type.
	if ( '_api_key' === substr( $key, -8 ) || 'api_key' === substr( $key, -7 ) ) {
		wp_send_json_error( array( 'message' => __( 'This setting cannot be auto-saved.', 'automated-blog-content-creator' ) ) );
		return;
	}

	// Sanitize value based on the type of the schema default.
	$raw = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( is_bool( $default ) ) {
		$value = ( '1' === $raw || 'true' === $raw );
	} elseif ( is_int( $default ) ) {
		$value = absint( $raw );
	} else {
		$value = sanitize_text_field( $raw );
	}

	// Schema-declared sanitizer wins over the type-based fallback.
	if ( ! empty( $definition['sanitize'] ) && is_callable( $definition['sanitize'] ) ) {
		$value = call_user_func( $definition['sanitize'], $value );
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

	foreach ( abcc_get_provider_ids() as $provider_id ) {
		if ( ! abcc_provider_supports_text_generation( $provider_id ) ) {
			continue;
		}

		$api_key = abcc_get_provider_api_key_for_health_check( $provider_id );

		if ( '' === $api_key ) {
			abcc_record_provider_no_key( $provider_id );
			continue;
		}

		$valid = abcc_validate_provider_api_key_probe( $provider_id, $api_key );

		abcc_record_provider_validation(
			$provider_id,
			$valid,
			$valid
				? __( 'Validated automatically', 'automated-blog-content-creator' )
				: __( 'Connection failed', 'automated-blog-content-creator' ),
			2 * DAY_IN_SECONDS
		);
	}
}

/**
 * Key the daily health check probes: Connectors, wp-config, then the saved option.
 *
 * @param string $provider_id Provider ID from registry.
 * @return string
 */
function abcc_get_provider_api_key_for_health_check( $provider_id ) {
	return (string) abcc_get_provider_api_key( $provider_id );
}

/**
 * Validate a provider API key with a real connection test.
 *
 * @param string $provider_id Provider ID.
 * @param string $api_key     API key to test.
 * @return bool True if the key passes the provider connection test.
 */
function abcc_validate_provider_api_key_probe( $provider_id, $api_key ) {
	if ( empty( $api_key ) ) {
		return false;
	}

	$result = abcc_test_provider_connection( $provider_id, $api_key );

	return ! is_wp_error( $result ) && ! empty( $result['success'] );
}

/**
 * Shared guard for topic AJAX handlers: nonce + capability.
 *
 * @since 4.2.0
 * @return bool True when the request may proceed (error already sent otherwise).
 */
function abcc_topic_ajax_guard() {
	check_ajax_referer( 'abcc_topic_nonce', 'nonce' );

	if ( ! abcc_current_user_can_prompt() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return false;
	}

	return true;
}

/**
 * Read the common topic fields from $_POST.
 *
 * Nonce verification happens in abcc_topic_ajax_guard() before this runs.
 *
 * @since 4.2.0
 * @return array
 */
function abcc_topic_args_from_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in abcc_topic_ajax_guard().
	$args = array();

	if ( isset( $_POST['title'] ) ) {
		$args['title'] = sanitize_text_field( wp_unslash( $_POST['title'] ) );
	}
	if ( isset( $_POST['prompt'] ) ) {
		$args['prompt'] = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) );
	}
	if ( isset( $_POST['frequency'] ) ) {
		$args['frequency'] = sanitize_key( wp_unslash( $_POST['frequency'] ) );
	}
	if ( isset( $_POST['post_status_override'] ) ) {
		$args['post_status_override'] = sanitize_key( wp_unslash( $_POST['post_status_override'] ) );
	}
	if ( isset( $_POST['provider_override'] ) ) {
		$args['provider_override'] = sanitize_text_field( wp_unslash( $_POST['provider_override'] ) );
	}
	if ( isset( $_POST['keywords'] ) ) {
		$args['keywords'] = sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) );
	}
	if ( isset( $_POST['target_post_type'] ) ) {
		$args['target_post_type'] = sanitize_key( wp_unslash( $_POST['target_post_type'] ) );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	return $args;
}

/**
 * AJAX: create a topic.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_topic_create() {
	if ( ! abcc_topic_ajax_guard() ) {
		return;
	}

	$args              = abcc_topic_args_from_request();
	$args['author_id'] = get_current_user_id();

	$topic_id = abcc_create_topic( $args );

	if ( is_wp_error( $topic_id ) ) {
		wp_send_json_error( array( 'message' => $topic_id->get_error_message() ) );
		return;
	}

	wp_send_json_success( array( 'topic' => abcc_get_topic( $topic_id ) ) );
}
add_action( 'wp_ajax_abcc_topic_create', 'abcc_handle_topic_create' );

/**
 * AJAX: update a topic.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_topic_update() {
	if ( ! abcc_topic_ajax_guard() ) {
		return;
	}

	$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in abcc_topic_ajax_guard().
	$result   = abcc_update_topic( $topic_id, abcc_topic_args_from_request() );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		return;
	}

	wp_send_json_success( array( 'topic' => abcc_get_topic( $topic_id ) ) );
}
add_action( 'wp_ajax_abcc_topic_update', 'abcc_handle_topic_update' );

/**
 * AJAX: delete a topic (generated posts are never touched).
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_topic_delete() {
	if ( ! abcc_topic_ajax_guard() ) {
		return;
	}

	$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in abcc_topic_ajax_guard().

	if ( ! abcc_delete_topic( $topic_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Topic not found.', 'automated-blog-content-creator' ) ) );
		return;
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_abcc_topic_delete', 'abcc_handle_topic_delete' );

/**
 * AJAX: pause or resume a topic.
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_topic_toggle() {
	if ( ! abcc_topic_ajax_guard() ) {
		return;
	}

	$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in abcc_topic_ajax_guard().
	$topic    = abcc_get_topic( $topic_id );

	if ( null === $topic ) {
		wp_send_json_error( array( 'message' => __( 'Topic not found.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$success = $topic['active'] ? abcc_pause_topic( $topic_id ) : abcc_resume_topic( $topic_id );

	if ( ! $success ) {
		wp_send_json_error( array( 'message' => __( 'Could not change the topic status.', 'automated-blog-content-creator' ) ) );
		return;
	}

	wp_send_json_success( array( 'topic' => abcc_get_topic( $topic_id ) ) );
}
add_action( 'wp_ajax_abcc_topic_toggle', 'abcc_handle_topic_toggle' );

/**
 * AJAX: queue a topic generation immediately (out-of-band; next_run untouched).
 *
 * @since 4.2.0
 * @return void
 */
function abcc_handle_topic_run_now() {
	if ( ! abcc_topic_ajax_guard() ) {
		return;
	}

	$topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in abcc_topic_ajax_guard().
	$job_id   = abcc_queue_topic_generation( $topic_id );

	if ( is_wp_error( $job_id ) ) {
		wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		return;
	}

	// The failure streak resets when the job succeeds, not when it is queued.
	update_post_meta( $topic_id, '_abcc_topic_last_job_id', (int) $job_id );
	update_post_meta( $topic_id, '_abcc_topic_last_run', time() );

	wp_send_json_success( array( 'job_id' => (int) $job_id ) );
}
add_action( 'wp_ajax_abcc_topic_run_now', 'abcc_handle_topic_run_now' );
