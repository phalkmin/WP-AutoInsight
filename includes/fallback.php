<?php
/**
 * Provider fallback chain.
 *
 * When the primary provider fails with a transient error (rate limit, 5xx,
 * network), retry once on a user-configured secondary provider+model. Auth and
 * configuration errors never retry — the user has to see them. Text-only:
 * image generation has its own path and return shape.
 *
 * @package WP-AutoInsight
 * @since 4.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Error codes that mean "try another provider".
 *
 * @since 4.5.0
 * @return string[]
 */
function abcc_get_retryable_generation_error_codes() {
	return array(
		'abcc_provider_rate_limited',
		'abcc_provider_server_error',
		'http_request_failed',
	);
}

/**
 * Whether a generation error is transient enough to retry elsewhere.
 *
 * @since 4.5.0
 * @param mixed $error WP_Error|null from the provider wrapper.
 * @return bool
 */
function abcc_is_retryable_generation_error( $error ) {
	if ( ! is_wp_error( $error ) ) {
		return false;
	}

	return in_array( $error->get_error_code(), abcc_get_retryable_generation_error_codes(), true );
}

/**
 * Sanitize the fallback chain setting.
 *
 * Drops entries whose provider is unregistered or not text-capable, or whose
 * model is not in that provider's text_models. A key is not required at save
 * time (the user may add it later); the runtime check handles that.
 *
 * @since 4.5.0
 * @param mixed $value Raw value.
 * @return array<int, array{provider: string, model: string}>
 */
function abcc_sanitize_fallback_chain( $value ) {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();

	foreach ( $value as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$provider = isset( $entry['provider'] ) ? sanitize_key( (string) $entry['provider'] ) : '';
		$model    = isset( $entry['model'] ) ? sanitize_text_field( (string) $entry['model'] ) : '';

		if ( '' === $provider || '' === $model ) {
			continue;
		}

		if ( ! abcc_provider_supports_text_generation( $provider ) ) {
			continue;
		}

		$config = abcc_get_provider( $provider );
		if ( empty( $config['text_models'][ $model ] ) ) {
			continue;
		}

		$clean[] = array(
			'provider' => $provider,
			'model'    => $model,
		);
	}

	return $clean;
}

/**
 * Read the configured fallback chain, already sanitized.
 *
 * @since 4.5.0
 * @return array<int, array{provider: string, model: string}>
 */
function abcc_get_fallback_chain() {
	return abcc_sanitize_fallback_chain( abcc_get_setting( 'abcc_fallback_chain', array() ) );
}

/**
 * Short, translatable reason for a fallback note.
 *
 * @since 4.5.0
 * @param mixed $error WP_Error or a reason slug ('no_key', 'prompt_too_long').
 * @return string
 */
function abcc_get_fallback_reason_label( $error ) {
	$code = is_wp_error( $error ) ? $error->get_error_code() : (string) $error;

	switch ( $code ) {
		case 'abcc_provider_rate_limited':
			return __( 'rate limit', 'automated-blog-content-creator' );
		case 'abcc_provider_server_error':
			return __( 'server error', 'automated-blog-content-creator' );
		case 'http_request_failed':
			return __( 'network error', 'automated-blog-content-creator' );
		case 'no_key':
			return __( 'no key', 'automated-blog-content-creator' );
		case 'prompt_too_long':
			return __( 'prompt too long', 'automated-blog-content-creator' );
		case 'abcc_provider_auth_error':
			return __( 'key rejected', 'automated-blog-content-creator' );
	}

	return __( 'error', 'automated-blog-content-creator' );
}

/**
 * Human label for a provider ID.
 *
 * @since 4.5.0
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_fallback_provider_label( $provider ) {
	$config = abcc_get_provider( $provider );

	return ! empty( $config['name'] ) ? $config['name'] : ucfirst( (string) $provider );
}

/**
 * Record a generation event: job log when running from the queue, debug log otherwise.
 *
 * @since 4.5.0
 * @param array  $context Safe hook context (job_id).
 * @param string $message Plain-text note.
 * @return void
 */
function abcc_note_generation_event( array $context, $message ) {
	$job_id = isset( $context['job_id'] ) ? (int) $context['job_id'] : 0;

	if ( $job_id > 0 && function_exists( 'abcc_append_job_log_note' ) ) {
		abcc_append_job_log_note( $job_id, $message );
	}

	abcc_debug_log( 'Fallback: ' . $message );
}

