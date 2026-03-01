<?php
/**
 * Token handling utilities and functions.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Get the maximum context window for a given model.
 *
 * @param string $model The model identifier.
 * @return int The maximum context window size.
 */
function abcc_get_model_context_window( $model ) {
	$model_limits = array(
		// OpenAI GPT-4.1 models (1M context).
		'gpt-4.1'                 => 1000000,
		'gpt-4.1-2025-04-14'      => 1000000,
		'gpt-4.1-mini'            => 1000000,
		'gpt-4.1-mini-2025-04-14' => 1000000,
		'gpt-4.1-nano'            => 1000000,
		'gpt-4.1-nano-2025-04-14' => 1000000,
		// OpenAI o-series reasoning models.
		'o4-mini'                 => 200000,
		'o4-mini-2025-04-16'      => 200000,
		// Claude 4.5 models (current).
		'claude-haiku-4-5'           => 200000,
		'claude-haiku-4-5-20251001'  => 200000,
		'claude-sonnet-4-5'          => 200000,
		'claude-sonnet-4-5-20250929' => 200000,
		'claude-opus-4-5'            => 200000,
		'claude-opus-4-5-20251101'   => 200000,
		// Gemini 2.5 models (current).
		'gemini-2.5-flash-lite' => 1048576, // 1M tokens.
		'gemini-2.5-flash'      => 1048576, // 1M tokens.
		'gemini-2.5-pro'        => 1048576, // 1M tokens.
	);

	return $model_limits[ $model ] ?? 4096;
}


/**
 * Estimates the number of tokens in a string.
 * This is a rough approximation for English text.
 *
 * @param string $text The text to estimate tokens for.
 * @return int Estimated number of tokens.
 */
function abcc_estimate_tokens( $text ) {
	// Remove extra whitespace
	$text = preg_replace( '/\s+/', ' ', trim( $text ) );

	// Approximate token count based on characters
	// Using 4 characters per token as a general rule
	return (int) ceil( mb_strlen( $text ) / 4 );
}

/**
 * Calculates the available tokens for content generation.
 *
 * @param string $prompt The prompt text.
 * @param int    $requested_tokens The number of tokens requested.
 * @param string $model The model being used.
 * @return int The number of tokens available for content.
 */
function abcc_calculate_available_tokens( $prompt, $requested_tokens, $model ) {
	// Get model's maximum context window
	$model_max = abcc_get_model_context_window( $model );

	// Estimate prompt tokens
	$prompt_tokens = abcc_estimate_tokens( $prompt );

	// Calculate maximum possible output tokens within context window
	$max_possible_output = $model_max - $prompt_tokens;

	// Use the smaller of: user's request OR what the model can actually handle
	$available_tokens = min( $requested_tokens, $max_possible_output );

	// Ensure minimum response size (but don't exceed what user requested)
	return (int) max( 100, $available_tokens );
}
