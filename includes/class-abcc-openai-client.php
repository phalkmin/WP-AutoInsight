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
			'method'  => $method,
			'headers' => $this->get_headers(),
			'timeout' => 60,
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
			$error_data = json_decode( $body, true );
			return new WP_Error(
				'openai_api_error',
				isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown API error',
				array( 'status' => $code )
			);
		}

		return json_decode( $body, true );
	}

	/**
	 * Generate chat completions.
	 *
	 * @param array $messages The messages array.
	 * @param array $options Additional options.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function create_chat_completion( $messages, $options = array() ) {
		$default_options = array(
			'model'             => 'gpt-4.1-mini',
			'temperature'       => 0.7,
			'max_tokens'        => 800,
			'top_p'             => 1,
			'frequency_penalty' => 0,
			'presence_penalty'  => 0,
		);

		$options = array_merge( $default_options, $options );

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
		// GPT Image models (gpt-image-1, *-mini, *-1.5) replaced dall-e-3, which
		// OpenAI deprecated on 2026-05-12. They do NOT accept 'response_format'
		// (always returning base64 in data[].b64_json) and use low/medium/high
		// quality rather than DALL-E's standard/hd.
		$default_options = array(
			'model'         => 'gpt-image-1',
			'n'             => 1,
			'size'          => '1024x1024',
			'quality'       => 'medium',
			'output_format' => 'png',
		);

		$data = array_merge( $default_options, $options, array( 'prompt' => $prompt ) );
		return $this->make_request( 'images/generations', $data );
	}
}
