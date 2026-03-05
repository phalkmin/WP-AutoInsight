<?php
/**
 * API key handling functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check and retrieve the appropriate API key based on the selected AI model.
 *
 * @return string The API key if found, empty string otherwise.
 */
function abcc_check_api_key() {
	$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );
	$api_key       = '';

	$model_options = abcc_get_ai_model_options();

	$provider = '';
	foreach ( $model_options as $provider_key => $group ) {
		if ( isset( $group['options'][ $prompt_select ] ) ) {
			$provider = $provider_key;
			break;
		}
	}

	switch ( $provider ) {
		case 'openai':
			$api_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
			break;

		case 'claude':
			$api_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
			break;

		case 'gemini':
			$api_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
			break;

		case 'perplexity':
			$api_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : get_option( 'perplexity_api_key', '' );
			break;
	}

	return $api_key;
}

/**
 * Determines which image generation service to use based on settings and availability.
 *
 * @param string $text_model The selected text generation model.
 * @return array Array containing 'service' and 'api_key'.
 */
function abcc_determine_image_service( $text_model ) {
	$openai_key    = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	$stability_key = defined( 'STABILITY_API' ) ? STABILITY_API : get_option( 'stability_api_key', '' );
	$gemini_key    = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );

	$preferred_image_service = get_option( 'preferred_image_service', 'auto' );

	// If user has explicitly selected a service, use it if available.
	if ( 'auto' !== $preferred_image_service ) {
		if ( 'stability' === $preferred_image_service && ! empty( $stability_key ) ) {
			return array(
				'service' => 'stability',
				'api_key' => $stability_key,
			);
		}
		if ( 'openai' === $preferred_image_service && ! empty( $openai_key ) ) {
			return array(
				'service' => 'openai',
				'api_key' => $openai_key,
			);
		}
		if ( 'gemini' === $preferred_image_service && ! empty( $gemini_key ) ) {
			return array(
				'service' => 'gemini',
				'api_key' => $gemini_key,
			);
		}
	}

	// Determine provider based on text model.
	$model_provider = '';
	if ( false !== strpos( $text_model, 'gpt' ) || 'openai' === $text_model || preg_match( '/^o[0-9]/', $text_model ) ) {
		$model_provider = 'openai';
	} elseif ( false !== strpos( $text_model, 'claude' ) ) {
		$model_provider = 'claude';
	} elseif ( false !== strpos( $text_model, 'gemini' ) ) {
		$model_provider = 'gemini';
	} elseif ( false !== strpos( $text_model, 'sonar' ) ) {
		$model_provider = 'perplexity';
	}

	// Auto-select based on text model provider.
	if ( 'openai' === $model_provider && ! empty( $openai_key ) ) {
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( 'gemini' === $model_provider && ! empty( $gemini_key ) ) {
		return array(
			'service' => 'gemini',
			'api_key' => $gemini_key,
		);
	} elseif ( ! empty( $stability_key ) ) {
		// Fallback to Stability for Claude or if no matching provider.
		return array(
			'service' => 'stability',
			'api_key' => $stability_key,
		);
	} elseif ( ! empty( $openai_key ) ) {
		// Fallback to OpenAI DALL-E.
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( ! empty( $gemini_key ) ) {
		// Fallback to Gemini Nano Banana.
		return array(
			'service' => 'gemini',
			'api_key' => $gemini_key,
		);
	}

	return array(
		'service' => null,
		'api_key' => null,
	);
}
