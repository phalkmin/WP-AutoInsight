<?php
/**
 * Token handling utilities and functions
 */

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
 * Get the maximum context window for a given model.
 *
 * @param string $model The model identifier.
 * @return int The maximum context window size.
 */
function abcc_get_model_context_window( $model ) {
	$model_limits = array(
		'gpt-3.5-turbo'       => 4096,
		'gpt-4-turbo-preview' => 128000,
		'gpt-4'               => 8192,
		'claude-3-haiku'      => 200000,
		'claude-3-sonnet'     => 200000,
		'claude-3-opus'       => 200000,
		'gemini-pro'          => 32768,
	);

	return $model_limits[ $model ] ?? 4096;
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

	// Calculate available tokens
	$max_tokens       = min( $requested_tokens, $model_max );
	$available_tokens = $max_tokens - $prompt_tokens;

	// Ensure minimum response size
	return max( 100, $available_tokens );
}
