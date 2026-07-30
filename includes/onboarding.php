<?php
/**
 * Onboarding functionality for WP-AutoInsight
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ordered onboarding step model.
 *
 * Numbered steps drive the progress indicator and navigation; 'success' is
 * the terminal screen (num 0). Provider-first flow as of v4.3.
 *
 * @since 4.3.0
 * @return array
 */
function abcc_get_onboarding_steps() {
	return array(
		array(
			'key'     => 'provider',
			'num'     => 1,
			'partial' => 'step-providers.php',
			'title'   => __( 'Connect a provider', 'automated-blog-content-creator' ),
		),
		array(
			'key'     => 'post_status',
			'num'     => 2,
			'partial' => 'step-post-status.php',
			'title'   => __( 'New post default', 'automated-blog-content-creator' ),
		),
		array(
			'key'     => 'first_topic',
			'num'     => 3,
			'partial' => 'step-first-topic.php',
			'title'   => __( 'Create a Topic', 'automated-blog-content-creator' ),
		),
		array(
			'key'     => 'try_audio',
			'num'     => 4,
			'partial' => 'step-try-audio.php',
			'title'   => __( 'Try audio', 'automated-blog-content-creator' ),
		),
		array(
			'key'     => 'success',
			'num'     => 0,
			'partial' => 'step-success.php',
			'title'   => __( 'All set', 'automated-blog-content-creator' ),
		),
	);
}

/**
 * Show the onboarding page for new users.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_show_onboarding_page() {
	wp_enqueue_style( 'abcc-onboarding-styles', plugins_url( '/css/onboarding.css', __DIR__ ), array(), ABCC_VERSION );
	wp_enqueue_script( 'abcc-onboarding-scripts', plugins_url( '/js/onboarding.js', __DIR__ ), array( 'jquery', 'abcc-ui-script' ), ABCC_VERSION, true );

		wp_localize_script(
			'abcc-onboarding-scripts',
			'abccOnboarding',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'abcc_onboarding' ),
				'i18n'    => array(
					'testing'      => __( 'Testing connection...', 'automated-blog-content-creator' ),
					'success'      => __( 'Connection successful!', 'automated-blog-content-creator' ),
					'error'        => __( 'Connection failed. Please check your API key.', 'automated-blog-content-creator' ),
					'generating'   => __( 'Generating your first post...', 'automated-blog-content-creator' ),
					'welcome'      => __( 'Welcome to WP-AutoInsight!', 'automated-blog-content-creator' ),
					/* translators: %s: Comma-separated list of connected AI providers. */
					'connectedVia' => __( 'Connected via WordPress Connectors: %s', 'automated-blog-content-creator' ),
				),
			)
		);

	?>
	<div class="wrap abcc-onboarding-wrap">
		<div class="abcc-onboarding-container">
			<!-- Header -->
			<div class="abcc-onboarding-header">
				<h1><?php esc_html_e( 'Welcome to WP-AutoInsight!', 'automated-blog-content-creator' ); ?></h1>
				<p><?php esc_html_e( 'Let\'s get you set up and creating content in just a few minutes.', 'automated-blog-content-creator' ); ?></p>
				<div class="abcc-progress-bar">
					<div class="abcc-progress-fill" data-step="1"></div>
				</div>
				<div class="abcc-step-indicators">
					<?php
					foreach ( abcc_get_onboarding_steps() as $step ) :
						if ( 0 === $step['num'] ) {
							continue;
						}
						$active = 1 === $step['num'] ? ' active' : '';
						?>
						<span class="abcc-step<?php echo esc_attr( $active ); ?>" data-step="<?php echo (int) $step['num']; ?>"><?php echo (int) $step['num']; ?></span>
					<?php endforeach; ?>
				</div>
			</div>

	<?php
	foreach ( abcc_get_onboarding_steps() as $step ) {
		$partial = __DIR__ . '/admin/onboarding/' . $step['partial'];
		if ( file_exists( $partial ) ) {
			include $partial;
		}
	}
	?>
		</div>
	</div>
	<?php
}

/**
 * Get onboarding goals configuration.
 *
 * @since 3.1.0
 * @return array Array of goal configurations.
 */
