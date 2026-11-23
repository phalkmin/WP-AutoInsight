<?php
/**
 * Request builders and response parsers per provider transport.
 *
 * The registry names a provider's transport, endpoint and auth; per-model
 * flags shape each request. abcc_call_provider_api() drives these.
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transport slug of a provider, or '' when it has none (image-only, unknown).
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @return string
 */
function abcc_get_provider_transport( $provider ) {
	$config = abcc_get_provider( $provider );

	if ( empty( $config['transport'] ) ) {
		return '';
	}

	return in_array( $config['transport'], array( 'openai_compatible', 'anthropic', 'gemini' ), true )
		? (string) $config['transport']
		: '';
}

/**
 * Default request flags every model starts from.
 *
 * @since 4.6.0
 * @return array
 */
function abcc_get_default_model_flags() {
	return array(
		'context_window'    => 0,
		'max_output'        => 0,
		'reasoning'         => false,
		'reasoning_effort'  => '',
		'min_output_tokens' => 0,
		'system_role'       => true,
		'temperature'       => true,
		'token_param'       => 'max_tokens',
		'structured_output' => true,
		'timeout'           => 60,
		'thinking_budget'   => null,
		'thinking_level'    => '',
	);
}

/**
 * Parameter rules for native OpenAI models that are not listed in the registry.
 *
 * GPT-5.x and o-series reject max_tokens and sampling parameters; o-series
 * bills hidden reasoning against the completion budget, hence the larger floor.
 * A generation newer than the registry knows is treated as a reasoning model:
 * the larger floor is only a ceiling when it turns out not to reason.
 *
 * @since 4.6.0
 * @param string $model Bare model ID.
 * @return array Partial flags.
 */
function abcc_infer_openai_model_flags( $model ) {
	$model = (string) $model;

	if ( 0 === strpos( $model, 'gpt-5.' ) ) {
		return array(
			'token_param'       => 'max_completion_tokens',
			'temperature'       => false,
			'reasoning'         => false,
			'reasoning_effort'  => 'none',
			'min_output_tokens' => 1000,
		);
	}

	$newer_generation = preg_match( '/^gpt-(\d+)/', $model, $generation ) && (int) $generation[1] >= 6;

	if ( $newer_generation || 0 === strpos( $model, 'gpt-5' ) || preg_match( '/^o\d/', $model ) ) {
		return array(
			'token_param'       => 'max_completion_tokens',
			'temperature'       => false,
			'reasoning'         => true,
			'reasoning_effort'  => 'low',
			'min_output_tokens' => 4000,
		);
	}

	return array();
}

/**
 * Parameter rules for model IDs the registry does not list (user-added IDs).
 *
 * The registry entry of a listed model overrides every key returned here.
 * Claude 5+ rejects sampling parameters and thinks by default; Gemini 3+
 * cannot turn thinking off, so both need an output floor.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model    Bare model ID.
 * @return array Partial flags.
 */
function abcc_infer_model_flags( $provider, $model ) {
	$model = (string) $model;

	switch ( (string) $provider ) {
		case 'openai':
			return abcc_infer_openai_model_flags( $model );

		case 'claude':
			if ( preg_match( '/^claude-[a-z]+-(\d+)/', $model, $generation ) && (int) $generation[1] >= 5 ) {
				return array(
					'temperature'       => false,
					'reasoning'         => true,
					'reasoning_effort'  => 'low',
					'min_output_tokens' => 4000,
				);
			}
			break;

		case 'gemini':
			if ( preg_match( '/^gemini-(\d+)/', $model, $generation ) && (int) $generation[1] >= 3 ) {
				return array(
					'reasoning'         => true,
					'min_output_tokens' => 2048,
				);
			}
			break;
	}

	return array();
}

/**
 * Effective request flags for a model: defaults, provider-level timeout,
 * per-provider heuristics for unlisted IDs, then the registry entry.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model    Model ID or 'provider::model' selection.
 * @return array
 */
