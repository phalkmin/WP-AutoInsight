<?php
/**
 * User-facing generation error messages.
 *
 * The wrapper produces machine codes; this maps them to sentences a
 * non-technical site owner can act on. Raw upstream strings never reach the
 * user — they go to the debug log.
 *
 * @package WP-AutoInsight
 * @since 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Turn a generation WP_Error into an actionable user-facing message.
 *
 * @since 4.4.0
 * @param mixed $error   WP_Error from the provider wrapper. Other types yield the generic message.
 * @param array $context Optional 'provider' and 'model'.
 * @return string Translated, escaped-safe plain text (no markup).
 */
function abcc_format_generation_error( $error, $context = array() ) {
	$provider_id    = isset( $context['provider'] ) ? (string) $context['provider'] : '';
	$provider_label = '';

	if ( '' !== $provider_id && function_exists( 'abcc_get_provider' ) ) {
		$provider       = abcc_get_provider( $provider_id );
		$provider_label = ! empty( $provider['name'] ) ? $provider['name'] : ucfirst( $provider_id );
	} elseif ( '' !== $provider_id ) {
		$provider_label = ucfirst( $provider_id );
	}

	if ( '' === $provider_label ) {
		$provider_label = __( 'The AI provider', 'automated-blog-content-creator' );
	}

	$code = is_wp_error( $error ) ? $error->get_error_code() : '';

	switch ( $code ) {
		case 'abcc_no_api_key':
			return sprintf(
				/* translators: %s: provider name */
				__( 'No API key configured for %s. Add one in Settings → Connections.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_auth_error':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s rejected the API key. Check it under Settings → Connections → API Keys.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_rate_limited':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s is rate limiting requests. Wait a minute and try again, or switch to another provider.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_server_error':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s is having server trouble. This is on their side — try again shortly or use a different provider.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'context_overflow':
			return __( 'The prompt is longer than this model can accept. Use fewer keywords or lower the character limit.', 'automated-blog-content-creator' );

		case 'abcc_provider_text_unsupported':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s does not generate text. Choose a different provider for this task.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_unknown_provider':
			return __( 'That provider is not available. Pick a model from Settings → Connections.', 'automated-blog-content-creator' );

		case 'http_request_failed':
			return sprintf(
				/* translators: %s: provider name */
				__( 'Could not reach %s. Check the connection from your server, or try a different provider.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_empty_completion':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s returned an empty response. Raise the character limit — reasoning models can spend the whole budget before writing anything.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_parse_error':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s returned something unexpected. Try again, or switch providers if it keeps happening.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_seo_unparseable':
			return sprintf(
				/* translators: %s: provider name */
				__( '%s returned SEO data that could not be read. Try again or switch providers.', 'automated-blog-content-creator' ),
				$provider_label
			);

		case 'abcc_provider_http_error':
			// Non-auth, non-rate-limit, non-5xx HTTP failure — in practice a
			// 400/404 for an unsupported parameter or a retired model ID.
			$status = 0;
			if ( is_wp_error( $error ) ) {
				$data   = $error->get_error_data();
				$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
			}
			return sprintf(
				/* translators: 1: provider name, 2: HTTP status code */
				__( '%1$s rejected the request (HTTP %2$d). The selected model may no longer be available — pick another under Settings → Connections.', 'automated-blog-content-creator' ),
				$provider_label,
				$status
			);
	}

	// Unknown code: the raw message goes to the log, not the screen.
	if ( is_wp_error( $error ) ) {
		abcc_debug_log( 'Unmapped generation error [' . $code . ']: ' . $error->get_error_message() );
	}

	return __( 'Content generation failed. Check Content → Generation Log for details.', 'automated-blog-content-creator' );
}
