<?php
/**
 * File: gpt.php
 *
 * This file contains functions for interacting with various AI APIs (Claude, Gemini, OpenAI)
 * for text and image generation.
 *
 * @package WP-AutoInsight
 * @version 4.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Call a text-generation provider API and return a normalized result tuple.
 *
 * Single transport + parse layer shared by the four text providers (Claude,
 * OpenAI, Gemini, Perplexity). Adapter direction: the public
 * abcc_*_generate_text() functions call THIS wrapper and convert the tuple
 * back to their legacy return shapes, so abcc_generate_content() and the
 * provider registry callbacks need zero changes.
 *
 * Truncation signals (verified against provider docs, 2026-06):
 * - OpenAI / Perplexity: choices[0].finish_reason === 'length'
 * - Claude:              stop_reason === 'max_tokens'
 * - Gemini:              candidates[0].finishReason === 'MAX_TOKENS'
 *
 * Text-only in v4.2 — image generation stays on its own path.
 *
 * @since 4.2.0
 * @param string $provider Provider ID (openai|claude|gemini|perplexity).
 * @param string $model    Model identifier.
 * @param string $prompt   Text prompt.
 * @param array  $opts     Options: api_key (string), max_tokens (int),
 *                         temperature (float, OpenAI only).
 * @return array{content: array, usage: array, truncated: bool, error: WP_Error|null, raw: array|null}
 *               On failure 'error' is a WP_Error and 'content' is []. Never throws.
 */
