<?php
/**
 * User-added model IDs (`abcc_custom_models`), merged into the provider registry on read.
 *
 * Native providers take a typed model ID; OpenRouter picks come from its catalog.
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a string is usable as a model ID.
 *
 * '::' is the selection separator, so an ID may not contain it.
 *
 * @since 4.6.0
 * @param string $model_id Candidate ID.
 * @return bool
 */
function abcc_is_valid_custom_model_id( $model_id ) {
	$model_id = (string) $model_id;

	return 1 === preg_match( '/^[A-Za-z0-9~][A-Za-z0-9._:\/@~-]{0,149}$/', $model_id ) && false === strpos( $model_id, '::' );
}

/**
 * Clean one stored model definition down to the keys the registry understands.
 *
 * @since 4.6.0
 * @param mixed  $definition Raw definition.
 * @param string $model_id   Model ID, used as the name when none is given.
 * @return array
 */
function abcc_sanitize_custom_model_definition( $definition, $model_id ) {
	$definition = is_array( $definition ) ? $definition : array();

	$clean = array(
		'name'           => isset( $definition['name'] ) && '' !== trim( (string) $definition['name'] ) ? sanitize_text_field( (string) $definition['name'] ) : (string) $model_id,
		'context_window' => isset( $definition['context_window'] ) ? min( 10000000, max( 1024, absint( $definition['context_window'] ) ) ) : 8192,
		'max_output'     => isset( $definition['max_output'] ) ? min( 2000000, absint( $definition['max_output'] ) ) : 4096,
		'custom'         => true,
	);

	foreach ( array( 'price_input', 'price_output' ) as $key ) {
		if ( isset( $definition[ $key ] ) && is_numeric( $definition[ $key ] ) && (float) $definition[ $key ] >= 0 ) {
			$clean[ $key ] = (float) $definition[ $key ];
		}
	}

	foreach ( array( 'pricing_date', 'pricing_source', 'gateway_reasoning_effort', 'expires' ) as $key ) {
		if ( isset( $definition[ $key ] ) && '' !== (string) $definition[ $key ] ) {
			$clean[ $key ] = sanitize_text_field( (string) $definition[ $key ] );
		}
	}

	foreach ( array( 'reasoning', 'temperature', 'structured_output', 'system_role', 'free' ) as $key ) {
		if ( isset( $definition[ $key ] ) ) {
			$clean[ $key ] = (bool) $definition[ $key ];
		}
	}

	if ( isset( $definition['min_output_tokens'] ) ) {
		$clean['min_output_tokens'] = min( 32000, absint( $definition['min_output_tokens'] ) );
	}

	return $clean;
}

/**
 * Sanitize the whole `abcc_custom_models` option.
 *
 * @since 4.6.0
 * @param mixed $value Raw value: provider => model ID => definition.
 * @return array
 */
function abcc_sanitize_custom_models( $value ) {
	$clean = array();

	if ( ! is_array( $value ) ) {
		return $clean;
	}

	foreach ( $value as $provider => $models ) {
		$provider = sanitize_key( (string) $provider );
		if ( '' === $provider || ! is_array( $models ) ) {
			continue;
		}

		foreach ( $models as $model_id => $definition ) {
			$model_id = (string) $model_id;
			if ( ! abcc_is_valid_custom_model_id( $model_id ) ) {
				continue;
			}
			$clean[ $provider ][ $model_id ] = abcc_sanitize_custom_model_definition( $definition, $model_id );
		}
	}

	return $clean;
}

/**
 * Stored custom models, for one provider or all.
 *
 * @since 4.6.0
 * @param string $provider Provider ID, or '' for every provider.
 * @return array Model ID => definition, or provider => that map when $provider is ''.
 */
function abcc_get_custom_models( $provider = '' ) {
	$all = abcc_sanitize_custom_models( abcc_get_setting( 'abcc_custom_models', array() ) );

	if ( '' === (string) $provider ) {
		return $all;
	}

	$provider = sanitize_key( (string) $provider );

	return isset( $all[ $provider ] ) ? $all[ $provider ] : array();
}

/**
 * Add a model ID to a text provider.
 *
 * @since 4.6.0
 * @param string $provider   Provider ID.
 * @param string $model_id   Model ID exactly as the provider names it.
 * @param array  $definition name, context_window, max_output (and catalog fields for gateways).
 * @return true|WP_Error
 */
