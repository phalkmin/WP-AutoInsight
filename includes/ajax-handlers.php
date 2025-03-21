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
 * AJAX handler for refreshing available models.
 */
function abcc_refresh_models_ajax() {
	check_ajax_referer( 'abcc_openai_generate_post', 'abcc_openai_nonce' );

	delete_transient( 'abcc_available_models' );
	$models = abcc_get_available_models();

	wp_send_json_success(
		array(
			'models' => $models,
		)
	);
}
add_action( 'wp_ajax_abcc_refresh_models', 'abcc_refresh_models_ajax' );

/**
 * AJAX handler for generating a post.
 */
function abcc_openai_generate_post_ajax() {
	check_ajax_referer( 'abcc_openai_generate_post' );

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$tone          = get_option( 'openai_tone', 'default' );
		$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );

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
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
				'details' => WP_DEBUG ? $e->getTraceAsString() : null,
			)
		);
	}
}
add_action( 'wp_ajax_openai_generate_post', 'abcc_openai_generate_post_ajax' );

/**
 * AJAX handler for estimating token usage.
 */
function abcc_estimate_tokens_ajax() {
	check_ajax_referer( 'abcc_openai_generate_post', 'abcc_openai_nonce' );

	$char_limit = absint( $_POST['char_limit'] );
	$model      = sanitize_text_field( $_POST['model'] );

	$available_tokens = abcc_calculate_available_tokens(
		abcc_build_content_prompt( array(), 'default', array(), $char_limit ),
		$char_limit,
		$model
	);

	wp_send_json_success(
		array(
			'message' => sprintf(
				// translators: %d is the number of available tokens
				__( 'Estimated available tokens: %d (after prompt tokens)', 'automated-blog-content-creator' ),
				$available_tokens
			),
		)
	);
}
add_action( 'wp_ajax_abcc_estimate_tokens', 'abcc_estimate_tokens_ajax' );
