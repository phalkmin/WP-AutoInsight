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
 * The registry's context_window wins; the map below covers IDs saved before
 * the registry carried one. Unknown IDs get a conservative 4096.
 *
 * @param string $model    The model identifier or 'provider::model' selection.
 * @param string $provider Optional provider ID when the caller knows it.
 * @return int The maximum context window size.
 */
function abcc_get_model_context_window( $model, $provider = '' ) {
	$definition = function_exists( 'abcc_get_model_definition' ) ? abcc_get_model_definition( $model, $provider ) : null;
	if ( ! empty( $definition['context_window'] ) ) {
		return (int) $definition['context_window'];
	}

	// A gateway serves models the registry does not list; assume a modest window for those.
	$parsed_provider = '' !== (string) $provider ? (string) $provider : ( function_exists( 'abcc_get_provider_for_model' ) ? abcc_get_provider_for_model( $model ) : '' );
	$provider_config = function_exists( 'abcc_get_provider' ) ? abcc_get_provider( $parsed_provider ) : null;
	if ( ! empty( $provider_config['default_context_window'] ) ) {
		return (int) $provider_config['default_context_window'];
	}

	$model = function_exists( 'abcc_get_model_id_from_selection' ) ? abcc_get_model_id_from_selection( $model ) : (string) $model;

	$model_limits = array(
		// OpenAI GPT-5.x models (~1M context, 128K max output).
		'gpt-5.4-mini'               => 400000,
		'gpt-5.4'                    => 1000000,
		'gpt-5.5'                    => 1000000,
		// OpenAI GPT-4.1 models (1M context).
		'gpt-4.1'                    => 1000000,
		'gpt-4.1-2025-04-14'         => 1000000,
		'gpt-4.1-mini'               => 1000000,
		'gpt-4.1-mini-2025-04-14'    => 1000000,
		'gpt-4.1-nano'               => 1000000,
		'gpt-4.1-nano-2025-04-14'    => 1000000,
		// OpenAI o-series reasoning models.
		'o4-mini'                    => 200000,
		'o4-mini-2025-04-16'         => 200000,
		// Claude 4.5 models (legacy — kept for stored-model fallback).
		'claude-haiku-4-5'           => 200000,
		'claude-haiku-4-5-20251001'  => 200000,
		'claude-sonnet-4-5'          => 200000,
		'claude-sonnet-4-5-20250929' => 200000,
		'claude-opus-4-5'            => 200000,
		'claude-opus-4-5-20251101'   => 200000,
		// Claude 4.6+ models (current).
		'claude-sonnet-4-6'          => 1000000,
		'claude-opus-4-7'            => 1000000,
		'claude-opus-4-8'            => 1000000,
		// Gemini 2.5/3.5 models (current).
		'gemini-2.5-flash-lite'      => 1048576, // 1M tokens.
		'gemini-2.5-flash'           => 1048576, // 1M tokens.
		'gemini-2.5-pro'             => 1048576, // 1M tokens.
		'gemini-3.5-flash'           => 1048576, // 1M tokens.
		// Perplexity Sonar models.
		'sonar'                      => 128000,
		'sonar-pro'                  => 200000,
		'sonar-reasoning-pro'        => 128000,
		'sonar-deep-research'        => 128000,
	);

	return $model_limits[ $model ] ?? 4096;
}

/**
 * Largest completion a model can return in one call, or 0 when unknown.
 *
 * @since 4.6.0
 * @param string $model    Model identifier or selection.
 * @param string $provider Optional provider ID.
 * @return int
 */