function abcc_get_model_flags( $provider, $model ) {
	$provider = sanitize_key( (string) $provider );
	$model    = abcc_get_model_id_from_selection( $model );
	$config   = abcc_get_provider( $provider );
	$flags    = abcc_get_default_model_flags();

	if ( ! empty( $config['timeout'] ) ) {
		$flags['timeout'] = (int) $config['timeout'];
	}

	$flags = array_merge( $flags, abcc_infer_model_flags( $provider, $model ) );

	$definition = abcc_get_model_definition( $model, $provider );
	if ( is_array( $definition ) ) {
		foreach ( array_keys( abcc_get_default_model_flags() ) as $key ) {
			if ( array_key_exists( $key, $definition ) ) {
				$flags[ $key ] = $definition[ $key ];
			}
		}
		if ( ( null !== $flags['thinking_budget'] && (int) $flags['thinking_budget'] > 0 ) || '' !== (string) $flags['thinking_level'] ) {
			$flags['reasoning'] = true;
		}
	}

	$flags['context_window']    = (int) $flags['context_window'];
	$flags['max_output']        = (int) $flags['max_output'];
	$flags['min_output_tokens'] = (int) $flags['min_output_tokens'];
	$flags['timeout']           = max( 1, (int) $flags['timeout'] );

	/**
	 * Filter the effective request flags for a model.
	 *
	 * @since 4.6.0
	 * @param array  $flags    Flags (see abcc_get_default_model_flags()).
	 * @param string $provider Provider ID.
	 * @param string $model    Bare model ID.
	 */
	return apply_filters( 'abcc_model_flags', $flags, $provider, $model );
}

/**
 * Whether a target can satisfy a request before anything is sent.
 *
 * Scheduled jobs, saved models, extensions and fallbacks bypass the picker,
 * so the format requirement is enforced here, not in the UI.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model    Model ID or selection.
 * @param array  $opts     Call options (require_json, response_format).
 * @return true|WP_Error
 */
function abcc_provider_can_serve_request( $provider, $model, $opts = array() ) {
	$provider = sanitize_key( (string) $provider );

	if ( ! abcc_provider_supports_text_generation( $provider ) || '' === abcc_get_provider_transport( $provider ) ) {
		return new WP_Error(
			'abcc_provider_text_unsupported',
			sprintf( '%s does not generate text', $provider ),
			array( 'provider' => $provider )
		);
	}

	$flags = abcc_get_model_flags( $provider, $model );

	if ( ! empty( $opts['require_json'] ) && empty( $flags['structured_output'] ) ) {
		return new WP_Error(
			'abcc_provider_cannot_comply',
			sprintf( '%s cannot return structured JSON', abcc_get_model_id_from_selection( $model ) ),
			array(
				'provider' => $provider,
				'model'    => abcc_get_model_id_from_selection( $model ),
			)
		);
	}

	return true;
}

/**
 * Split a call into system and user text, honoring the model's system role.
 *
 * @since 4.6.0
 * @param string $prompt User prompt.
 * @param array  $opts   Call options ('system' => string).
 * @param array  $flags  Model flags.
 * @return array{system: string, user: string}
 */
function abcc_build_provider_messages( $prompt, $opts, $flags ) {
	$system = isset( $opts['system'] ) ? trim( (string) $opts['system'] ) : '';
	$user   = (string) $prompt;

	if ( '' !== $system && empty( $flags['system_role'] ) ) {
		$user   = $system . "\n\n" . $user;
		$system = '';
	}

	return array(
		'system' => $system,
		'user'   => $user,
	);
}

/**
 * Headers for a provider request from its auth scheme.
 *
 * @since 4.6.0
 * @param array  $config  Provider registry entry.
 * @param string $api_key API key ('' for auth 'none').
 * @return array
 */
function abcc_build_provider_headers( $config, $api_key ) {
	$headers = array( 'Content-Type' => 'application/json' );
	$auth    = isset( $config['auth'] ) ? (string) $config['auth'] : 'bearer';

	switch ( $auth ) {
		case 'x-api-key':
			$headers['x-api-key'] = $api_key;
			break;
		case 'x-goog-api-key':
			$headers['x-goog-api-key'] = $api_key;
			break;
		case 'none':
			break;
		case 'bearer':
		default:
			$headers['Authorization'] = 'Bearer ' . $api_key;
			break;
	}

	if ( ! empty( $config['extra_headers'] ) && is_array( $config['extra_headers'] ) ) {
		foreach ( $config['extra_headers'] as $name => $value ) {
			$headers[ (string) $name ] = (string) $value;
		}
	}

	return $headers;
}

