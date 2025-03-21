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
		'claude-3-haiku'             => 'claude-3-haiku-20240307',
		'claude-3-sonnet'            => 'claude-3-sonnet-20240229',
		'claude-3-opus'              => 'claude-3-opus-20240229',
		'claude-3.5-haiku-20241022'  => 'claude-3.5-haiku-20241022',
		'claude-3.5-sonnet-20241022' => 'claude-3.5-sonnet-20241022',
		'claude-3.7-sonnet-20250219' => 'claude-3.7-sonnet-20250219',
	);

		// For backward compatibility
	if ( isset( $model_mapping[ $model ] ) ) {
		$model = $model_mapping[ $model ];
	}

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
function abcc_gemini_generate_text( $api_key, $prompt, $requested_tokens, $model = 'gemini-pro' ) {
	// Map plugin model names to Gemini library model names
	$model_mapping = array(
		'gemini-pro'               => 'models/gemini-pro', // Legacy compatibility
		'gemini-1.5-flash'         => 'models/gemini-1.5-flash',
		'gemini-1.5-flash-8b'      => 'models/gemini-1.5-flash-8b',
		'gemini-1.5-pro'           => 'models/gemini-1.5-pro',
		'gemini-2.0-flash'         => 'models/gemini-2.0-flash',
		'gemini-2.0-flash-lite'    => 'models/gemini-2.0-flash-lite',
		'gemini-2.0-pro-exp-02-05' => 'gemini-2.0-pro-exp-02-05',
	);

	// Calculate available tokens for response
	$available_tokens = abcc_calculate_available_tokens( $prompt, $requested_tokens, $model );

	// Initialize Gemini client
	$gemini = new \GeminiAPI\Client( $api_key );

	// Use the model mapping to get the correct model name
	$model_id = isset( $model_mapping[ $model ] ) ? $model_mapping[ $model ] : $model;

	// Create a text part from the prompt
	$text_part = new \GeminiAPI\Resources\Parts\TextPart( $prompt );

	try {
		// For newer models (2.0), you might need beta version access
		if ( strpos( $model, '2.0' ) !== false ) {
			$gemini = $gemini->withV1BetaVersion();
		}

		// Generate content using the specified model
		$response = $gemini->generativeModel( $model_id )
			->generateContent( $text_part );

		// Return the response text split by newlines
		$text_array = explode( PHP_EOL, $response->text() );
		return $text_array;
	} catch ( \Exception $e ) {
		error_log( 'Gemini API Error: ' . $e->getMessage() );
		handle_api_request_error( $e->getMessage(), 'Gemini' );
		return false;
	}
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