function abcc_get_onboarding_goals() {
	return array(
		'blogger'  => array(
			'title'       => __( 'Personal/Business Blog', 'automated-blog-content-creator' ),
			'description' => __( 'Create engaging blog posts for your audience', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'friendly',
				'openai_char_limit'      => 300,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
		'business' => array(
			'title'       => __( 'Business/Corporate Content', 'automated-blog-content-creator' ),
			'description' => __( 'Professional content for business websites', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'professional',
				'openai_char_limit'      => 400,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
		'news'     => array(
			'title'       => __( 'News/Information Site', 'automated-blog-content-creator' ),
			'description' => __( 'Quick, informative articles and updates', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'professional',
				'openai_char_limit'      => 250,
				'openai_generate_images' => false,
				'openai_generate_seo'    => true,
			),
		),
		'creative' => array(
			'title'       => __( 'Creative/Entertainment', 'automated-blog-content-creator' ),
			'description' => __( 'Fun, engaging content with personality', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'friendly',
				'openai_char_limit'      => 350,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
	);
}

/**
 * Get icon for onboarding goal.
 *
 * @since 3.1.0
 * @param string $goal_key The goal identifier.
 * @return string SVG icon markup.
 */
function abcc_get_goal_icon( $goal_key ) {
	$icons = array(
		'blogger'  => '<span class="dashicons dashicons-admin-users"></span>',
		'business' => '<span class="dashicons dashicons-building"></span>',
		'news'     => '<span class="dashicons dashicons-megaphone"></span>',
		'creative' => '<span class="dashicons dashicons-art"></span>',
	);

	return $icons[ $goal_key ] ?? '<span class="dashicons dashicons-welcome-write-blog"></span>';
}

/**
 * Check if user has any API key configured.
 *
 * @since 3.1.0
 * @return bool Whether any API key is configured.
 */
function abcc_has_any_api_key() {
	foreach ( abcc_get_provider_ids() as $provider ) {
		if ( ! empty( abcc_get_provider_api_key( $provider ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if user has generated any content.
 *
 * @since 3.1.0
 * @return bool Whether any content has been generated.
 */
function abcc_has_generated_content() {
	// Check if any posts were created by the plugin.
	$posts = get_posts(
		array(
			'meta_query'     => array(
				array(
					'key'     => '_abcc_generated',
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => 1,
		)
	);

	return ! empty( $posts );
}

/**
 * Mark existing users to skip onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_check_existing_user_on_activation() {
	// If any API key exists or any content has been generated, mark as completed.
	if ( abcc_has_any_api_key() || abcc_has_generated_content() ) {
		update_option( 'abcc_onboarding_completed', true );
	}
}

/**
 * AJAX handler for goal selection.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_goal() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$goal  = isset( $_POST['goal'] ) ? sanitize_text_field( wp_unslash( $_POST['goal'] ) ) : '';
	$goals = abcc_get_onboarding_goals();

	if ( ! isset( $goals[ $goal ] ) ) {
		wp_send_json_error( array( 'message' => 'Invalid goal selected' ) );
	}

	// Apply goal-based settings.
	foreach ( $goals[ $goal ]['settings'] as $option => $value ) {
		abcc_update_setting( $option, $value );
	}

	wp_send_json_success( array( 'message' => 'Goal configured successfully' ) );
}
add_action( 'wp_ajax_abcc_onboarding_goal', 'abcc_handle_onboarding_goal' );

/**
 * AJAX handler for API key testing.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_test_api() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	$provider     = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
	$api_key      = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	$is_wp_config = isset( $_POST['wp_config'] ) && $_POST['wp_config'] === 'true';

	// Get API key from wp-config if needed
	if ( $is_wp_config ) {
		$constant_name = abcc_get_provider_constant_name( $provider );
		if ( ! empty( $constant_name ) && defined( $constant_name ) ) {
			$api_key = constant( $constant_name );
		}
	}

	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => __( 'API key is required', 'automated-blog-content-creator' ) ) );
	}

	$test_result = abcc_test_provider_connection( $provider, $api_key );
	if ( is_wp_error( $test_result ) ) {
		wp_send_json_error( array( 'message' => $test_result->get_error_message() ) );
	}

	if ( $test_result['success'] ) {
		// Save the API key only if it's not from wp-config and provider is a known valid value.
		if ( ! $is_wp_config && in_array( $provider, abcc_get_provider_ids(), true ) ) {
			abcc_set_provider_saved_api_key( $provider, $api_key );
		}

		$first_model = abcc_get_provider_default_model( $provider );
		if ( ! empty( $first_model ) ) {
			abcc_update_setting( 'prompt_select', $first_model );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'automated-blog-content-creator' ) ) );
	} else {
		wp_send_json_error( array( 'message' => $test_result['error'] ) );
	}
}
add_action( 'wp_ajax_abcc_onboarding_test_api', 'abcc_handle_onboarding_test_api' );

/**
 * AJAX handler for first post generation.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_first_post() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = array( 'welcome', 'hello world', 'getting started' );
		$prompt_select = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
		$tone          = abcc_get_setting( 'openai_tone', 'friendly' );
		$char_limit    = abcc_get_setting( 'openai_char_limit', 200 );

		$post_id = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			false,
			$char_limit
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Mark post as generated during onboarding.
		update_post_meta( $post_id, '_abcc_generated', true );
		update_post_meta( $post_id, '_abcc_onboarding_post', true );

		// Mark onboarding as completed.
		update_option( 'abcc_onboarding_completed', true );
		set_transient( 'abcc_onboarding_just_completed', true, 300 );

		wp_send_json_success(
			array(
				'message'  => 'First post created successfully!',
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, '' ),
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_onboarding_first_post', 'abcc_handle_onboarding_first_post' );

/**
 * AJAX handler for skipping onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_skip() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	update_option( 'abcc_onboarding_completed', true );
	wp_send_json_success( array( 'message' => 'Onboarding skipped' ) );
}
add_action( 'wp_ajax_abcc_onboarding_skip', 'abcc_handle_onboarding_skip' );

/**
 * AJAX: create the user's first Topic during onboarding (optional step).
 *
 * @since 4.3.0
 * @return void
 */
function abcc_handle_onboarding_first_topic() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
	$prompt    = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'weekly'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( '' === $title || '' === $prompt ) {
		wp_send_json_error( array( 'message' => __( 'A topic needs a name and a prompt.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$topic_id = abcc_create_topic(
		array(
			'title'     => $title,
			'prompt'    => $prompt,
			'frequency' => $frequency,
		)
	);

	if ( is_wp_error( $topic_id ) ) {
		wp_send_json_error( array( 'message' => $topic_id->get_error_message() ) );
		return;
	}

	wp_send_json_success( array( 'topic_id' => $topic_id ) );
}
add_action( 'wp_ajax_abcc_onboarding_first_topic', 'abcc_handle_onboarding_first_topic' );

/**
 * AJAX: finish onboarding from the final step.
 *
 * @since 4.3.0
 * @return void
 */
function abcc_handle_onboarding_complete() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	update_option( 'abcc_onboarding_completed', true );
	set_transient( 'abcc_onboarding_just_completed', true, 300 );

	wp_send_json_success();
}
add_action( 'wp_ajax_abcc_onboarding_complete', 'abcc_handle_onboarding_complete' );

/**
 * AJAX: persist the default post-status choice from onboarding.
 *
 * @since 4.3.0
 * @return void
 */
function abcc_handle_onboarding_post_status() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$raw_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_ajax_referer.
	$status     = abcc_sanitize_post_status( $raw_status ); // Enforces draft|publish.
	abcc_update_setting( 'abcc_default_post_status', $status );

	wp_send_json_success( array( 'status' => abcc_get_setting( 'abcc_default_post_status' ) ) );
}
add_action( 'wp_ajax_abcc_onboarding_post_status', 'abcc_handle_onboarding_post_status' );

/**
 * Test OpenAI API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_openai_connection( $api_key ) {
	try {
		$client   = new ABCC_OpenAI_Client( $api_key );
		$response = $client->create_chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			array(
				'model'      => 'gpt-4.1-mini',
				'max_tokens' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		if ( ! isset( $response['choices'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from OpenAI', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'OpenAI connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}

/**
 * Test Claude API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_claude_connection( $api_key ) {
	try {
		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		);

		$body = array(
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 5,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message */
					__( 'Network error: %s', 'automated-blog-content-creator' ),
					$response->get_error_message()
				),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown API error';

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'Claude API error (%1$d): %2$s', 'automated-blog-content-creator' ),
					$response_code,
					$error_msg
				),
			);
		}

		// Verify response has expected structure
		$data = json_decode( $response_body, true );
		if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from Claude API', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Claude connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}

/**
 * Test Gemini API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_gemini_connection( $api_key ) {
	$url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . rawurlencode( $api_key );
	$response = wp_remote_post(
		$url,
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'contents' => array(
						array( 'parts' => array( array( 'text' => 'Hello' ) ) ),
					),
				)
			),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'error'   => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = $data['error']['message'] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'Gemini API returned HTTP %d', 'automated-blog-content-creator' ),
			$code
		);
		return array(
			'success' => false,
			'error'   => $message,
		);
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

	if ( null === $text ) {
		return array(
			'success' => false,
			'error'   => __( 'Gemini API returned empty response', 'automated-blog-content-creator' ),
		);
	}

	return array( 'success' => true );
}

/**
 * Admin notice for completed onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_onboarding_completed_notice() {
	if ( get_transient( 'abcc_onboarding_just_completed' ) ) {
		delete_transient( 'abcc_onboarding_just_completed' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( '🎉 Welcome to WP-AutoInsight! You\'re all set up and ready to start creating content.', 'automated-blog-content-creator' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'abcc_onboarding_completed_notice' );

/**
 * Test Perplexity API connection.
 *
 * @since 3.3.0
 * @param string $api_key The API key to test.
 * @return array Whether the connection test succeeded.
 */
function abcc_test_perplexity_connection( $api_key ) {
	try {
		$response = wp_remote_post(
			'https://api.perplexity.ai/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'sonar',
						'max_tokens' => 5,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hello',
							),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message */
					__( 'Network error: %s', 'automated-blog-content-creator' ),
					$response->get_error_message()
				),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown API error';

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'Perplexity API error (%1$d): %2$s', 'automated-blog-content-creator' ),
					$response_code,
					$error_msg
				),
			);
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from Perplexity API', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Perplexity connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}
