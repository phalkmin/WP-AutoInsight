<?php

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

/**
 * Generates text using Claude API.
 *
 * @param string $api_key API key for Claude.
 * @param string $prompt Text prompt for generating content.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_claude_generate_text( $api_key, $prompt, $requested_tokens, $model ) {

	$model_mapping = array(
		'claude-3-haiku'  => 'claude-3-haiku-20240307',
		'claude-3-sonnet' => 'claude-3-sonnet-20240229',
		'claude-3-opus'   => 'claude-3-opus-20240229',
	);

	$headers = array(
		'Content-Type'      => 'application/json',
		'x-api-key'         => $api_key,
		'anthropic-version' => '2023-06-01',
	);

	$available_tokens = abcc_calculate_available_tokens( $prompt, $requested_tokens, $model );

	$body = array(
		'model'      => $model,
		'max_tokens' => $available_tokens,
		'messages'   => array(
			array(
				'role'    => 'user',
				'content' => wp_kses_post( $prompt ),
			),
		),
	);

	$response = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		)
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'Claude API Error: ' . $response->get_error_message() );
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! isset( $data['content'][0]['text'] ) ) {
		error_log( 'Unexpected Claude response structure: ' . print_r( $data, true ) );
		return false;
	}

	$text_array = explode( PHP_EOL, $data['content'][0]['text'] );
	return $text_array;
}

/**
 * Generates text using Gemini API.
 *
 * @param string $api_key API key for Gemini API.
 * @param string $prompt Text prompt for generating content.
 * @param string $length Length of the generated content.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_gemini_generate_text( $api_key, $prompt, $requested_tokens ) {
	$available_tokens = abcc_calculate_available_tokens( $prompt, $requested_tokens, 'gemini-pro' );

	$gemini = new Client( $api_key );
	$chat   = $gemini->geminiPro()->startChat(
		array(
			'maxOutputTokens' => $available_tokens,
		)
	);

	$response = $chat->sendMessage( new TextPart( $prompt ) );

	if ( is_wp_error( $response ) ) {
		handle_api_request_error( $response, 'Gemini' );
		return false;
	}

	$text_array = explode( PHP_EOL, $response->text() );
	return $text_array;
}

/**
 * Generates text using OpenAI's API or a custom OpenAI-compatible endpoint.
 *
 * @param string $api_key API key for OpenAI.
 * @param string $prompt Text prompt.
 * @param int    $length Maximum number of tokens.
 * @param string $prompt_select Model to use.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_openai_generate_text( $api_key, $prompt, $requested_tokens, $model ) {
	$available_tokens = abcc_calculate_available_tokens( $prompt, $requested_tokens, $model );

	$client   = new ABCC_OpenAI_Client( $api_key );
	$messages = array(
		array(
			'role'    => 'user',
			'content' => wp_kses_post( $prompt ),
		),
	);

	$options = array(
		'model'       => $model,
		'max_tokens'  => $available_tokens,
		'temperature' => 0.8,
	);

	$response = $client->create_chat_completion( $messages, $options );

	if ( is_wp_error( $response ) ) {
		handle_api_request_error( $response, 'OpenAI' );
		return false;
	}

	if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
		error_log( 'Unexpected OpenAI response structure: ' . print_r( $response, true ) );
		return false;
	}

	$text = $response['choices'][0]['message']['content'];
	return explode( PHP_EOL, $text );
}

/**
 * Generates images using OpenAI's DALL-E or falls back to Stability AI if OpenAI fails.
 *
 * @param string $api_key API key for OpenAI.
 * @param string $prompt Text prompt.
 * @param string $n Number of images to generate.
 * @param string $image_size Size of the generated images.
 * @return array|false Array of image URLs or false on failure.
 */
function abcc_openai_generate_images( $api_key, $prompt, $n, $image_size = '1792x1024' ) {
	$client = new ABCC_OpenAI_Client( $api_key );

	// Set custom endpoint if configured
	$custom_endpoint = get_option( 'openai_custom_endpoint', '' );
	if ( ! empty( $custom_endpoint ) ) {
		$client->set_base_url( $custom_endpoint );
	}

	$options = array(
		'n'    => absint( $n ),
		'size' => $image_size,
	);

	$response = $client->create_image( wp_kses_post( $prompt ), $options );

	if ( is_wp_error( $response ) ) {
		error_log( 'OpenAI Image Generation Error: ' . $response->get_error_message() );
		return abcc_stability_generate_images( $prompt, $n );
	}

	if ( empty( $response['data'] ) ) {
		error_log( 'OpenAI Image API: Missing expected data in response' );
		return abcc_stability_generate_images( $prompt, $n );
	}

	$urls = array_map(
		function ( $item ) {
			return $item['url'] ?? null;
		},
		$response['data']
	);

	return array_filter( $urls );
}