/**
 * Translate a response-format option into the OpenAI-dialect body key.
 *
 * @since 4.6.0
 * @param array $format 'schema' => array, 'name' => string.
 * @return array
 */
function abcc_build_openai_response_format( $format ) {
	if ( ! empty( $format['schema'] ) && is_array( $format['schema'] ) ) {
		return array(
			'type'        => 'json_schema',
			'json_schema' => array(
				'name'   => ! empty( $format['name'] ) ? sanitize_key( $format['name'] ) : 'abcc_response',
				'schema' => $format['schema'],
				'strict' => true,
			),
		);
	}

	return array( 'type' => 'json_object' );
}

/**
 * Strip JSON Schema keywords Gemini's responseSchema rejects.
 *
 * @since 4.6.0
 * @param array $schema JSON schema.
 * @return array
 */
function abcc_sanitize_gemini_schema( $schema ) {
	unset( $schema['additionalProperties'], $schema['$schema'], $schema['strict'] );

	foreach ( $schema as $key => $value ) {
		if ( is_array( $value ) ) {
			$schema[ $key ] = abcc_sanitize_gemini_schema( $value );
		}
	}

	return $schema;
}

/**
 * Build the HTTP request for one provider call.
 *
 * @since 4.6.0
 * @param array  $config           Provider registry entry.
 * @param string $provider         Provider ID.
 * @param string $model            Bare model ID.
 * @param array  $messages         From abcc_build_provider_messages().
 * @param array  $opts             Call options.
 * @param int    $available_tokens Output budget after floors and clamps.
 * @param array  $flags            Model flags.
 * @return array{url: string, headers: array, body: array, timeout: int, redirection: int, reject_unsafe_urls: bool}
 */
