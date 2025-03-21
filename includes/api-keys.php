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
	$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );
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
	}

	return $api_key;
}

/**
 * Determines which image generation service to use based on settings and availability.
 *
 * @param string $text_model The selected text generation model
 * @return array Array containing 'service' and 'api_key'
 */
function abcc_determine_image_service( $text_model ) {
	$openai_key    = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	$stability_key = defined( 'STABILITY_API' ) ? STABILITY_API : get_option( 'stability_api_key', '' );

	$preferred_image_service = get_option( 'preferred_image_service', 'auto' );

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
	}

	$model_provider = '';
	if ( false !== strpos( $text_model, 'gpt' ) || 'openai' === $text_model ) {
		$model_provider = 'openai';
	} elseif ( false !== strpos( $text_model, 'claude' ) ) {
		$model_provider = 'claude';
	} elseif ( false !== strpos( $text_model, 'gemini' ) ) {
		$model_provider = 'gemini';
	}

	if ( 'openai' === $model_provider && ! empty( $openai_key ) ) {
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( ! empty( $stability_key ) ) {
		return array(
			'service' => 'stability',
			'api_key' => $stability_key,
		);
	}

	return array(
		'service' => null,
		'api_key' => null,
	);
}

/**
 * The function `abcc_get_available_models` retrieves available models either from cache or by making
 * an API call and caches the result for 1 hour.
 *
 * @return array function `abcc_get_available_models` is returning an array of available models from the
 * cache if available, otherwise it checks for an API key, retrieves available models using the API
 * client, caches the models for 1 hour, and returns the models. If there is an error during the
 * process, it returns an empty array.
 */
function abcc_get_available_models() {
	$cached_models = get_transient( 'abcc_available_models' );
	if ( false !== $cached_models ) {
		return $cached_models;
	}

	$api_key = abcc_check_api_key();
	if ( empty( $api_key ) ) {
		return array();
	}

	$client = new ABCC_OpenAI_Client( $api_key );
	$models = $client->get_available_models();

	if ( ! is_wp_error( $models ) ) {
		set_transient( 'abcc_available_models', $models, HOUR_IN_SECONDS );
		return $models;
	}

	return array();
}
