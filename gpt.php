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
 * The registry names the transport, endpoint and auth; per-model flags shape
 * the request (includes/transport.php). The abcc_*_generate_text() adapters
 * convert the tuple to their legacy return shapes.
 *
 * Truncation signals:
 * - OpenAI-compatible: choices[0].finish_reason === 'length'
 * - Anthropic:         stop_reason === 'max_tokens'
 * - Gemini:            candidates[0].finishReason === 'MAX_TOKENS'
 *
 * @since 4.2.0
 * @since 4.6.0 Data-driven transport; new opts and the tokens / served_model keys.
 * @param string $provider Provider ID.
 * @param string $model    Model identifier or 'provider::model' selection.
 * @param string $prompt   User prompt.
 * @param array  $opts     api_key (string), max_tokens (int), temperature (float),
 *                         system (string), operation (string), response_format
 *                         (array{schema, name}), require_json (bool), timeout (int).
 * @return array{content: array, usage: array, tokens: array, truncated: bool, error: WP_Error|null, raw: array|null, served_model: string}
 *               On failure 'error' is a WP_Error and 'content' is []. 'served_model' is
 *               the bare ID actually sent ('' when nothing was sent). Never throws.
 */
function abcc_call_provider_api( $provider, $model, $prompt, $opts = array() ) {
	$result = array(
		'content'      => array(),
		'usage'        => array(),
		'tokens'       => array(
			'input'     => null,
			'output'    => null,
			'reasoning' => null,
		),
		'truncated'    => false,
		'error'        => null,
		'raw'          => null,
		'served_model' => '',
	);

	$provider  = sanitize_key( (string) $provider );
	$config    = abcc_get_provider( $provider );
	$transport = abcc_get_provider_transport( $provider );
	$selection = abcc_parse_model_selection( $model );

	if ( ! $config || '' === $transport ) {
		$result['error'] = new WP_Error( 'abcc_unknown_provider', sprintf( 'Unknown text provider: %s', $provider ) );
		return $result;
	}

	$label = ! empty( $config['name'] ) ? (string) $config['name'] : ucfirst( $provider );

	if ( $selection['qualified'] && $selection['provider'] !== $provider ) {
		$result['error'] = new WP_Error(
			'abcc_unknown_model',
			sprintf( 'Model selection does not belong to %s', $label ),
			array(
				'provider'           => $provider,
				'selection_provider' => $selection['provider'],
			)
		);
		return $result;
	}

	$model = $selection['model'];

	if ( '' === $model ) {
		$result['error'] = new WP_Error( 'abcc_unknown_model', sprintf( 'No model given for %s', $label ), array( 'provider' => $provider ) );
		return $result;
	}

	$live_model = abcc_resolve_retired_model( $provider, $model );
	if ( $live_model !== $model ) {
		abcc_debug_log( sprintf( '%s retired %s; sending %s instead.', $label, $model, $live_model ) );
		$model = $live_model;
	}
	$result['served_model'] = $model;

	$can_serve = abcc_provider_can_serve_request( $provider, $model, $opts );
	if ( is_wp_error( $can_serve ) ) {
		$result['error'] = $can_serve;
		return $result;
	}

	$flags    = abcc_get_model_flags( $provider, $model );
	$messages = abcc_build_provider_messages( $prompt, $opts, $flags );

	// Reserve, floor and max_output clamp in one place, shared with the cost estimate.
	$requested_tokens = abcc_resolve_output_budget( isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800, $provider, $model );
	$available_tokens = abcc_calculate_available_tokens( trim( $messages['system'] . "\n" . $messages['user'] ), $requested_tokens, $model, $provider );

	if ( $available_tokens <= 0 ) {
		$result['error'] = new WP_Error( 'context_overflow', __( 'Prompt exceeds model context — reduce keywords or character limit.', 'automated-blog-content-creator' ) );
		return $result;
	}

	$request = abcc_build_provider_request( $config, $provider, $model, $messages, $opts, $available_tokens, $flags );
	$args    = array(
		'headers' => $request['headers'],
		'body'    => wp_json_encode( $request['body'] ),
		'timeout' => $request['timeout'],
	);
	if ( null !== $request['redirection'] ) {
		$args['redirection'] = $request['redirection'];
	}
	// Core re-checks the host on every request, so a name that later resolves
	// to a private address is refused.
	if ( ! empty( $request['reject_unsafe_urls'] ) ) {
		$args['reject_unsafe_urls'] = true;
	}
	if ( '' === $request['url'] ) {
		$result['error'] = new WP_Error( 'abcc_unknown_provider', sprintf( '%s has no endpoint configured', $label ) );
		return $result;
	}

	$response = wp_remote_post( $request['url'], $args );

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( $label . ' API Error: ' . $response->get_error_message() );
		$result['error'] = $response;
		return $result;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code < 200 || $code >= 300 ) {
		$api_key = isset( $opts['api_key'] ) ? (string) $opts['api_key'] : '';
		$detail  = abcc_extract_provider_error_detail( $body, $api_key );
		abcc_debug_log( $label . ' API HTTP ' . $code . ( '' !== $detail ? ': ' . $detail : '' ) );

		$result['error'] = new WP_Error(
			abcc_classify_provider_http_status( $code ),
			sprintf( '%s API returned HTTP %d', $label, $code ),
			array(
				'status'   => $code,
				'provider' => $provider,
				'model'    => $model,
				'detail'   => $detail,
			)
		);
		return $result;
	}

	$data = json_decode( $body, true );

	// A gateway can answer 200 and carry the upstream provider's failure in the body.
	$embedded = abcc_get_embedded_provider_error( $data, isset( $opts['api_key'] ) ? (string) $opts['api_key'] : '' );
	if ( null !== $embedded ) {
		abcc_debug_log( $label . ' API returned an error in a 2xx response: ' . $embedded['detail'] );

		$result['error'] = new WP_Error(
			$embedded['status'] >= 400 ? abcc_classify_provider_http_status( $embedded['status'] ) : 'abcc_provider_server_error',
			sprintf( '%s API returned an error', $label ),
			array(
				'status'   => $embedded['status'] >= 400 ? $embedded['status'] : $code,
				'provider' => $provider,
				'model'    => $model,
				'detail'   => $embedded['detail'],
			)
		);
		return $result;
	}

	$result['raw'] = is_array( $data ) ? $data : null;

	// A router ID (openrouter/auto) is answered by some other model; record that one.
	if ( ! empty( $config['gateway'] ) && 0 === strpos( $model, 'openrouter/' ) && is_array( $data ) && ! empty( $data['model'] ) && is_string( $data['model'] ) ) {
		$result['served_model'] = sanitize_text_field( $data['model'] );
	}

	$parsed              = abcc_parse_provider_response( $transport, $data );
	$result['usage']     = $parsed['usage'];
	$result['tokens']    = $parsed['tokens'];
	$result['truncated'] = $parsed['truncated'];

	if ( $parsed['blocked'] ) {
		$result['error'] = new WP_Error(
			'abcc_provider_content_blocked',
			sprintf( '%s blocked the prompt or refused the response', $label ),
			array(
				'provider' => $provider,
				'model'    => $model,
				'reason'   => $parsed['block_reason'],
			)
		);
		return $result;
	}

	// Reasoning but no text: usage is kept, the reasoning itself is never logged.
	if ( $parsed['reasoning_only'] ) {
		abcc_debug_log( $label . ' returned reasoning and no text. Usage: ' . wp_json_encode( $result['usage'] ) );
		$result['error'] = new WP_Error(
			'abcc_reasoning_only',
			sprintf( '%s spent the output budget on reasoning and returned no text', $label ),
			array(
				'provider' => $provider,
				'model'    => $model,
			)
		);
		return $result;
	}

	$text = $parsed['text'];

	if ( null === $text || ! is_string( $text ) ) {
		abcc_debug_log( 'Unexpected ' . $label . ' response structure; response body omitted from the log.' );
		$result['error'] = new WP_Error( 'abcc_provider_parse_error', sprintf( 'Unexpected %s response structure', $label ) );
		return $result;
	}

	// HTTP 200 with no text: reasoning models can burn the whole completion
	// budget on hidden reasoning tokens and return an empty message.
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
 * GPT Image models return base64 payloads, which are saved locally and
 * returned as public URLs. Retired IDs are mapped to their successor first.
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
function abcc_openai_generate_images( $api_key, $prompt, $n, $image_size = '1024x1024', $image_quality = 'medium', $model = 'gpt-image-2.5-flare' ) {
	$client = new ABCC_OpenAI_Client( $api_key );
	$model  = abcc_resolve_image_model( 'openai', $model );

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

	$response = $client->create_image( $prompt, $options );

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( 'OpenAI Image Generation Error: ' . $response->get_error_message() );
		abcc_set_last_image_error( 'OpenAI: ' . $response->get_error_message() );

		return abcc_openai_image_stability_fallback( $prompt, $n );
	}

	if ( empty( $response['data'] ) ) {
		abcc_debug_log( 'OpenAI Image API: Missing expected data in response' );
		abcc_set_last_image_error( 'OpenAI: the image response contained no data.' );

		return abcc_openai_image_stability_fallback( $prompt, $n );
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
		abcc_set_last_image_error( 'OpenAI: the returned image could not be saved.' );

		return abcc_openai_image_stability_fallback( $prompt, $n );
	}

	return $urls;
}

