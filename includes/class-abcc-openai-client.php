<?php
/**
 * Class ABCC_OpenAI_Client
 *
 * Handles all OpenAI API interactions for the plugin
 */
class ABCC_OpenAI_Client {
	private $api_key;
	private $base_url           = 'https://api.openai.com/v1';
	private $is_custom_endpoint = false;

	/**
	 * Constructor
	 *
	 * @param string $api_key The OpenAI API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Set a custom base URL for API requests
	 *
	 * @param string $url The custom base URL.
	 */
	public function set_base_url( $url ) {
		$this->base_url = rtrim( $url, '/' );
	}

	/**
	 * Get available models from the API
	 *
	 * @return array|WP_Error Array of models or WP_Error on failure
	 */
	public function get_available_models() {
		$response = wp_remote_get(
			$this->base_url . '/models',
			array(
				'headers' => $this->get_headers(),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) ) {
			return new WP_Error(
				'invalid_response',
				'No models found in API response'
			);
		}

		// Extract model IDs and details
		$models = array();
		foreach ( $body['data'] as $model ) {
			$models[] = array(
				'id'          => $model['id'],
				'name'        => $model['id'], // You might want to map these to friendly names
				'description' => isset( $model['description'] ) ? $model['description'] : '',
				'cost_tier'   => '1', // Default cost tier
			);
		}

		return $models;
	}

	/**
	 * Get headers based on endpoint type
	 */
	private function get_headers() {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( ! $this->is_custom_endpoint ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		} else {
			// Add custom authentication if needed
			$headers['X-API-Key'] = $this->api_key;
		}

		return $headers;
	}

	/**
	 * Verify if a specific model is available
	 */
	private function verify_model( $model_name ) {
		if ( $this->is_custom_endpoint ) {
			error_log( 'Verifying model availability: ' . $model_name );
			$models = $this->get_available_models();
			if ( is_wp_error( $models ) ) {
				error_log( 'Error fetching models: ' . $models->get_error_message() );
				return false;
			}
			$available = in_array( $model_name, array_column( $models, 'id' ) );
			error_log(
				sprintf(
					'Model %s %s available',
					$model_name,
					$available ? 'is' : 'is not'
				)
			);
			return $available;
		}
		return true;
	}

	/**
	 * Make a request to the OpenAI API
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

		if ( $code !== 200 ) {
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
	 * Generate chat completions
	 *
	 * @param array $messages The messages array.
	 * @param array $options Additional options.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function create_chat_completion( $messages, $options = array() ) {
		$default_options = array(
			'model'             => 'gpt-3.5-turbo',
			'temperature'       => 0.7,
			'max_tokens'        => 800,
			'top_p'             => 1,
			'frequency_penalty' => 0,
			'presence_penalty'  => 0,
		);

		$options = array_merge( $default_options, $options );

		if ( ! $this->verify_model( $options['model'] ) ) {
			return new WP_Error(
				'model_not_available',
				sprintf( 'Model "%s" is not available on this endpoint', $options['model'] )
			);
		}

		$data = array_merge( $options, array( 'messages' => $messages ) );
		return $this->make_request( 'chat/completions', $data );
	}

	/**
	 * Generate images using DALL-E
	 *
	 * @param string $prompt The image prompt.
	 * @param array  $options Additional options.
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function create_image( $prompt, $options = array() ) {
		$default_options = array(
			'model'           => 'dall-e-3',
			'n'               => 1,
			'size'            => '1024x1024',
			'quality'         => 'standard',
			'response_format' => 'url',
		);

		$data = array_merge( $default_options, $options, array( 'prompt' => $prompt ) );
		return $this->make_request( 'images/generations', $data );
	}

	/**
	 * List available models
	 *
	 * @return array|WP_Error The API response or WP_Error on failure.
	 */
	public function list_models() {
		return $this->make_request( 'models', array(), 'GET' );
	}

	public function clear_model_cache() {
		delete_transient( 'abcc_available_models' );
	}
}