/**
 * Generates images using Stability AI's API as a fallback.
 *
 * @param string $prompt Text prompt.
 * @param int    $n Number of images to generate.
 * @return array|false Array of image URLs or false on failure.
 */
function abcc_stability_generate_images( $prompt, $n, $stability_key ) {
	if ( empty( $stability_key ) ) {
		error_log( 'Stability AI API key not provided' );
		return false;
	}

	$headers = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer ' . $stability_key,
		'Accept'        => 'application/json',
	);

	$body = array(
		'text_prompts' => array(
			array(
				'text'   => $prompt,
				'weight' => 1,
			),
		),
		'cfg_scale'    => 7,
		'steps'        => 30,
		'samples'      => absint( $n ),
		'height'       => 1024,
		'width'        => 1024,
		'style_preset' => 'photographic',
	);

	$response = wp_remote_post(
		'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
		array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		)
	);

	if ( is_wp_error( $response ) ) {
		error_log( 'Stability AI API Error: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		error_log( 'Stability AI API Error: Response code ' . $response_code );
		error_log( 'Response body: ' . wp_remote_retrieve_body( $response ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['artifacts'] ) || ! is_array( $body['artifacts'] ) ) {
		error_log( 'Stability AI: Unexpected response format: ' . print_r( $body, true ) );
		return false;
	}

	// Process only the first image
	if ( ! empty( $body['artifacts'][0]['base64'] ) ) {
		// Create uploads directory if it doesn't exist
		$upload_dir = wp_upload_dir();
		if ( ! file_exists( $upload_dir['path'] ) ) {
			wp_mkdir_p( $upload_dir['path'] );
		}

		// Generate unique filename
		$filename = 'stability-' . uniqid() . '.png';
		$filepath = $upload_dir['path'] . '/' . $filename;

		// Decode and save the image
		$image_data = base64_decode( $body['artifacts'][0]['base64'] );
		if ( file_put_contents( $filepath, $image_data ) ) {
			return $upload_dir['url'] . '/' . $filename;
		} else {
			error_log( 'Failed to save Stability AI image to filesystem' );
			return false;
		}
	}

	error_log( 'Stability AI: No valid image data in response' );
	return false;
}

/**
 * Validates custom API endpoints
 *
 * @param string $endpoint The endpoint URL to validate.
 * @return bool|WP_Error True if valid, WP_Error if not.
 */
function abcc_validate_custom_endpoint( $endpoint ) {
	if ( empty( $endpoint ) ) {
		return true; // Empty endpoint is valid (uses default)
	}

	$parsed_url = wp_parse_url( $endpoint );

	// Check for required URL components
	if ( ! $parsed_url || ! isset( $parsed_url['scheme'] ) || ! isset( $parsed_url['host'] ) ) {
		return new WP_Error(
			'invalid_endpoint',
			__( 'Invalid endpoint URL format. Must be a complete URL with http:// or https://', 'automated-blog-content-creator' )
		);
	}

	// Ensure HTTPS is used
	if ( $parsed_url['scheme'] !== 'https' ) {
		return new WP_Error(
			'insecure_endpoint',
			__( 'Endpoint URL must use HTTPS for security', 'automated-blog-content-creator' )
		);
	}

	// Optional: Add additional validation for specific endpoint patterns
	$valid_patterns = array(
		'/v1/$',
		'/v1/images$',
		'/api/v1/$',
		'/api/v1/images$',
	);

	$path          = isset( $parsed_url['path'] ) ? trailingslashit( $parsed_url['path'] ) : '/';
	$valid_pattern = false;

	foreach ( $valid_patterns as $pattern ) {
		if ( preg_match( $pattern, $path ) ) {
			$valid_pattern = true;
			break;
		}
	}

	if ( ! $valid_pattern ) {
		return new WP_Error(
			'invalid_endpoint_pattern',
			__( 'Endpoint URL pattern not recognized. Please check the documentation for valid endpoint formats.', 'automated-blog-content-creator' )
		);
	}

	return true;
}

/**
 * Sanitizes and validates custom endpoint before saving
 * Add this to your existing save_settings functionality
 */
function abcc_sanitize_custom_endpoints( $endpoint ) {
	$endpoint = esc_url_raw( trim( $endpoint ) );

	$validation = abcc_validate_custom_endpoint( $endpoint );
	if ( is_wp_error( $validation ) ) {
		add_settings_error(
			'openai-settings',
			'invalid-endpoint',
			$validation->get_error_message(),
			'error'
		);
		return ''; // Return empty if invalid
	}

	return $endpoint;
}