function abcc_call_provider_api( $provider, $model, $prompt, $opts = array() ) {
	$result = array(
		'content'   => array(),
		'usage'     => array(),
		'truncated' => false,
		'error'     => null,
		'raw'       => null,
	);

	$api_key          = isset( $opts['api_key'] ) ? (string) $opts['api_key'] : '';
	$requested_tokens = isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800;
	$available_tokens = abcc_calculate_available_tokens( $prompt, $requested_tokens, $model );
	$label            = ucfirst( $provider );

	if ( $available_tokens <= 0 ) {
		$result['error'] = new WP_Error( 'context_overflow', __( 'Prompt exceeds model context — reduce keywords or character limit.', 'automated-blog-content-creator' ) );
		return $result;
	}

	// --- Transport: produce a decoded response body or an error. ---
	if ( 'openai' === $provider ) {
		// OpenAI rides the existing client (custom-endpoint support, model verification).
		$client   = new ABCC_OpenAI_Client( $api_key );
		$response = $client->create_chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => wp_kses_post( $prompt ),
				),
			),
			array(
				'model'       => $model,
				'max_tokens'  => $available_tokens,
				'temperature' => isset( $opts['temperature'] ) ? (float) $opts['temperature'] : 0.8,
			)
		);

		if ( is_wp_error( $response ) ) {
			abcc_handle_api_request_error( $response, 'OpenAI' );
			$result['error'] = $response;
			return $result;
		}

		$data = $response;
	} else {
		switch ( $provider ) {
			case 'claude':
				$url     = 'https://api.anthropic.com/v1/messages';
				$headers = array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				);
				$body    = array(
					'model'      => $model,
					'max_tokens' => $available_tokens,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => wp_kses_post( $prompt ),
						),
					),
				);
				break;

			case 'gemini':
				$url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
				$headers = array( 'Content-Type' => 'application/json' );
				$body    = array(
					'contents'         => array(
						array( 'parts' => array( array( 'text' => wp_kses_post( $prompt ) ) ) ),
					),
					'generationConfig' => array( 'maxOutputTokens' => $available_tokens ),
				);
				break;

			case 'perplexity':
				$url     = 'https://api.perplexity.ai/chat/completions';
				$headers = array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				);
				$body    = array(
					'model'      => $model,
					'max_tokens' => $available_tokens,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => wp_kses_post( $prompt ),
						),
					),
				);

				$recency = abcc_get_setting( 'abcc_perplexity_recency_filter', '' );
				if ( ! empty( $recency ) ) {
					$body['search_recency_filter'] = $recency;
				}
				break;

			default:
				$result['error'] = new WP_Error( 'abcc_unknown_provider', sprintf( 'Unknown text provider: %s', $provider ) );
				return $result;
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			abcc_debug_log( $label . ' API Error: ' . $response->get_error_message() );
			$result['error'] = $response;
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			abcc_debug_log( $label . ' API HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			$result['error'] = new WP_Error( 'abcc_provider_http_error', sprintf( '%s API returned HTTP %d', $label, $code ) );
			return $result;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
	}

	// --- Parse: extract text, usage, and the per-provider truncation signal. ---
	$result['raw'] = is_array( $data ) ? $data : null;

	switch ( $provider ) {
		case 'claude':
			$text                = $data['content'][0]['text'] ?? null;
			$result['usage']     = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
			$result['truncated'] = isset( $data['stop_reason'] ) && 'max_tokens' === $data['stop_reason'];
			break;

		case 'gemini':
			$text                = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
			$result['usage']     = isset( $data['usageMetadata'] ) && is_array( $data['usageMetadata'] ) ? $data['usageMetadata'] : array();
			$result['truncated'] = 'MAX_TOKENS' === ( $data['candidates'][0]['finishReason'] ?? '' );
			break;

		case 'openai':
		case 'perplexity':
			$text                = $data['choices'][0]['message']['content'] ?? null;
			$result['usage']     = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
			$result['truncated'] = 'length' === ( $data['choices'][0]['finish_reason'] ?? '' );
			break;
	}

	if ( null === $text || ! is_string( $text ) ) {
		abcc_debug_log( 'Unexpected ' . $label . ' response structure: ' . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$result['error'] = new WP_Error( 'abcc_provider_parse_error', sprintf( 'Unexpected %s response structure', $label ) );
		return $result;
	}

	// HTTP 200 with no text: reasoning models can burn the whole completion
	// budget on hidden reasoning tokens and return an empty message. Without
	// this guard the empty string cascades into a generic, unlogged
	// "Content generation failed" downstream.
	if ( '' === trim( $text ) ) {
		$detail = $result['truncated']
			? ' — the token budget was consumed before any text was produced; raise the character limit'
			: '';
		abcc_debug_log( $label . ' returned an empty completion' . $detail . '. Usage: ' . wp_json_encode( $result['usage'] ) );
		$result['error'] = new WP_Error( 'abcc_provider_empty_completion', sprintf( '%s returned an empty completion%s', $label, $detail ) );
		return $result;
	}

	// Models sometimes return the whole HTML post on a single line. Downstream
	// block creation is line-oriented (one heading/paragraph per line), so
	// break after each closing block-level tag before splitting.
	$text = preg_replace( '#(</(?:p|h[1-6]|ul|ol|blockquote)>)\s*#i', "$1\n", $text );

	$result['content'] = explode( "\n", str_replace( "\r\n", "\n", $text ) );

	return $result;
}

/**
 * Generates text using Claude API.
 *
 * @since 1.0.0
 * @param string $api_key          API key for Claude.
 * @param string $prompt           Text prompt for generating content.
 * @param int    $requested_tokens Number of tokens requested.
 * @param string $model            Model to use for generation.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_claude_generate_text( $api_key, $prompt, $requested_tokens, $model ) {
	$result = abcc_call_provider_api(
		'claude',
		$model,
		$prompt,
		array(
			'api_key'    => $api_key,
			'max_tokens' => $requested_tokens,
		)
	);

	return is_wp_error( $result['error'] ) ? false : $result['content'];
}

/**
 * Generates text using Gemini API.
 *
 * @since 1.0.0
 * @param string $api_key          API key for Gemini API.
 * @param string $prompt           Text prompt for generating content.
 * @param int    $requested_tokens Number of tokens requested.
 * @param string $model            Model to use for generation.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_gemini_generate_text( $api_key, $prompt, $requested_tokens, $model = 'gemini-2.5-flash' ) {
	$result = abcc_call_provider_api(
		'gemini',
		$model,
		$prompt,
		array(
			'api_key'    => $api_key,
			'max_tokens' => $requested_tokens,
		)
	);

	return is_wp_error( $result['error'] ) ? false : $result['content'];
}

/**
 * Generates text using OpenAI's API or a custom OpenAI-compatible endpoint.
 *
 * @since 1.0.0
 * @param string $api_key          API key for OpenAI.
 * @param string $prompt           Text prompt.
 * @param int    $requested_tokens Maximum number of tokens.
 * @param string $model            Model to use.
 * @return array|false An array containing lines of generated text, or false on failure.
 */