function abcc_get_model_max_output( $model, $provider = '' ) {
	$definition = function_exists( 'abcc_get_model_definition' ) ? abcc_get_model_definition( $model, $provider ) : null;

	return ! empty( $definition['max_output'] ) ? (int) $definition['max_output'] : 0;
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
 * @param int    $requested_tokens The number of tokens requested (floors already applied).
 * @param string $model The model being used.
 * @param string $provider Optional provider ID.
 * @return int The number of tokens available for content.
 */
function abcc_calculate_available_tokens( $prompt, $requested_tokens, $model, $provider = '' ) {
	// Get model's maximum context window
	$model_max = abcc_get_model_context_window( $model, $provider );

	// A model's own output ceiling caps the request regardless of context left.
	$max_output = abcc_get_model_max_output( $model, $provider );
	if ( $max_output > 0 ) {
		$requested_tokens = min( (int) $requested_tokens, $max_output );
	}

	// Estimate prompt tokens
	$prompt_tokens = abcc_estimate_tokens( $prompt );

	// Calculate maximum possible output tokens within context window
	$max_possible_output = $model_max - $prompt_tokens;

	// Prompt alone exceeds (or nearly fills) the model context. Return 0 so
	// callers receive a clean WP_Error instead of a doomed 100-token request.
	if ( $max_possible_output < 100 ) {
		return 0;
	}

	// Use the smaller of: user's request OR what the model can actually handle
	$available_tokens = min( $requested_tokens, $max_possible_output );

	// Ensure minimum response size (but don't exceed what user requested)
	return (int) max( 100, $available_tokens );
}

/**
 * Words a content-length setting asks for.
 *
 * The stored value is token-like for historical reasons; 0.75 words per unit
 * is the ratio the length slider has always shown.
 *
 * @since 4.6.0
 * @param int $char_limit Content length setting.
 * @return int
 */
function abcc_get_word_target( $char_limit ) {
	return (int) max( 1, round( (int) $char_limit * 0.75 ) );
}

/**
 * Locale the content is written in ('pt_BR'), from the setting or the site.
 *
 * @since 4.6.0
 * @return string
 */
function abcc_get_content_language_code() {
	$setting = (string) abcc_get_setting( 'abcc_content_language', 'site' );

	return ( '' === $setting || 'site' === $setting ) ? (string) get_locale() : $setting;
}

/**
 * Approximate output tokens per visible word for a language.
 *
 * English tokenizes densely; inflected and non-Latin languages need more
 * tokens for the same text. These are planning numbers, not measurements.
 *
 * @since 4.6.0
 * @param string $locale Locale or language code; '' means the content language.
 * @return float
 */
function abcc_get_tokens_per_word( $locale = '' ) {
	$locale = '' !== (string) $locale ? (string) $locale : abcc_get_content_language_code();
	$parts  = preg_split( '/[_-]/', strtolower( $locale ) );
	$code   = isset( $parts[0] ) ? (string) $parts[0] : 'en';

	$table = array(
		'en' => 1.35,
		'pt' => 1.6,
		'es' => 1.6,
		'fr' => 1.6,
		'it' => 1.6,
		'nl' => 1.6,
		'de' => 1.8,
		'pl' => 2.0,
		'tr' => 2.0,
		'ru' => 2.2,
		'uk' => 2.2,
		'ar' => 2.2,
		'he' => 2.2,
		'hi' => 2.5,
		'ja' => 2.5,
		'zh' => 2.5,
		'ko' => 2.5,
	);

	$ratio = isset( $table[ $code ] ) ? $table[ $code ] : 1.8;

	/**
	 * Filter the output tokens assumed per visible word.
	 *
	 * @since 4.6.0
	 * @param float  $ratio  Tokens per word.
	 * @param string $code   Two-letter language code.
	 * @param string $locale Full locale.
	 */
	$ratio = (float) apply_filters( 'abcc_tokens_per_word', $ratio, $code, $locale );

	return $ratio > 0 ? $ratio : 1.35;
}

/**
 * Visible-text token request for a word target: language ratio plus 30% headroom.
 *
 * Reasoning reserves, floors and clamps are added by abcc_resolve_output_budget().
 *
 * @since 4.6.0
 * @param int    $words  Word target.
 * @param string $locale Optional locale; '' means the content language.
 * @return int
 */
function abcc_words_to_token_request( $words, $locale = '' ) {
	return (int) ceil( max( 1, (int) $words ) * abcc_get_tokens_per_word( $locale ) * 1.3 );
}

/**
 * Token request the body call sends for a content-length setting.
 *
 * @since 4.6.0
 * @param int $char_limit Content length setting.
 * @return int
 */
function abcc_get_body_token_request( $char_limit ) {
	return abcc_words_to_token_request( abcc_get_word_target( $char_limit ) );
}

/**
 * Output ceiling actually asked of a model for a requested visible budget.
 *
 * The one place floors apply: a reasoning model gets a reserve for hidden
 * tokens (half its floor), every model gets its min_output_tokens, Gemini's
 * explicit thinking allowance is included, and max_output caps the total.
 *
 * @since 4.6.0
 * @param int    $requested Visible tokens wanted.
 * @param string $provider  Provider ID.
 * @param string $model     Model ID or selection.
 * @return int
 */
function abcc_resolve_output_budget( $requested, $provider, $model ) {
	$flags  = abcc_get_model_flags( $provider, $model );
	$budget = max( 1, (int) $requested );
	$floor  = (int) $flags['min_output_tokens'];

	if ( ! empty( $flags['reasoning'] ) && null === $flags['thinking_budget'] ) {
		$budget += (int) floor( $floor / 2 );
	}

	$budget = max( $budget, $floor );

	if ( null !== $flags['thinking_budget'] && (int) $flags['thinking_budget'] > 0 ) {
		$budget += (int) $flags['thinking_budget'];
	}

	if ( (int) $flags['max_output'] > 0 ) {
		$budget = min( $budget, (int) $flags['max_output'] );
	}

	return (int) $budget;
}

/**
 * Output-token ceiling a post of N words needs on a model.
 *
 * Shared by the cost estimate and (through abcc_resolve_output_budget()) the
 * transport, so what is estimated is what is requested.
 *
 * @since 4.6.0
 * @param int    $words     Visible word target.
 * @param string $selection Model selection ('provider::model' or a bare ID).
 * @param array  $flags     Optional: 'locale' (string), 'prompt_tokens' (int, for the context clamp).
 * @return int
 */
function abcc_estimate_output_tokens( $words, $selection, $flags = array() ) {
	$parsed   = abcc_parse_model_selection( $selection );
	$provider = $parsed['provider'];
	$model    = '' !== $provider ? abcc_resolve_retired_model( $provider, $parsed['model'] ) : $parsed['model'];
	$locale   = isset( $flags['locale'] ) ? (string) $flags['locale'] : '';

	$budget = abcc_resolve_output_budget( abcc_words_to_token_request( $words, $locale ), $provider, $model );

	$prompt_tokens = isset( $flags['prompt_tokens'] ) ? max( 0, (int) $flags['prompt_tokens'] ) : 0;
	$room          = abcc_get_model_context_window( $model, $provider ) - $prompt_tokens;

	return (int) max( 100, min( $budget, $room ) );
}