function abcc_add_custom_model( $provider, $model_id, $definition = array() ) {
	$provider = sanitize_key( (string) $provider );
	$model_id = trim( (string) $model_id );

	if ( ! abcc_provider_supports_text_generation( $provider ) || '' === abcc_get_provider_transport( $provider ) ) {
		return new WP_Error( 'abcc_custom_model_provider', __( 'That provider does not generate text.', 'automated-blog-content-creator' ) );
	}

	if ( ! abcc_is_valid_custom_model_id( $model_id ) ) {
		return new WP_Error( 'abcc_custom_model_id', __( 'That model ID is not valid. Copy it exactly as the provider lists it.', 'automated-blog-content-creator' ) );
	}

	$config = abcc_get_provider( $provider );

	if ( ! empty( $config['single_model'] ) ) {
		return new WP_Error( 'abcc_custom_model_provider', __( 'Set this provider\'s model on its own card.', 'automated-blog-content-creator' ) );
	}

	$stored = abcc_get_custom_models();

	if ( isset( $config['text_models'][ $model_id ] ) && ! isset( $stored[ $provider ][ $model_id ] ) ) {
		return new WP_Error( 'abcc_custom_model_exists', __( 'That model is already in the list.', 'automated-blog-content-creator' ) );
	}

	if ( ! isset( $stored[ $provider ][ $model_id ] ) && isset( $stored[ $provider ] ) && count( $stored[ $provider ] ) >= 30 ) {
		return new WP_Error( 'abcc_custom_model_limit', __( 'Remove a model before adding another: the limit is 30 per provider.', 'automated-blog-content-creator' ) );
	}

	$stored[ $provider ][ $model_id ] = abcc_sanitize_custom_model_definition( $definition, $model_id );

	abcc_update_setting( 'abcc_custom_models', $stored );
	abcc_flush_provider_registry_cache();

	return true;
}

/**
 * Remove a user-added model.
 *
 * @since 4.6.0
 * @param string $provider Provider ID.
 * @param string $model_id Model ID.
 * @return bool Whether something was removed.
 */
function abcc_remove_custom_model( $provider, $model_id ) {
	$provider = sanitize_key( (string) $provider );
	$model_id = (string) $model_id;
	$stored   = abcc_get_custom_models();

	if ( ! isset( $stored[ $provider ][ $model_id ] ) ) {
		return false;
	}

	unset( $stored[ $provider ][ $model_id ] );
	if ( empty( $stored[ $provider ] ) ) {
		unset( $stored[ $provider ] );
	}

	abcc_update_setting( 'abcc_custom_models', $stored );
	abcc_flush_provider_registry_cache();

	return true;
}

/**
 * Merge stored custom models into the registry. Built-in IDs always win.
 *
 * @since 4.6.0
 * @param array $registry Provider registry.
 * @return array
 */
function abcc_merge_custom_models( $registry ) {
	foreach ( abcc_get_custom_models() as $provider => $models ) {
		if ( empty( $registry[ $provider ] ) || empty( $registry[ $provider ]['capabilities']['text_generation'] ) ) {
			continue;
		}

		if ( ! isset( $registry[ $provider ]['text_models'] ) || ! is_array( $registry[ $provider ]['text_models'] ) ) {
			$registry[ $provider ]['text_models'] = array();
		}

		foreach ( $models as $model_id => $definition ) {
			if ( ! isset( $registry[ $provider ]['text_models'][ $model_id ] ) ) {
				$registry[ $provider ]['text_models'][ $model_id ] = $definition;
			}
		}
	}

	return $registry;
}

/**
 * AJAX: add a model ID to a provider.
 *
 * For OpenRouter the definition comes from its catalog, never from the request.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_handle_add_custom_model() {
	check_ajax_referer( 'abcc_custom_model_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
	$model_id = isset( $_POST['model_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['model_id'] ) ) ) : '';

	try {
		if ( 'openrouter' === $provider ) {
			$catalog = abcc_get_openrouter_catalog();
			if ( empty( $catalog['models'][ $model_id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'That model is not in the OpenRouter catalog. Search again and pick it from the results.', 'automated-blog-content-creator' ) ) );
				return;
			}
			$definition = $catalog['models'][ $model_id ];
		} else {
			$definition = array(
				'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'context_window' => isset( $_POST['context_window'] ) ? absint( $_POST['context_window'] ) : 8192,
				'max_output'     => isset( $_POST['max_output'] ) ? absint( $_POST['max_output'] ) : 4096,
			);
		}

		$added = abcc_add_custom_model( $provider, $model_id, $definition );
	} catch ( Throwable $e ) {
		error_log( 'WP-AutoInsight add model failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		wp_send_json_error( array( 'message' => __( 'Could not add the model.', 'automated-blog-content-creator' ) ) );
		return;
	}

	if ( is_wp_error( $added ) ) {
		wp_send_json_error( array( 'message' => $added->get_error_message() ) );
		return;
	}

	wp_send_json_success( array( 'selection' => abcc_build_model_selection( $provider, $model_id ) ) );
}
add_action( 'wp_ajax_abcc_add_custom_model', 'abcc_handle_add_custom_model' );

/**
 * AJAX: remove a user-added model.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_handle_remove_custom_model() {
	check_ajax_referer( 'abcc_custom_model_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
	$model_id = isset( $_POST['model_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['model_id'] ) ) ) : '';

	if ( ! abcc_remove_custom_model( $provider, $model_id ) ) {
		wp_send_json_error( array( 'message' => __( 'That model was not in your list.', 'automated-blog-content-creator' ) ) );
		return;
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_abcc_remove_custom_model', 'abcc_handle_remove_custom_model' );