function abcc_openai_generate_text( $api_key, $prompt, $requested_tokens, $model ) {
	$result = abcc_call_provider_api(
		'openai',
		$model,
		$prompt,
		array(
			'api_key'    => $api_key,
			'max_tokens' => $requested_tokens,
		)
	);

	return is_wp_error( $result['error'] ) ? false : $result['content'];
}

/**
 * Generates text using Perplexity API.
 *
 * @since 3.3.0
 * @param string $api_key          API key for Perplexity.
 * @param string $prompt           Text prompt for generating content.
 * @param int    $requested_tokens Number of tokens requested.
 * @param string $model            Model to use for generation.
 * @return array|false Associative array with 'text' (array of lines) and 'citations' (array of URLs), or false on failure.
 */
function abcc_perplexity_generate_text( $api_key, $prompt, $requested_tokens, $model ) {
	$result = abcc_call_provider_api(
		'perplexity',
		$model,
		$prompt,
		array(
			'api_key'    => $api_key,
			'max_tokens' => $requested_tokens,
		)
	);

	if ( is_wp_error( $result['error'] ) ) {
		return false;
	}

	// Citations come from the raw decoded body — the normalized tuple stays provider-agnostic.
	$citations = isset( $result['raw']['citations'] ) && is_array( $result['raw']['citations'] ) ? $result['raw']['citations'] : array();

	return array(
		'text'      => $result['content'],
		'citations' => $citations,
	);
}

/**
 * Generates images using OpenAI's GPT Image models, falling back to Stability AI on failure.
 *
 * GPT Image models (gpt-image-1, gpt-image-1-mini, gpt-image-1.5) replaced the
 * deprecated dall-e-3 and return base64 payloads, which are saved locally and
 * returned as public URLs.
 *
 * @since 1.0.0
 * @param string $api_key       API key for OpenAI.
 * @param string $prompt        Text prompt.
 * @param string $n             Number of images to generate.
 * @param string $image_size    Size of the generated images.
 * @param string $image_quality Image quality (low, medium, high).
 * @param string $model         GPT Image model to use.
 * @return array|false Array of image URLs or false on failure.
 */