function abcc_build_provider_request( $config, $provider, $model, $messages, $opts, $available_tokens, $flags ) {
	$api_key   = isset( $opts['api_key'] ) ? (string) $opts['api_key'] : '';
	$transport = abcc_get_provider_transport( $provider );
	$endpoint  = isset( $config['endpoint'] ) ? (string) $config['endpoint'] : '';
	$format    = ! empty( $opts['response_format'] ) && is_array( $opts['response_format'] ) ? $opts['response_format'] : null;

	switch ( $transport ) {
		case 'anthropic':
			$body = array(
				'model'      => $model,
				'max_tokens' => $available_tokens,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $messages['user'],
					),
				),
			);
			if ( '' !== $messages['system'] ) {
				$body['system'] = $messages['system'];
			}
			// Claude 5+ thinks by default and bills it against max_tokens; effort is how much.
			if ( '' !== (string) $flags['reasoning_effort'] ) {
				$body['output_config'] = array( 'effort' => (string) $flags['reasoning_effort'] );
			}
			// The Messages API has no response_format; the schema travels as an instruction.
			if ( $format && ! empty( $flags['structured_output'] ) ) {
				$instruction = 'Respond with a single JSON object and nothing else: no prose, no Markdown fences.';
				if ( ! empty( $format['schema'] ) && is_array( $format['schema'] ) ) {
					$instruction .= ' It must match this JSON Schema: ' . wp_json_encode( $format['schema'] );
				}
				$body['system'] = '' !== $messages['system'] ? $messages['system'] . "\n\n" . $instruction : $instruction;
			}
			if ( ! empty( $flags['temperature'] ) && isset( $opts['temperature'] ) ) {
				$body['temperature'] = (float) $opts['temperature'];
			}
			break;

		case 'gemini':
			$endpoint          = str_replace( '{model}', rawurlencode( $model ), $endpoint );
			$generation_config = array( 'maxOutputTokens' => $available_tokens );

			// Gemini 3+ rejects thinkingBudget. Any 2.5 thinking allowance is
			// already included in the budget before its context and output clamps.
			if ( '' !== (string) $flags['thinking_level'] ) {
				$generation_config['thinkingConfig'] = array( 'thinkingLevel' => strtoupper( (string) $flags['thinking_level'] ) );
			} elseif ( null !== $flags['thinking_budget'] ) {
				$generation_config['thinkingConfig'] = array( 'thinkingBudget' => (int) $flags['thinking_budget'] );
			}
			if ( ! empty( $flags['temperature'] ) && isset( $opts['temperature'] ) ) {
				$generation_config['temperature'] = (float) $opts['temperature'];
			}
			if ( $format && ! empty( $flags['structured_output'] ) ) {
				$generation_config['responseMimeType'] = 'application/json';
				if ( ! empty( $format['schema'] ) && is_array( $format['schema'] ) ) {
					$generation_config['responseSchema'] = abcc_sanitize_gemini_schema( $format['schema'] );
				}
			}

			$body = array(
				'contents'         => array(
					array( 'parts' => array( array( 'text' => $messages['user'] ) ) ),
				),
				'generationConfig' => $generation_config,
			);
			if ( '' !== $messages['system'] ) {
				$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $messages['system'] ) ) );
			}
			break;

		case 'openai_compatible':
		default:
			$chat = array();
			if ( '' !== $messages['system'] ) {
				$chat[] = array(
					'role'    => 'system',
					'content' => $messages['system'],
				);
			}
			$chat[] = array(
				'role'    => 'user',
				'content' => $messages['user'],
			);

			$token_param = 'max_completion_tokens' === $flags['token_param'] ? 'max_completion_tokens' : 'max_tokens';
			$body        = array(
				'model'      => $model,
				'messages'   => $chat,
				$token_param => $available_tokens,
			);

			if ( ! empty( $flags['temperature'] ) ) {
				if ( isset( $opts['temperature'] ) ) {
					$body['temperature'] = (float) $opts['temperature'];
				} elseif ( isset( $config['default_temperature'] ) ) {
					$body['temperature'] = (float) $config['default_temperature'];
				}
			}
			if ( '' !== (string) $flags['reasoning_effort'] ) {
				$body['reasoning_effort'] = (string) $flags['reasoning_effort'];
			}
			if ( $format && ! empty( $flags['structured_output'] ) ) {
				$body['response_format'] = abcc_build_openai_response_format( $format );
			}
			break;
	}

	if ( ! empty( $config['request_body_callback'] ) && is_callable( $config['request_body_callback'] ) ) {
		$body = call_user_func( $config['request_body_callback'], $body, $model, $opts );
	}

	/**
	 * Filter the request body before it is sent.
	 *
	 * @since 4.6.0
	 * @param array  $body     Decoded request body.
	 * @param string $provider Provider ID.
	 * @param string $model    Bare model ID.
	 * @param array  $opts     Call options without the key.
	 */
	$safe_opts = $opts;
	unset( $safe_opts['api_key'] );
	$body = apply_filters( 'abcc_provider_request_body', $body, $provider, $model, $safe_opts );

	$timeout = isset( $opts['timeout'] ) ? (int) $opts['timeout'] : (int) $flags['timeout'];

	return array(
		'url'                => $endpoint,
		'headers'            => abcc_build_provider_headers( $config, $api_key ),
		'body'               => $body,
		'timeout'            => max( 1, $timeout ),
		'redirection'        => isset( $config['redirection'] ) ? (int) $config['redirection'] : 0,
		'reject_unsafe_urls' => ! empty( $config['reject_unsafe_urls'] ),
	);
}

/**
 * Perplexity: honor the recency filter setting.
 *
 * @since 4.6.0
 * @param array  $body  Request body.
 * @param string $model Bare model ID.
 * @param array  $opts  Call options.
 * @return array
 */
function abcc_perplexity_request_body( $body, $model, $opts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- registry callback signature.
	$recency = abcc_get_setting( 'abcc_perplexity_recency_filter', '' );
	if ( ! empty( $recency ) ) {
		$body['search_recency_filter'] = $recency;
	}

	return $body;
}

