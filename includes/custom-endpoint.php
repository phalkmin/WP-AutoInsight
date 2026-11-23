<?php
/**
 * One custom OpenAI-compatible endpoint (Ollama, Groq, DeepSeek, Mistral, Together…).
 *
 * Policy: https for public hosts; loopback and private hosts only behind the
 * "Local endpoint" switch; no credentials in the URL; redirects are never
 * followed, so the key cannot travel to a host the admin did not enter.
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the current user may change the custom endpoint.
 *
 * On multisite a site admin could otherwise point the network's server at an
 * internal address.
 *
 * @since 4.6.0
 * @return bool
 */
function abcc_current_user_can_manage_custom_endpoint() {
	return is_multisite() ? current_user_can( 'manage_network_options' ) : current_user_can( 'manage_options' );
}

/**
 * Sanitize the `abcc_custom_endpoint` option. Does not validate the URL policy.
 *
 * @since 4.6.0
 * @param mixed $value Raw value.
 * @return array{base_url: string, model: string, context_window: int, max_output: int, allow_local: bool, is_local: bool, constant_key_origin: string}
 *               'is_local' records that the host resolved to a private address when it was saved.
 */
function abcc_sanitize_custom_endpoint( $value ) {
	$value = is_array( $value ) ? $value : array();
	$model = isset( $value['model'] ) ? trim( (string) $value['model'] ) : '';

	return array(
		'base_url'            => isset( $value['base_url'] ) ? untrailingslashit( esc_url_raw( trim( (string) $value['base_url'] ), array( 'http', 'https' ) ) ) : '',
		'model'               => abcc_is_valid_custom_model_id( $model ) ? $model : '',
		'context_window'      => isset( $value['context_window'] ) ? min( 10000000, max( 1024, absint( $value['context_window'] ) ) ) : 8192,
		'max_output'          => isset( $value['max_output'] ) ? min( 2000000, max( 256, absint( $value['max_output'] ) ) ) : 4096,
		'allow_local'         => ! empty( $value['allow_local'] ) && 'false' !== $value['allow_local'],
		'is_local'            => ! empty( $value['is_local'] ),
		'constant_key_origin' => isset( $value['constant_key_origin'] ) ? sanitize_text_field( (string) $value['constant_key_origin'] ) : '',
	);
}

/**
 * The stored custom endpoint, sanitized.
 *
 * @since 4.6.0
 * @return array{base_url: string, model: string, context_window: int, max_output: int, allow_local: bool, is_local: bool, constant_key_origin: string}
 */
function abcc_get_custom_endpoint_settings() {
	return abcc_sanitize_custom_endpoint( abcc_get_setting( 'abcc_custom_endpoint', array() ) );
}

/**
 * Whether an IP address is loopback, private, link-local or otherwise not public.
 *
 * @since 4.6.0
 * @param string $ip IPv4 or IPv6 address.
 * @return bool
 */
function abcc_is_private_ip( $ip ) {
	if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Classify a custom endpoint host, optionally confirming its DNS addresses.
 *
 * A local-looking hostname with a public, mixed, invalid or missing DNS answer
 * is unverified, not public: callers must not send it a local endpoint key.
 *
 * @since 4.6.0
 * @param string $host    Host from the URL (IPv6 may be bracketed).
 * @param bool   $resolve Look the name up in DNS.
 * @return string 'local', 'public', 'unverified' or 'invalid'.
 */
function abcc_get_custom_endpoint_host_status( $host, $resolve = false ) {
	$host = strtolower( trim( (string) $host, " \t[]." ) );

	if ( '' === $host ) {
		return 'invalid';
	}

	if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return abcc_is_private_ip( $host ) ? 'local' : 'public';
	}

	// Alternate integer, octal and hexadecimal IP forms bypass literal-IP checks.
	if ( preg_match( '/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*$/i', $host ) || false !== strpos( $host, ':' ) ) {
		return 'invalid';
	}

	$local_name = 'localhost' === $host || false === strpos( $host, '.' );
	foreach ( array( '.localhost', '.local', '.internal', '.lan', '.home', '.home.arpa', '.test' ) as $suffix ) {
		if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
			$local_name = true;
			break;
		}
	}

	if ( ! $resolve ) {
		return $local_name ? 'local' : 'public';
	}

	/**
	 * Filter the addresses a custom endpoint host resolves to.
	 *
	 * Return an array to skip the DNS lookup.
	 *
	 * @since 4.6.0
	 * @param array|null $ips  Addresses, or null to look them up.
	 * @param string     $host Host name.
	 */
	$ips = apply_filters( 'abcc_custom_endpoint_resolved_ips', null, $host );
	if ( null === $ips && function_exists( 'dns_get_record' ) ) {
		$records = dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( is_array( $records ) ) {
			$ips = array();
			foreach ( $records as $record ) {
				if ( ! empty( $record['ipv6'] ) ) {
					$ips[] = (string) $record['ipv6'];
				} elseif ( ! empty( $record['ip'] ) ) {
					$ips[] = (string) $record['ip'];
				}
			}
			if ( empty( $ips ) ) {
				$ips = null;
			}
		}
	}

	if ( null === $ips && function_exists( 'gethostbynamel' ) ) {
		$ips = gethostbynamel( $host );
	}

	if ( empty( $ips ) || ! is_array( $ips ) ) {
		return $local_name ? 'unverified' : 'public';
	}

	foreach ( $ips as $ip ) {
		$ip = (string) $ip;
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) || ! abcc_is_private_ip( $ip ) ) {
			return $local_name ? 'unverified' : 'public';
		}
	}

	return 'local';
}

