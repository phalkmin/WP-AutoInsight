<?php
/**
 * Class ABCC_OpenAI_Client
 *
 * Handles all OpenAI API interactions for the plugin.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
class ABCC_OpenAI_Client {
	private $api_key;
	private $base_url = 'https://api.openai.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key The OpenAI API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Get headers for API requests.
	 *
	 * @return array Headers for API requests.
	 */
	private function get_headers() {
		return array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
		);
	}

	/**
	 * Make a request to the OpenAI API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data The request data.
	 * @param string $method HTTP method to use.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	private function make_request( $endpoint, $data = array(), $method = 'POST' ) {
		$url = $this->base_url . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'             => $method,
			'headers'            => $this->get_headers(),
			'timeout'            => 60,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			// Match the transport's status classes so the error formatter and
			// fallback chain handle OpenAI failures consistently.
			if ( 401 === $code || 403 === $code ) {
				$error_code = 'abcc_provider_auth_error';
			} elseif ( 402 === $code ) {
				$error_code = 'abcc_provider_payment_required';
			} elseif ( 429 === $code ) {
				$error_code = 'abcc_provider_rate_limited';
			} elseif ( $code >= 500 ) {
				$error_code = 'abcc_provider_server_error';
			} else {
				$error_code = 'abcc_provider_http_error';
			}

			$detail = abcc_extract_provider_error_detail( $body, $this->api_key );
			return new WP_Error(
				$error_code,
				'' !== $detail ? $detail : 'Unknown API error',
				array(
					'status'   => $code,
					'provider' => 'openai',
				)
			);
		}

		return json_decode( $body, true );
	}

	/**
	 * Generate chat completions.
	 *
	 * Text generation goes through abcc_call_provider_api(); this stays for
	 * the connection test and reads the same per-model flags as the transport.
	 *
	 * @param array $messages The messages array.
	 * @param array $options Additional options.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function create_chat_completion( $messages, $options = array() ) {
		$default_options = array(
			'model'             => abcc_get_model_id_from_selection( abcc_get_default_text_model() ),
			'temperature'       => 0.7,
			'max_tokens'        => 800,
			'top_p'             => 1,
			'frequency_penalty' => 0,
			'presence_penalty'  => 0,
		);

		$options   = array_merge( $default_options, $options );
		$selection = abcc_parse_model_selection( $options['model'] );
		if ( $selection['qualified'] && 'openai' !== $selection['provider'] ) {
			return new WP_Error( 'abcc_unknown_model', 'Model selection does not belong to OpenAI' );
		}

		$options['model'] = $selection['model'];
		$flags            = abcc_get_model_flags( 'openai', $options['model'] );
		$budget           = max( (int) $options['max_tokens'], (int) $flags['min_output_tokens'] );

		if ( 'max_completion_tokens' === $flags['token_param'] ) {
			$options['max_completion_tokens'] = $budget;
			unset( $options['max_tokens'] );
		} else {
			$options['max_tokens'] = $budget;
		}

		if ( empty( $flags['temperature'] ) ) {
			unset( $options['temperature'], $options['top_p'], $options['frequency_penalty'], $options['presence_penalty'] );
		}

		if ( '' !== (string) $flags['reasoning_effort'] ) {
			$options['reasoning_effort'] = (string) $flags['reasoning_effort'];
		}

		$data = array_merge( $options, array( 'messages' => $messages ) );
		return $this->make_request( 'chat/completions', $data );
	}

	/**
	 * Generate images using DALL-E.
	 *
	 * @param string $prompt The image prompt.
	 * @param array  $options Additional options.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function create_image( $prompt, $options = array() ) {
		// GPT Image models reject 'response_format' (base64 comes back in
		// data[].b64_json) and take low/medium/high quality.
		$default_options = array(
			'model'         => 'gpt-image-2.5-flare',
			'n'             => 1,
			'size'          => '1024x1024',
			'quality'       => 'medium',
			'output_format' => 'png',
		);

		$data = array_merge( $default_options, $options, array( 'prompt' => $prompt ) );
		return $this->make_request( 'images/generations', $data );
	}
}