/**
 * Error code for a non-2xx provider status.
 *
 * 401 is a key problem the user must see; 402 is credits; 408, 429 and 5xx are transient.
 *
 * @since 4.6.0
 * @param int $code HTTP status.
 * @return string
 */
function abcc_classify_provider_http_status( $code ) {
	$code = (int) $code;

	if ( 401 === $code || 403 === $code ) {
		return 'abcc_provider_auth_error';
	}
	if ( 402 === $code ) {
		return 'abcc_provider_payment_required';
	}
	if ( 429 === $code ) {
		return 'abcc_provider_rate_limited';
	}
	// A timed-out request is the provider being slow, not a bad request.
	if ( 408 === $code || $code >= 500 ) {
		return 'abcc_provider_server_error';
	}

	return 'abcc_provider_http_error';
}

/**
 * Provider's own error message from a failed response body, if any.
 *
 * @since 4.6.0
 * @param string $body    Raw response body.
 * @param string $api_key Credential used for this request, which may not be saved yet.
 * @return string Plain text, secrets redacted, at most 300 characters.
 */
function abcc_extract_provider_error_detail( $body, $api_key = '' ) {
	$decoded = json_decode( (string) $body, true );
	$detail  = '';

	if ( is_array( $decoded ) ) {
		if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			$detail = $decoded['error']['message'];
		} elseif ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
			$detail = $decoded['error'];
		} elseif ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
			$detail = $decoded['message'];
		}
	}

	$api_key = (string) $api_key;
	if ( '' !== $api_key ) {
		$detail = str_replace( $api_key, '[redacted]', $detail );
	}
	$detail = sanitize_text_field( $detail );
	if ( function_exists( 'abcc_redact_secrets' ) ) {
		$detail = abcc_redact_secrets( $detail );
	}

	return mb_substr( $detail, 0, 300 );
}

/**
 * Upstream failure carried in the body of a 2xx response, or null.
 *
 * A gateway answers 200 once it has routed the request, then reports the
 * provider's error as `error` or as `choices[0].error`.
 *
 * @since 4.6.0
 * @param mixed  $data    Decoded JSON body.
 * @param string $api_key Credential used for this request, which may not be saved yet.
 * @return array{status: int, detail: string}|null 'status' is 0 when the body names none.
 */
function abcc_get_embedded_provider_error( $data, $api_key = '' ) {
	if ( ! is_array( $data ) ) {
		return null;
	}

	$error = null;

	if ( isset( $data['error'] ) && is_array( $data['error'] ) && empty( $data['choices'] ) && empty( $data['content'] ) && empty( $data['candidates'] ) ) {
		$error = $data['error'];
	} elseif ( isset( $data['choices'][0]['error'] ) && is_array( $data['choices'][0]['error'] ) ) {
		$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			$error = $data['choices'][0]['error'];
		}
	}

	if ( null === $error ) {
		return null;
	}

	return array(
		'status' => isset( $error['code'] ) && is_numeric( $error['code'] ) ? (int) $error['code'] : 0,
		'detail' => abcc_extract_provider_error_detail( (string) wp_json_encode( array( 'error' => $error ) ), $api_key ),
	);
}

/**
 * Remove <think> reasoning blocks, closed or truncated, from model text.
 *
 * @since 4.6.0
 * @param string $text Model text.
 * @return string
 */
function abcc_strip_reasoning_blocks( $text ) {
	$text = (string) $text;

	// A failed match returns null; the text must survive that.
	$closed = preg_replace( '#<think>.*?</think>\s*#is', '', $text );
	$text   = is_string( $closed ) ? $closed : $text;
	$open   = preg_replace( '#^\s*<think>.*\z#is', '', $text );

	return is_string( $open ) ? $open : $text;
}

/**
 * Normalize the text parts a chat message may carry (string or parts array).
 *
 * @since 4.6.0
 * @param mixed $content Message content.
 * @return string|null
 */