/**
 * Whether a host names this server or its private network.
 *
 * @since 4.6.0
 * @param string $host    Host from the URL (IPv6 may be bracketed).
 * @param bool   $resolve Look the name up in DNS.
 * @return bool
 */
function abcc_is_local_endpoint_host( $host, $resolve = false ) {
	return 'local' === abcc_get_custom_endpoint_host_status( $host, $resolve );
}

/**
 * Check a custom endpoint URL against the endpoint policy.
 *
 * @since 4.6.0
 * @param string $url         Base URL, e.g. https://api.example.com/v1.
 * @param bool   $allow_local Whether the "Local endpoint" switch is on.
 * @param bool   $resolve     Look the host up in DNS (save time and when a
 *                            previously local hostname is checked again).
 * @param bool   $known_local The host was confirmed private for this validation.
 * @param string $resolved_status Set to the host classification used for this validation.
 * @return true|WP_Error
 */
function abcc_validate_custom_endpoint( $url, $allow_local, $resolve = false, $known_local = false, &$resolved_status = null ) {
	$url   = trim( (string) $url );
	$parts = '' !== $url ? wp_parse_url( $url ) : false;

	if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
		return new WP_Error( 'abcc_endpoint_invalid', __( 'Enter the full base URL of the endpoint, starting with https://.', 'automated-blog-content-creator' ) );
	}

	$scheme = strtolower( (string) $parts['scheme'] );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return new WP_Error( 'abcc_endpoint_scheme', __( 'The endpoint must start with https:// (or http:// for a local endpoint).', 'automated-blog-content-creator' ) );
	}

	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return new WP_Error( 'abcc_endpoint_userinfo', __( 'Remove the user name and password from the URL. Put the key in the API key field instead.', 'automated-blog-content-creator' ) );
	}

	if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
		return new WP_Error( 'abcc_endpoint_query', __( 'Enter the base URL only, without "?" or "#" parts.', 'automated-blog-content-creator' ) );
	}

	$host_status     = abcc_get_custom_endpoint_host_status( (string) $parts['host'], $resolve );
	$resolved_status = $host_status;
	if ( 'invalid' === $host_status ) {
		return new WP_Error( 'abcc_endpoint_invalid', __( 'Enter a canonical IP address or a valid hostname for the endpoint.', 'automated-blog-content-creator' ) );
	}
	if ( 'unverified' === $host_status ) {
		return new WP_Error( 'abcc_endpoint_dns', __( 'The local hostname must resolve only to valid private IP addresses. Check DNS and try again.', 'automated-blog-content-creator' ) );
	}

	$is_local = $known_local || 'local' === $host_status;

	if ( $is_local && ! $allow_local ) {
		return new WP_Error( 'abcc_endpoint_local', __( 'That address is on the server\'s own machine or private network. Turn on "Local endpoint" to allow it.', 'automated-blog-content-creator' ) );
	}

	if ( ! $is_local && 'https' !== $scheme ) {
		return new WP_Error( 'abcc_endpoint_insecure', __( 'A public endpoint must use https://, or your key and prompts would travel unencrypted.', 'automated-blog-content-creator' ) );
	}

	return true;
}