function abcc_openai_generate_images( $api_key, $prompt, $n, $image_size = '1024x1024', $image_quality = 'medium', $model = 'gpt-image-1' ) {
	$client = new ABCC_OpenAI_Client( $api_key );

	// Translate any stale DALL-E 3 values still stored in settings to their
	// GPT Image equivalents. dall-e-3 was deprecated 2026-05-12 and its sizes
	// (1792x1024 / 1024x1792) and quality (standard / hd) are rejected by the
	// GPT Image models. This normalizes at runtime so existing installs keep
	// working without a settings re-save.
	$size_map   = array(
		'1792x1024' => '1536x1024',
		'1024x1792' => '1024x1536',
	);
	$image_size = $size_map[ $image_size ] ?? $image_size;

	$quality_map   = array(
		'standard' => 'medium',
		'hd'       => 'high',
	);
	$image_quality = $quality_map[ $image_quality ] ?? $image_quality;

	$options = array(
		'model'   => $model,
		'n'       => absint( $n ),
		'size'    => $image_size,
		'quality' => $image_quality,
	);

	$response = $client->create_image( wp_kses_post( $prompt ), $options );

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( 'OpenAI Image Generation Error: ' . $response->get_error_message() );

		// Get Stability AI key for fallback.
		$stability_key = abcc_get_provider_api_key( 'stability' );
		$fallback_url  = abcc_stability_generate_images( $prompt, $n, $stability_key );
		return $fallback_url ? array( $fallback_url ) : false;
	}

	if ( empty( $response['data'] ) ) {
		abcc_debug_log( 'OpenAI Image API: Missing expected data in response' );

		// Get Stability AI key for fallback.
		$stability_key = abcc_get_provider_api_key( 'stability' );
		$fallback_url  = abcc_stability_generate_images( $prompt, $n, $stability_key );
		return $fallback_url ? array( $fallback_url ) : false;
	}

	// GPT Image models return base64 (data[].b64_json), not URLs. Persist each
	// image to the uploads directory and return its public URL, mirroring the
	// Gemini and Stability paths.
	$urls = array();
	foreach ( $response['data'] as $item ) {
		if ( empty( $item['b64_json'] ) ) {
			continue;
		}
		$saved = abcc_save_base64_image( $item['b64_json'], 'image/png', 'openai' );
		if ( $saved ) {
			$urls[] = $saved;
		}
	}

	if ( empty( $urls ) ) {
		abcc_debug_log( 'OpenAI Image API: No image data could be saved from response' );

		// Get Stability AI key for fallback.
		$stability_key = abcc_get_provider_api_key( 'stability' );
		$fallback_url  = abcc_stability_generate_images( $prompt, $n, $stability_key );
		return $fallback_url ? array( $fallback_url ) : false;
	}

	return $urls;
}

/**
 * Generates images using Stability AI's API as a fallback.
 *
 * @param string $prompt Text prompt.
 * @param int    $n Number of images to generate.
 * @param string $stability_key Stability AI API key.
 * @param string $image_size Resolution in WxH format.
 * @return array|false Array of image URLs or false on failure.
 */