function abcc_flatten_message_content( $content ) {
	if ( is_string( $content ) ) {
		return $content;
	}

	if ( is_array( $content ) ) {
		$chunks = array();
		foreach ( $content as $part ) {
			if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) && ( ! isset( $part['type'] ) || 'text' === $part['type'] ) ) {
				$chunks[] = $part['text'];
			}
		}
		return empty( $chunks ) ? null : implode( "\n", $chunks );
	}

	return null;
}

/**
 * Pull text, usage and the truncation signal out of a decoded response.
 *
 * @since 4.6.0
 * @param string $transport Transport slug.
 * @param mixed  $data      Decoded JSON body.
 * @return array{text: string|null, usage: array, tokens: array, truncated: bool, reasoning_only: bool, blocked: bool, block_reason: string}
 */
function abcc_parse_provider_response( $transport, $data ) {
	$parsed = array(
		'text'           => null,
		'usage'          => array(),
		'tokens'         => array(
			'input'     => null,
			'output'    => null,
			'reasoning' => null,
		),
		'truncated'      => false,
		'reasoning_only' => false,
		'blocked'        => false,
		'block_reason'   => '',
	);

	if ( ! is_array( $data ) ) {
		return $parsed;
	}

	switch ( $transport ) {
		case 'anthropic':
			$chunks       = array();
			$had_thinking = false;
			if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
				foreach ( $data['content'] as $block ) {
					// Thinking blocks are hidden reasoning, never post content.
					if ( is_array( $block ) && isset( $block['type'] ) && in_array( $block['type'], array( 'thinking', 'redacted_thinking' ), true ) ) {
						$had_thinking = true;
						continue;
					}
					if ( ! is_array( $block ) || ( isset( $block['type'] ) && 'text' !== $block['type'] ) || ! isset( $block['text'] ) || ! is_string( $block['text'] ) ) {
						continue;
					}
					$chunks[] = $block['text'];
				}
			}
			$parsed['text'] = empty( $chunks ) ? null : implode( "\n", $chunks );
			if ( $had_thinking && null === $parsed['text'] ) {
				$parsed['reasoning_only'] = true;
			}
			$parsed['usage']     = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
			$parsed['truncated'] = isset( $data['stop_reason'] ) && 'max_tokens' === $data['stop_reason'];
			$parsed['blocked']   = isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'];
			if ( $parsed['blocked'] ) {
				$parsed['block_reason'] = 'refusal';
			}
			if ( isset( $parsed['usage']['input_tokens'] ) ) {
				$parsed['tokens']['input'] = (int) $parsed['usage']['input_tokens'];
			}
			if ( isset( $parsed['usage']['output_tokens'] ) ) {
				$parsed['tokens']['output'] = (int) $parsed['usage']['output_tokens'];
			}
			break;

		case 'gemini':
			$parts        = isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] )
				? $data['candidates'][0]['content']['parts']
				: array();
			$finish       = (string) ( $data['candidates'][0]['finishReason'] ?? '' );
			$chunks       = array();
			$had_thinking = false;
			foreach ( $parts as $part ) {
				// Thought parts are hidden reasoning, never post content.
				if ( ! empty( $part['thought'] ) ) {
					$had_thinking = true;
					continue;
				}
				if ( ! isset( $part['text'] ) || ! is_string( $part['text'] ) ) {
					continue;
				}
				$chunks[] = $part['text'];
			}
			$parsed['text'] = ! empty( $chunks ) ? implode( "\n", $chunks ) : ( 'MAX_TOKENS' === $finish ? '' : null );
			if ( $had_thinking && empty( $chunks ) ) {
				$parsed['reasoning_only'] = true;
			}
			$parsed['usage']     = isset( $data['usageMetadata'] ) && is_array( $data['usageMetadata'] ) ? $data['usageMetadata'] : array();
			$parsed['truncated'] = 'MAX_TOKENS' === $finish;
			$blocked_reason      = ! empty( $data['promptFeedback']['blockReason'] )
				? (string) $data['promptFeedback']['blockReason']
				: $finish;
			$parsed['blocked']   = '' !== $blocked_reason && in_array( $blocked_reason, array( 'SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'LANGUAGE' ), true );
			if ( $parsed['blocked'] ) {
				$parsed['block_reason'] = $blocked_reason;
			}
			if ( isset( $parsed['usage']['promptTokenCount'] ) ) {
				$parsed['tokens']['input'] = (int) $parsed['usage']['promptTokenCount'];
			}
			if ( isset( $parsed['usage']['candidatesTokenCount'] ) ) {
				$parsed['tokens']['output'] = (int) $parsed['usage']['candidatesTokenCount'];
			}
			if ( isset( $parsed['usage']['thoughtsTokenCount'] ) ) {
				$parsed['tokens']['reasoning'] = (int) $parsed['usage']['thoughtsTokenCount'];
			}
			break;

		case 'openai_compatible':
		default:
			$message = isset( $data['choices'][0]['message'] ) && is_array( $data['choices'][0]['message'] ) ? $data['choices'][0]['message'] : array();
			$text    = array_key_exists( 'content', $message ) ? abcc_flatten_message_content( $message['content'] ) : null;

			// Only presence is read; the reasoning text itself is never kept or logged.
			$has_reasoning = ( isset( $message['reasoning_content'] ) && '' !== trim( (string) $message['reasoning_content'] ) )
				|| ( isset( $message['reasoning'] ) && is_string( $message['reasoning'] ) && '' !== trim( $message['reasoning'] ) );

			if ( is_string( $text ) ) {
				$text = abcc_strip_reasoning_blocks( $text );
			}

			$finish_reason          = (string) ( $data['choices'][0]['finish_reason'] ?? '' );
			$parsed['text']         = $text;
			$parsed['usage']        = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : array();
			$parsed['truncated']    = 'length' === $finish_reason;
			$parsed['blocked']      = 'content_filter' === $finish_reason;
			$parsed['block_reason'] = $parsed['blocked'] ? 'content_filter' : '';
			if ( isset( $parsed['usage']['prompt_tokens'] ) ) {
				$parsed['tokens']['input'] = (int) $parsed['usage']['prompt_tokens'];
			}
			if ( isset( $parsed['usage']['completion_tokens'] ) ) {
				$parsed['tokens']['output'] = (int) $parsed['usage']['completion_tokens'];
			}
			if ( isset( $parsed['usage']['completion_tokens_details']['reasoning_tokens'] ) ) {
				$parsed['tokens']['reasoning'] = (int) $parsed['usage']['completion_tokens_details']['reasoning_tokens'];
			}

			if ( $has_reasoning && ( null === $text || '' === trim( $text ) ) && ! empty( $message ) ) {
				$parsed['reasoning_only'] = true;
			}
			break;
	}

	return $parsed;
}