/**
 * Chat-completions URL for a base URL.
 *
 * @since 4.6.0
 * @param string $base_url Base URL (with or without /chat/completions).
 * @return string
 */
function abcc_get_custom_endpoint_chat_url( $base_url ) {
	$base_url = untrailingslashit( trim( (string) $base_url ) );

	if ( '' === $base_url ) {
		return '';
	}

	return '/chat/completions' === substr( $base_url, -17 ) ? $base_url : $base_url . '/chat/completions';
}

/**
 * Registry entry for the custom endpoint.
 *
 * Called while the registry is being built, so it reads options directly and
 * must not call anything that reads the registry. An endpoint that fails the
 * policy generates nothing: it has no endpoint and no text capability.
 *
 * @since 4.6.0
 * @return array
 */
function abcc_get_custom_endpoint_provider_entry() {
	$settings    = abcc_get_custom_endpoint_settings();
	$host        = (string) wp_parse_url( $settings['base_url'], PHP_URL_HOST );
	$was_local   = $settings['is_local'] && $settings['allow_local'];
	$host_status = abcc_get_custom_endpoint_host_status( $host );

	// Local-looking names and names previously approved as local need fresh DNS.
	if ( $was_local || 'local' === $host_status ) {
		$host_status = abcc_get_custom_endpoint_host_status( $host, true );
	}

	$is_local   = 'local' === $host_status;
	$host_valid = in_array( $host_status, array( 'local', 'public' ), true );
	$usable     = $host_valid && ! ( $was_local && ! $is_local ) && '' !== $settings['base_url'] && '' !== $settings['model']
		&& true === abcc_validate_custom_endpoint( $settings['base_url'], $settings['allow_local'], false, $is_local );

	$has_key  = ( defined( 'CUSTOM_API' ) && '' !== (string) constant( 'CUSTOM_API' ) ) || '' !== (string) get_option( 'custom_api_key', '' );
	$is_local = $usable && $is_local;

	$models = array();
	if ( $usable ) {
		$models[ $settings['model'] ] = array(
			'name'           => $settings['model'],
			'context_window' => $settings['context_window'],
			'max_output'     => $settings['max_output'],
		);
	}

	return array(
		'id'                       => 'custom',
		'name'                     => 'Custom endpoint',
		'group_label'              => 'Custom endpoint',
		'help_url'                 => '',
		'constant'                 => 'CUSTOM_API',
		'option_name'              => 'custom_api_key',
		'wp_connector_id'          => '',
		'text_generation_callback' => '',
		'connection_test_callback' => '',
		'connection_test_model'    => $settings['model'],
		'transport'                => 'openai_compatible',
		'endpoint'                 => $usable ? abcc_get_custom_endpoint_chat_url( $settings['base_url'] ) : '',
		// Keyless only for a local endpoint; a public host always needs its key.
		'auth'                     => ( $is_local && $settings['allow_local'] && ! $has_key ) ? 'none' : 'bearer',
		'extra_headers'            => array(),
		'redirection'              => 0,
		'reject_unsafe_urls'       => ! $is_local,
		'timeout'                  => 120,
		'custom_endpoint'          => true,
		'single_model'             => true,
		'capabilities'             => array(
			'text_generation'     => $usable,
			'image_generation'    => false,
			'citations'           => false,
			'audio_transcription' => false,
		),
		'text_models'              => $models,
	);
}

/**
 * Normalized origin a custom endpoint credential is bound to.
 *
 * @since 4.6.0
 * @param string $url Endpoint base URL.
 * @return string scheme://host:port, or '' for an invalid URL.
 */
function abcc_get_custom_endpoint_origin( $url ) {
	$parts = wp_parse_url( (string) $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}

	$scheme = strtolower( (string) $parts['scheme'] );
	$host   = strtolower( rtrim( (string) $parts['host'], '.' ) );
	$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );

	return $scheme . '://' . $host . ':' . $port;
}

/**
 * Save the custom endpoint from the Connections form.
 *
 * An empty base URL clears the endpoint. A URL that fails the policy is not
 * saved and the previous endpoint stays in place.
 *
 * @since 4.6.0
 * @param array $raw Unslashed form values: base_url, model, context_window, max_output, allow_local.
 * @return true|WP_Error
 */