/**
 * Call the primary provider; on a retryable failure walk the configured chain.
 *
 * @since 4.5.0
 * @param string $provider Primary provider ID.
 * @param string $model    Primary model.
 * @param string $prompt   Prompt.
 * @param array  $opts     Wrapper opts (api_key, max_tokens).
 * @param array  $context  Safe hook context (job_id, source…) for log notes.
 * @return array Wrapper tuple + 'fallback_from' => array{provider,model}|null
 *               + 'served_provider', 'served_model', 'attempt' => int.
 */
function abcc_execute_fallback_chain( $provider, $model, $prompt, $opts, $context = array() ) {
	$result = abcc_call_provider_api( $provider, $model, $prompt, $opts );

	$result['fallback_from']   = null;
	$result['served_provider'] = $provider;
	$result['served_model']    = $model;
	$result['attempt']         = 1;

	if ( ! abcc_is_retryable_generation_error( $result['error'] ) ) {
		return $result;
	}

	$chain = abcc_get_fallback_chain();
	if ( empty( $chain ) ) {
		return $result;
	}

	$primary_label = abcc_get_fallback_provider_label( $provider );
	$primary_error = $result['error'];
	$attempt       = 1;

	foreach ( $chain as $entry ) {
		if ( $entry['provider'] === $provider && $entry['model'] === $model ) {
			continue;
		}

		$label = abcc_get_fallback_provider_label( $entry['provider'] );

		$key = abcc_get_provider_api_key( $entry['provider'] );
		if ( '' === (string) $key ) {
			abcc_note_generation_event(
				$context,
				sprintf(
					/* translators: 1: provider name, 2: short reason */
					__( '%1$s skipped (%2$s)', 'automated-blog-content-creator' ),
					$label,
					abcc_get_fallback_reason_label( 'no_key' )
				)
			);
			continue;
		}

		$requested = isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : 800;
		if ( abcc_calculate_available_tokens( $prompt, $requested, $entry['model'] ) <= 0 ) {
			abcc_note_generation_event(
				$context,
				sprintf(
					/* translators: 1: provider name, 2: short reason */
					__( '%1$s skipped (%2$s)', 'automated-blog-content-creator' ),
					$label,
					abcc_get_fallback_reason_label( 'prompt_too_long' )
				)
			);
			continue;
		}

		++$attempt;
		abcc_fallback_backoff( $attempt );

		$retry_opts            = $opts;
		$retry_opts['api_key'] = $key;

		$retry = abcc_call_provider_api( $entry['provider'], $entry['model'], $prompt, $retry_opts );

		if ( ! is_wp_error( $retry['error'] ) ) {
			abcc_note_generation_event(
				$context,
				sprintf(
					/* translators: 1: primary provider name, 2: short reason, 3: fallback provider name */
					__( '%1$s failed (%2$s) → completed via %3$s', 'automated-blog-content-creator' ),
					$primary_label,
					abcc_get_fallback_reason_label( $primary_error ),
					$label
				)
			);

			$retry['fallback_from']   = array(
				'provider' => $provider,
				'model'    => $model,
			);
			$retry['served_provider'] = $entry['provider'];
			$retry['served_model']    = $entry['model'];
			$retry['attempt']         = $attempt;

			return $retry;
		}

		abcc_note_generation_event(
			$context,
			sprintf(
				/* translators: 1: fallback provider name, 2: short reason */
				__( '%1$s also failed (%2$s)', 'automated-blog-content-creator' ),
				$label,
				abcc_get_fallback_reason_label( $retry['error'] )
			)
		);
	}

	// Every fallback failed or was skipped: the user should see the original cause.
	return $result;
}

/**
 * Pause before a fallback attempt so a rate-limited provider is not hammered.
 *
 * @since 4.5.0
 * @param int $attempt Attempt number (2 = first fallback).
 * @return void
 */
function abcc_fallback_backoff( $attempt ) {
	$schedule = array(
		2 => 100000,
		3 => 500000,
	);
	$us       = isset( $schedule[ $attempt ] ) ? $schedule[ $attempt ] : 2000000;

	/**
	 * Filter the fallback backoff in microseconds.
	 *
	 * @since 4.5.0
	 * @param int $us      Microseconds to sleep.
	 * @param int $attempt Attempt number.
	 */
	$us = (int) apply_filters( 'abcc_fallback_backoff_us', $us, $attempt );

	if ( $us > 0 ) {
		usleep( $us );
	}
}