/**
 * Three-stage connection probe: endpoint reachable, credentials accepted,
 * the selected model generates (a 1-token completion).
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $api_key  API key ('' for keyless local endpoints).
 * @param string $model    Optional model; defaults to the provider's test model.
 * @return array{reachable: bool, authenticated: bool, generates: bool, success: bool, error: string, code: string}
 */
function abcc_test_provider_generation( $provider, $api_key, $model = '' ) {
	$provider = sanitize_key( (string) $provider );
	$model    = '' !== (string) $model ? (string) $model : abcc_get_provider_connection_test_model( $provider );

	$result = abcc_call_provider_api(
		$provider,
		$model,
		'Reply with the single word: ok',
		array(
			'api_key'    => (string) $api_key,
			'max_tokens' => 5,
			'operation'  => 'probe',
		)
	);

	$code  = is_wp_error( $result['error'] ) ? (string) $result['error']->get_error_code() : '';
	$probe = array(
		'reachable'     => 'http_request_failed' !== $code,
		'authenticated' => ! in_array( $code, array( 'http_request_failed', 'abcc_provider_auth_error', 'abcc_provider_payment_required' ), true ),
		'generates'     => '' === $code,
		'success'       => '' === $code,
		'error'         => is_wp_error( $result['error'] ) ? $result['error']->get_error_message() : '',
		'code'          => $code,
	);

	return $probe;
}
