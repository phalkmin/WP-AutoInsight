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
		'gpt-3.5-turbo'              => 16385,  // Updated context window
		'gpt-4-turbo'                => 128000,
		'gpt-4-turbo-2024-04-09'     => 128000,
		'gpt-4'                      => 8192,
		'gpt-4.5-preview'            => 128000,
		'gpt-4.5-preview-2025-02-27' => 128000,
		'gpt-4o'                     => 128000,
		'gpt-4o-2024-05-13'          => 128000,
		'gpt-4o-2024-08-06'          => 128000,
		'gpt-4o-2024-11-20'          => 128000,
		'gpt-4o-mini'                => 128000,
		'gpt-4o-mini-2024-07-18'     => 128000,
		// Original Claude models (for backward compatibility)
		'claude-3-haiku'             => 200000,
		'claude-3-sonnet'            => 200000,
		'claude-3-opus'              => 200000,
		// New Claude models with specific versions
		'claude-3-haiku-20240307'    => 200000,
		'claude-3-sonnet-20240229'   => 200000,
		'claude-3-opus-20240229'     => 200000,
		'claude-3.5-haiku-20241022'  => 200000,
		'claude-3.5-sonnet-20241022' => 200000,
		'claude-3.7-sonnet-20250219' => 200000,
		// Gemini models
		'gemini-pro'                 => 32768,  // Legacy compatibility
		'gemini-1.5-flash'           => 1048576, // 1M tokens
		'gemini-1.5-flash-8b'        => 1048576, // 1M tokens
		'gemini-1.5-pro'             => 2097152, // 2M tokens
		'gemini-2.0-flash'           => 1048576, // 1M tokens
		'gemini-2.0-flash-lite'      => 1048576, // 1M tokens
		'gemini-2.0-pro-exp-02-05'   => 2048576, // 2M tokens
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