/**
 * Retry a failed OpenAI image on Stability AI, keeping both reasons on failure.
 *
 * @since 4.5.2
 * @param string $prompt Text prompt.
 * @param int    $n      Number of images.
 * @return array|false Array of image URLs or false on failure.
 */
function abcc_openai_image_stability_fallback( $prompt, $n ) {
	$openai_reason = abcc_get_last_image_error();
	abcc_set_last_image_error( '' );

	$fallback_url = abcc_stability_generate_images( $prompt, $n, abcc_get_provider_api_key( 'stability' ) );
	if ( $fallback_url ) {
		return array( $fallback_url );
	}

	$stability_reason = abcc_get_last_image_error();
	abcc_set_last_image_error( '' !== $stability_reason ? rtrim( $openai_reason, '. ' ) . '. ' . $stability_reason : $openai_reason );
	return false;
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
			'headers'            => $headers,
			'body'               => wp_json_encode( $body ),
			'timeout'            => 60,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( 'Stability AI API Error: ' . $response->get_error_message() );
		abcc_set_last_image_error( 'Stability AI: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( $response_code !== 200 ) {
		$detail = abcc_extract_provider_error_detail( wp_remote_retrieve_body( $response ), $stability_key );
		abcc_debug_log( 'Stability AI API Error: Response code ' . $response_code . ( '' !== $detail ? ': ' . $detail : '' ) );
		abcc_set_last_image_error( sprintf( 'Stability AI HTTP %d: %s', $response_code, $detail ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['artifacts'] ) || ! is_array( $body['artifacts'] ) ) {
		abcc_debug_log( 'Stability AI: Unexpected response format; response body omitted from the log.' );
		abcc_set_last_image_error( 'Stability AI: unexpected response format.' );
		return false;
	}

	// Process only the first image.
	if ( ! empty( $body['artifacts'][0]['base64'] ) ) {
		return abcc_save_base64_image( $body['artifacts'][0]['base64'], 'image/png', 'stability' );
	}

	abcc_debug_log( 'Stability AI: No valid image data in response' );
	abcc_set_last_image_error( 'Stability AI: the response contained no image data.' );
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
 * @param string $model      Model to use (see abcc_get_image_model_options( 'gemini' )).
 * @param string $image_size Image size ('1K', '2K', or '4K').
 * @return string|false Image URL on success, false on failure.
 */
function abcc_gemini_generate_images( $api_key, $prompt, $model = 'gemini-2.5-flash-image', $image_size = '2K' ) {
	if ( empty( $api_key ) ) {
		abcc_debug_log( 'Gemini API key not provided for image generation' );
		return false;
	}

	$model = abcc_resolve_image_model( 'gemini', $model );

	// Validate image size.
	$valid_sizes = array( '1K', '2K', '4K' );
	if ( ! in_array( $image_size, $valid_sizes, true ) ) {
		$image_size = '2K';
	}

	$endpoint = sprintf(
		'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
		rawurlencode( $model )
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
			'headers'            => array(
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $api_key,
			),
			'body'               => wp_json_encode( $body ),
			'timeout'            => 90,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		abcc_debug_log( 'Gemini Image API Error: ' . $response->get_error_message() );
		abcc_set_last_image_error( 'Gemini: ' . $response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $response_code ) {
		$detail = abcc_extract_provider_error_detail( wp_remote_retrieve_body( $response ), $api_key );
		abcc_debug_log( 'Gemini Image API Error: Response code ' . $response_code . ( '' !== $detail ? ': ' . $detail : '' ) );
		abcc_set_last_image_error( sprintf( 'Gemini HTTP %d: %s', $response_code, $detail ) );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// Look for image data in response.
	if ( empty( $body['candidates'][0]['content']['parts'] ) ) {
		abcc_debug_log( 'Gemini Image: Unexpected response format; response body omitted from the log.' );
		abcc_set_last_image_error( 'Gemini: the response contained no image (it may have been blocked by safety filters).' );
		return false;
	}

	// Find the image part in the response.
	foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
		if ( isset( $part['inlineData']['data'] ) && isset( $part['inlineData']['mimeType'] ) ) {
			return abcc_save_base64_image( $part['inlineData']['data'], $part['inlineData']['mimeType'], 'gemini' );
		}
	}

	abcc_debug_log( 'Gemini Image: No valid image data in response' );
	abcc_set_last_image_error( 'Gemini: the response contained no image data.' );
	return false;
}
