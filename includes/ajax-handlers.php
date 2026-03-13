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

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$tone          = get_option( 'openai_tone', 'default' );
		$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );

		$result = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			false,
			$char_limit
		);

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Post created successfully!', 'automated-blog-content-creator' ),
				'post_id' => $result,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
/**
 * AJAX handler for validating API keys.
 *
 * @since 3.4.0
 */
function abcc_handle_validate_api_key() {
	check_ajax_referer( 'abcc_openai_generate_post', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized', 'automated-blog-content-creator' ) ) );
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


add_action( 'wp_ajax_openai_generate_post', 'abcc_openai_generate_post_ajax' );

/**
 * AJAX handler for generating a post (legacy compatibility).
 */
function abcc_openai_generate_post_ajax() {
	check_ajax_referer( 'abcc_openai_generate_post', '_ajax_nonce' );

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$tone          = get_option( 'openai_tone', 'default' );
		$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );

		$result = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			false,
			$char_limit
		);

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Post created successfully!', 'automated-blog-content-creator' ),
				'post_id' => $result,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