function abcc_stability_generate_images( $prompt, $n, $stability_key, $image_size = '1024x1024' ) {
	if ( empty( $stability_key ) ) {
		abcc_debug_log( 'Stability AI API key not provided' );
		return false;
	}

	$headers = array(
		'Content-Type'  => 'application/json',
		'Authorization' => 'Bearer ' . $stability_key,
		'Accept'        => 'application/json',
	);

	// Parse size.
	$width  = 1024;
	$height = 1024;
	if ( strpos( $image_size, 'x' ) !== false ) {
		list( $width, $height ) = array_map( 'intval', explode( 'x', $image_size ) );
	}

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
		'height'       => $height,
		'width'        => $width,
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
		abcc_debug_log( 'Stability AI API Error: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		abcc_debug_log( 'Stability AI API Error: Response code ' . $response_code );
		abcc_debug_log( 'Response body: ' . wp_remote_retrieve_body( $response ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['artifacts'] ) || ! is_array( $body['artifacts'] ) ) {
		abcc_debug_log( 'Stability AI: Unexpected response format: ' . print_r( $body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		return false;
	}

	// Process only the first image.
	if ( ! empty( $body['artifacts'][0]['base64'] ) ) {
		return abcc_save_base64_image( $body['artifacts'][0]['base64'], 'image/png', 'stability' );
	}

	abcc_debug_log( 'Stability AI: No valid image data in response' );
	return false;
}

/**
 * Decode a base64 image, validate its magic bytes, and save it to the uploads directory.
 *
 * Centralizes the save logic shared by the Stability AI and Gemini image
 * generators. Validation rejects payloads whose content does not match the
 * declared MIME type.
 *
 * @since 4.2.0
 * @param string $b64    Base64-encoded image data.
 * @param string $mime   Declared MIME type (image/png, image/jpeg, image/webp).
 * @param string $prefix Filename prefix (e.g. 'stability', 'gemini').
 * @return string|false Public URL on success, false on failure.
 */
function abcc_save_base64_image( $b64, $mime, $prefix ) {
	$extensions = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	if ( ! isset( $extensions[ $mime ] ) ) {
		abcc_debug_log( 'Image save: Unsupported MIME type ' . $mime );
		return false;
	}

	$image_data = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Provider APIs return images as base64; magic bytes validated below.
	if ( false === $image_data ) {
		abcc_debug_log( 'Image save: Invalid base64 image data (' . $prefix . ')' );
		return false;
	}

	// Validate magic bytes against the declared MIME type.
	$is_valid = false;
	if ( 'image/png' === $mime && substr( $image_data, 0, 4 ) === "\x89PNG" ) {
		$is_valid = true;
	} elseif ( 'image/jpeg' === $mime && substr( $image_data, 0, 2 ) === "\xFF\xD8" ) {
		$is_valid = true;
	} elseif ( 'image/webp' === $mime && substr( $image_data, 8, 4 ) === 'WEBP' ) {
		$is_valid = true;
	}

	if ( ! $is_valid ) {
		abcc_debug_log( 'Image save: Decoded data does not match declared MIME type ' . $mime . ' (' . $prefix . ')' );
		return false;
	}

	// Create uploads directory if it doesn't exist.
	$upload_dir = wp_upload_dir();
	if ( ! file_exists( $upload_dir['path'] ) ) {
		wp_mkdir_p( $upload_dir['path'] );
	}

	$filename = $prefix . '-' . uniqid() . '.' . $extensions[ $mime ];
	$filepath = $upload_dir['path'] . '/' . $filename;

	// TODO v4.x: migrate to WP_Filesystem (single spot now that save logic is centralized).
	if ( file_put_contents( $filepath, $image_data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $upload_dir['url'] . '/' . $filename;
	}

	abcc_debug_log( 'Failed to save ' . $prefix . ' image to filesystem' );
	return false;
}

/**
 * Generates images using Google Gemini's Nano Banana API.
 *
 * @since 3.2.0
 * @param string $api_key    Gemini API key.
 * @param string $prompt     Text prompt for image generation.
 * @param string $model      Model to use ('gemini-2.5-flash-image' or 'gemini-3-pro-image-preview').
 * @param string $image_size Image size ('1K', '2K', or '4K').
 * @return string|false Image URL on success, false on failure.
 */
function abcc_gemini_generate_images( $api_key, $prompt, $model = 'gemini-2.5-flash-image', $image_size = '2K' ) {
	if ( empty( $api_key ) ) {
		abcc_debug_log( 'Gemini API key not provided for image generation' );
		return false;
	}

	// Validate model.
	$valid_models = array( 'gemini-2.5-flash-image', 'gemini-3-pro-image-preview' );
	if ( ! in_array( $model, $valid_models, true ) ) {
		$model = 'gemini-2.5-flash-image';
	}

	// Validate image size.
	$valid_sizes = array( '1K', '2K', '4K' );
	if ( ! in_array( $image_size, $valid_sizes, true ) ) {
		$image_size = '2K';
	}

	$endpoint = sprintf(
		'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
		rawurlencode( $model ),
		rawurlencode( $api_key )
	);

	$body = array(
		'contents'         => array(
			array(
				'parts' => array(
					array(
						'text' => sanitize_text_field( $prompt ),
					),
				),
			),
		),
		'generationConfig' => array(
			'responseModalities' => array( 'IMAGE' ),
			'imageConfig'        => array(
				'imageSize' => $image_size,
			),
		),
	);

	$response = wp_remote_post(
		$endpoint,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		)
	);

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( 'Gemini Image API Error: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $response_code ) {
		abcc_debug_log( 'Gemini Image API Error: Response code ' . $response_code );
		abcc_debug_log( 'Response body: ' . wp_remote_retrieve_body( $response ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// Look for image data in response.
	if ( empty( $body['candidates'][0]['content']['parts'] ) ) {
		abcc_debug_log( 'Gemini Image: Unexpected response format: ' . print_r( $body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		return false;
	}

	// Find the image part in the response.
	foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
		if ( isset( $part['inlineData']['data'] ) && isset( $part['inlineData']['mimeType'] ) ) {
			return abcc_save_base64_image( $part['inlineData']['data'], $part['inlineData']['mimeType'], 'gemini' );
		}
	}

	abcc_debug_log( 'Gemini Image: No valid image data in response' );
	return false;
}