function abcc_save_custom_endpoint( $raw ) {
	if ( ! abcc_current_user_can_manage_custom_endpoint() ) {
		return new WP_Error( 'abcc_endpoint_forbidden', __( 'Only a network administrator can change the custom endpoint.', 'automated-blog-content-creator' ) );
	}

	$raw      = is_array( $raw ) ? $raw : array();
	$typed    = isset( $raw['base_url'] ) ? trim( (string) $raw['base_url'] ) : '';
	$settings = abcc_sanitize_custom_endpoint( $raw );

	// Never taken from the request: it is decided by the lookup below.
	$settings['is_local'] = false;

	$previous     = abcc_get_custom_endpoint_settings();
	$old_origin   = abcc_get_custom_endpoint_origin( $previous['base_url'] );
	$bound_origin = '' !== $previous['constant_key_origin'] ? $previous['constant_key_origin'] : $old_origin;

	if ( '' === $typed ) {
		$settings['base_url']            = '';
		$settings['constant_key_origin'] = $bound_origin;
		abcc_update_setting( 'abcc_custom_endpoint', $settings );
		if ( '' !== $old_origin ) {
			abcc_forget_custom_endpoint_key();
		}
		abcc_flush_provider_registry_cache();
		return true;
	}

	// Validate what was typed: sanitizing first could hide a user:pass@ part.
	$host_status = '';
	$valid       = abcc_validate_custom_endpoint( $typed, $settings['allow_local'], true, false, $host_status );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	if ( '' === $settings['base_url'] ) {
		return new WP_Error( 'abcc_endpoint_invalid', __( 'Enter the full base URL of the endpoint, starting with https://.', 'automated-blog-content-creator' ) );
	}

	if ( '' === $settings['model'] ) {
		return new WP_Error( 'abcc_endpoint_model', __( 'Enter the model ID the endpoint serves, exactly as it names it.', 'automated-blog-content-creator' ) );
	}

	$settings['is_local'] = 'local' === $host_status;
	$new_origin           = abcc_get_custom_endpoint_origin( $settings['base_url'] );
	$has_constant_key     = defined( 'CUSTOM_API' ) && '' !== (string) constant( 'CUSTOM_API' );
	$has_constant_key     = (bool) apply_filters( 'abcc_custom_endpoint_has_constant_key', $has_constant_key );

	if ( '' !== $bound_origin && $bound_origin !== $new_origin && $has_constant_key ) {
		return new WP_Error( 'abcc_endpoint_constant_origin', __( 'Remove CUSTOM_API from wp-config before changing the custom endpoint origin. You can then configure the new origin and its key.', 'automated-blog-content-creator' ) );
	}

	$settings['constant_key_origin'] = $new_origin;
	abcc_update_setting( 'abcc_custom_endpoint', $settings );

	// Paths may change, but a key never follows a new scheme, host or port.
	if ( '' !== $old_origin && $old_origin !== $new_origin ) {
		abcc_forget_custom_endpoint_key();
	} elseif ( $old_origin !== $new_origin ) {
		// Turned back on at an address: an earlier "verified" was about another one.
		abcc_forget_custom_endpoint_validation();
	}

	abcc_flush_provider_registry_cache();

	return true;
}

/**
 * Drop the saved custom endpoint key and the endpoint's validation state.
 *
 * Reads and writes the option directly so it is safe while the registry is stale.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_forget_custom_endpoint_key() {
	if ( '' !== (string) get_option( 'custom_api_key', '' ) ) {
		update_option( 'custom_api_key', '' );
	}
	abcc_forget_custom_endpoint_validation();
}

/**
 * Drop what is known about the custom endpoint's health: it describes the previous address.
 *
 * Covers both stores (the cached validation and the health row), read and
 * written directly so it is safe while the registry is stale.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_forget_custom_endpoint_validation() {
	delete_transient( 'abcc_last_validation_custom' );

	$rows = get_option( 'abcc_provider_health', array() );
	if ( is_array( $rows ) && isset( $rows['custom'] ) ) {
		unset( $rows['custom'] );
		update_option( 'abcc_provider_health', $rows );
	}
}
