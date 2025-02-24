<?php
/**
 * Plugin Name: WP-AutoInsight
 * Description: Create blog posts automatically using the OpenAI and Gemini APIs!
 * Version: 2.1
 * Author: Paulo H. Alkmin
 * Author URI: https://phalkmin.me/
 * Text Domain: automated-wordpress-content-creator
 * Domain Path: /languages
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'VERSION', filemtime( plugin_dir_path( '/auto-post.php', __FILE__ ) ) );


require_once __DIR__ . '/vendor/autoload.php';
require plugin_dir_path( __FILE__ ) . 'admin.php';
require plugin_dir_path( __FILE__ ) . 'gpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-abcc-openai-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/token-handling.php';

/**
 * Handle API request errors.
 *
 * @param mixed  $response The API response.
 * @param string $api      The API name.
 */
function handle_api_request_error( $response, $api ) {
	if ( ! ( $response instanceof WP_Error ) && ! empty( $response ) ) {
		$response = new WP_Error( 'api_error', $response );
	}

	// Error logging.
	$error_message = 'Unknown error occurred.';
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
	}

	error_log( sprintf( '%s API Request Error: %s', $api, $error_message ) );

	add_settings_error(
		'openai-settings',
		'api-request-error',
		// Translators: %1$s is the API name, %2$s is the error message.
		sprintf( esc_html__( 'Error in %1$s API request: %2$s', 'automated-blog-content-creator' ), $api, $error_message ),
		'error'
	);
}


/**
 * The function `abcc_enqueue_scripts` is used to enqueue CSS and JavaScript files, including Select2
 * library, for the WordPress admin area.
 */
function abcc_enqueue_scripts() {
	wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
	wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0', true );
	wp_enqueue_style( 'abcc-admin-style', plugins_url( '/css/admin-style.css', __FILE__ ), array(), VERSION, true );
	wp_enqueue_script( 'abcc-admin-script', plugins_url( '/js/admin-script.js', __FILE__ ), array( 'jquery' ), VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'abcc_enqueue_scripts' );


/**
 * Check and retrieve the appropriate API key based on the selected AI model.
 *
 * @return string The API key if found, empty string otherwise.
 */
function abcc_check_api_key() {
	$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );
	$api_key       = '';

	$model_options = abcc_get_ai_model_options();

	$provider = '';
	foreach ( $model_options as $provider_key => $group ) {
		if ( isset( $group['options'][ $prompt_select ] ) ) {
			$provider = $provider_key;
			break;
		}
	}

	switch ( $provider ) {
		case 'openai':
			$api_key    = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
			$key_source = defined( 'OPENAI_API' ) ? 'constant' : 'option';
			break;

		case 'claude':
			$api_key    = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
			$key_source = defined( 'CLAUDE_API' ) ? 'constant' : 'option';
			break;

		case 'gemini':
			$api_key    = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
			$key_source = defined( 'GEMINI_API' ) ? 'constant' : 'option';
			break;
	}

	return $api_key;
}

/**
 * Determines which image generation service to use based on settings and availability.
 *
 * @param string $text_model The selected text generation model
 * @return array Array containing 'service' and 'api_key'
 */
function abcc_determine_image_service( $text_model ) {
	$openai_key    = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	$stability_key = defined( 'STABILITY_API' ) ? STABILITY_API : get_option( 'stability_api_key', '' );

	$preferred_image_service = get_option( 'preferred_image_service', 'auto' );

	if ( $preferred_image_service !== 'auto' ) {
		if ( $preferred_image_service === 'stability' && ! empty( $stability_key ) ) {
			return array(
				'service' => 'stability',
				'api_key' => $stability_key,
			);
		}
		if ( $preferred_image_service === 'openai' && ! empty( $openai_key ) ) {
			return array(
				'service' => 'openai',
				'api_key' => $openai_key,
			);
		}
	}

	$model_provider = '';
	if ( strpos( $text_model, 'gpt' ) !== false || $text_model === 'openai' ) {
		$model_provider = 'openai';
	} elseif ( strpos( $text_model, 'claude' ) !== false ) {
		$model_provider = 'claude';
	} elseif ( strpos( $text_model, 'gemini' ) !== false ) {
		$model_provider = 'gemini';
	}

	if ( $model_provider === 'openai' && ! empty( $openai_key ) ) {
		return array(
			'service' => 'openai',
			'api_key' => $openai_key,
		);
	} elseif ( ! empty( $stability_key ) ) {
		return array(
			'service' => 'stability',
			'api_key' => $stability_key,
		);
	}

	return array(
		'service' => null,
		'api_key' => null,
	);
}

/**
 * The function `abcc_get_available_models` retrieves available models either from cache or by making
 * an API call and caches the result for 1 hour.
 *
 * @return array function `abcc_get_available_models` is returning an array of available models from the
 * cache if available, otherwise it checks for an API key, retrieves available models using the API
 * client, caches the models for 1 hour, and returns the models. If there is an error during the
 * process, it returns an empty array.
 */
function abcc_get_available_models() {
	$cached_models = get_transient( 'abcc_available_models' );
	if ( false !== $cached_models ) {
		return $cached_models;
	}

	$api_key = abcc_check_api_key();
	if ( empty( $api_key ) ) {
		return array();
	}

	$client = new ABCC_OpenAI_Client( $api_key );
	$models = $client->get_available_models();

	if ( ! is_wp_error( $models ) ) {
		set_transient( 'abcc_available_models', $models, HOUR_IN_SECONDS );
		return $models;
	}

	return array();
}

function abcc_clear_model_cache( $old_value, $new_value ) {
	delete_transient( 'abcc_available_models' );
}
add_action( 'update_option_openai_custom_endpoint', 'abcc_clear_model_cache', 10, 2 );

function abcc_refresh_models_ajax() {
	check_ajax_referer( 'abcc_refresh_models' );

	delete_transient( 'abcc_available_models' );
	$models = abcc_get_available_models();

	wp_send_json_success(
		array(
			'models' => $models,
		)
	);
}
add_action( 'wp_ajax_abcc_refresh_models', 'abcc_refresh_models_ajax' );

/**
 * The function abcc_create_block creates a WordPress block with specified attributes and content.
 *
 * @param string $block_name The `block_name` parameter in the `abcc_create_block` function is used to specify.
 * the name of the block that you want to create. This could be the name of a custom block that you
 * have registered in WordPress or a core block like 'paragraph', 'heading', 'image', etc
 * @param array  $attributes The `abcc_create_block` function you provided is used to create a block in
 *  WordPress with the specified block name, attributes, and content. Here's an explanation of the
 *  parameters:
 * @param string $content The `abcc_create_block` function you provided is used to create a block of content
 * for the WordPress block editor. The function takes three parameters:
 *
 * @return string The function `abcc_create_block` returns a block of content in the format used by the
 * WordPress block editor (Gutenberg). The content includes the block name, attributes encoded as a
 * JSON string, and the block content itself enclosed in HTML comments.
 */
function abcc_create_block( $block_name, $attributes = array(), $content = '' ) {
	$content = trim( $content );

	if ( $block_name === 'heading' ) {
		$level = isset( $attributes['level'] ) ? $attributes['level'] : 2;
		return sprintf(
			'<!-- wp:heading {"level":%d} --><h%d class="wp-block-heading">%s</h%d><!-- /wp:heading -->',
			$level,
			$level,
			esc_html( $content ),
			$level
		);
	}

	$attributes_string = ! empty( $attributes ) ? ' ' . wp_json_encode( $attributes ) : '';

	if ( $block_name === 'paragraph' ) {
		return sprintf(
			'<!-- wp:paragraph%s --><p>%s</p><!-- /wp:paragraph -->',
			$attributes_string,
			esc_html( $content )
		);
	}

	return sprintf(
		'<!-- wp:%s%s -->%s<!-- /wp:%s -->',
		esc_attr( $block_name ),
		$attributes_string,
		wp_kses_post( $content ),
		esc_attr( $block_name )
	);
}


/**
 * The function `abcc_create_blocks` creates an array of paragraph blocks with specified attributes
 * from a given array of text items.
 *
 * @param array $text_array The `abcc_create_blocks` function takes an array of text items as input and
 * creates an array of blocks based on each text item. Each block has a predefined structure with a
 * name, attributes, and content.
 *
 * @return array The function `abcc_create_blocks` takes an array of text items as input, creates blocks for
 * each non-empty item, and returns an array of blocks. Each block has a name of 'paragraph',
 * attributes including alignment and a custom attribute with a value, and the content sanitized using
 * `wp_kses_post`.
 */
function abcc_create_blocks( $text_array ) {
	$blocks = array();
	foreach ( $text_array as $item ) {
		$item = trim( $item );
		if ( empty( $item ) ) {
			continue;
		}

		// Handle headings
		if ( preg_match( '/<h2>(.*?)<\/h2>/', $item, $matches ) ) {
			$blocks[] = array(
				'name'       => 'heading',
				'attributes' => array(
					'level'     => 2,
					'className' => 'wp-block-heading',
				),
				'content'    => wp_strip_all_tags( $matches[1] ),
			);
			continue;
		}

		if ( preg_match( '/<h3>(.*?)<\/h3>/', $item, $matches ) ) {
			$blocks[] = array(
				'name'       => 'heading',
				'attributes' => array(
					'level'     => 3,
					'className' => 'wp-block-heading',
				),
				'content'    => wp_strip_all_tags( $matches[1] ),
			);
			continue;
		}

		// Handle regular paragraphs
		$blocks[] = array(
			'name'       => 'paragraph',
			'attributes' => array(),
			'content'    => wp_strip_all_tags( $item ),
		);
	}
	return $blocks;
}


/**
 * The function abcc_gutenberg_blocks processes an array of blocks to create block contents in PHP.
 *
 * @param array $blocks The `abcc_gutenberg_blocks` function takes an array of blocks as a parameter. Each
 * block in the array should have the following structure:
 *
 * @return string The function `abcc_gutenberg_blocks` is returning the concatenated block contents generated
 * by calling the `abcc_create_block` function for each block in the input array ``.
 */
function abcc_gutenberg_blocks( $blocks = array() ) {
	$block_contents = '';
	foreach ( $blocks as $block ) {
		$block_contents .= abcc_create_block( $block['name'], $block['attributes'], $block['content'] );
	}
	return $block_contents;
}

/**
 * Checks which SEO plugin is active and returns its identifier.
 *
 * @return string Identifier of the active SEO plugin or 'none'
 */
function abcc_get_active_seo_plugin() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
		return 'yoast';
	}
	return 'none';
}


/**
 * Gets the appropriate meta fields based on the active SEO plugin.
 *
 * @param array $seo_data Array containing SEO metadata
 * @return array Meta input array for wp_insert_post
 */
function abcc_get_seo_meta_fields( $seo_data ) {
	$active_seo_plugin = abcc_get_active_seo_plugin();
	$meta_input        = array();

	switch ( $active_seo_plugin ) {
		case 'yoast':
			$meta_input = array(
				'_yoast_wpseo_metadesc'              => $seo_data['meta_description'],
				'_yoast_wpseo_focuskw'               => $seo_data['primary_keyword'],
				'_yoast_wpseo_metakeywords'          => implode( ',', $seo_data['secondary_keywords'] ),
				'_yoast_wpseo_opengraph-description' => $seo_data['social_excerpt'],
			);
			break;
	}
	return $meta_input;
}


/**
 * Generates a new post using AI services.
 *
 * @param string  $api_key        The API key for the selected service
 * @param array   $keywords       Keywords to focus the article on
 * @param string  $prompt_select  Which AI service to use
 * @param string  $tone          The tone to use for the article
 * @param boolean $auto_create   Whether this is an automated creation
 * @param int     $char_limit    Maximum token limit
 * @return int|WP_Error Post ID on success, WP_Error on failure
 */
function abcc_openai_generate_post( $api_key, $keywords, $prompt_select, $tone = 'default', $auto_create = false, $char_limit = 200 ) {
	try {
		$generate_seo = get_option( 'openai_generate_seo', true ) && abcc_get_active_seo_plugin() !== 'none';

		if ( $generate_seo ) {
			// Generate title and SEO data
			$title_and_seo = abcc_generate_title_and_seo(
				$api_key,
				$keywords,
				$prompt_select,
				array(
					'site_name'        => get_bloginfo( 'name' ),
					'site_description' => get_bloginfo( 'description' ),
				)
			);
			$title         = $title_and_seo['title'];
			$seo_data      = $title_and_seo['seo_data'];
		} else {
			// Just generate a title
			$title    = abcc_generate_title( $api_key, $keywords, $prompt_select );
			$seo_data = array();
		}

		// Then, generate the content
		$content_array = abcc_generate_post_content(
			$api_key,
			$keywords,
			$prompt_select,
			$title,
			$char_limit
		);

		$content_array = array_filter(
			$content_array,
			function ( $line ) {
				return ! strpos( $line, '<title>' ) && trim( $line ) !== '';
			}
		);

		if ( empty( $content_array ) ) {
			throw new Exception( 'Content generation failed' );
		}

		$content_array = array_map( 'trim', $content_array );
		$content_array = array_filter(
			$content_array,
			function ( $line ) {
				return ! empty( $line ) &&
						! strpos( $line, '<title>' ) &&
						! strpos( $line, '[SEO]' );
			}
		);

		$format_content = abcc_create_blocks( $content_array );
		$post_content   = abcc_gutenberg_blocks( $format_content );

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => wp_kses_post( $post_content ),
			'post_status'   => 'draft',
			'post_author'   => get_current_user_id(),
			'post_type'     => 'post',
			'post_category' => get_option( 'openai_selected_categories', array() ),
		);

		// Add SEO data if Yoast is active
		if ( $generate_seo && ! empty( $seo_data ) ) {
			$post_data['meta_input'] = abcc_get_seo_meta_fields( $seo_data );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		if ( get_option( 'openai_generate_images', true ) ) {
			try {
				if ( $image_url = abcc_generate_featured_image( $prompt_select, $keywords, $category_names ) ) {
					abcc_set_featured_image( $post_id, $image_url );
				}
			} catch ( Exception $e ) {
				error_log( 'Featured image generation failed but post was created: ' . $e->getMessage() );
			}
		}

		if ( get_option( 'openai_email_notifications', false ) ) {
			abcc_send_post_notification( $post_id );
		}

		return $post_id;

	} catch ( Exception $e ) {
		error_log( 'Post Generation Error: ' . $e->getMessage() );
		return new WP_Error( 'post_generation_failed', $e->getMessage() );
	}
}

/**
 * Send email notification about new post creation.
 *
 * @param int $post_id The ID of the created post.
 * @return bool Whether the email was sent successfully.
 */
function abcc_send_post_notification( $post_id ) {
	$admin_email = get_option( 'admin_email' );
	$post        = get_post( $post_id );

	if ( ! $post ) {
		return false;
	}

	$subject = sprintf(
		__( 'New AI Generated Post: %s', 'automated-blog-content-creator' ),
		$post->post_title
	);

	$message = sprintf(
		__( 'A new post "%1$s" has been created automatically.\n\nYou can edit it here: %2$s', 'automated-blog-content-creator' ),
		$post->post_title,
		get_edit_post_link( $post_id, '' )
	);

	return wp_mail( $admin_email, $subject, $message );
}


/**
 * Helper function to build the content generation prompt.
 *
 * @param array  $keywords Array of keywords
 * @param string $tone Content tone
 * @param array  $category_names Array of category names
 * @param int    $char_limit Character limit
 * @return string
 */
function abcc_build_content_prompt( $keywords, $tone, $category_names, $char_limit ) {
	$site_name        = get_bloginfo( 'name' );
	$site_description = get_bloginfo( 'description' );
	$site_url         = get_bloginfo( 'url' );

	// Build a more structured prompt
	$prompt_parts = array();

	// Core instructions
	$prompt_parts[] = sprintf(
		'You are an expert content writer for %s, a website focused on %s',
		$site_name,
		$site_description
	);

	// Tone setting
	$tone_instructions = array(
		'funny'    => 'Write in a humorous and entertaining style, using clever wordplay and pop culture references where appropriate. Keep the tone light and engaging while still being informative.',
		'business' => 'Maintain a professional and authoritative tone, focusing on clear, actionable information and industry insights.',
		'academic' => 'Write in a scholarly tone with well-researched information, clear arguments, and proper citations where relevant.',
		'epic'     => 'Use dramatic and powerful language to create an engaging narrative, making even simple topics sound grand and exciting.',
		'personal' => 'Write in a conversational, relatable tone as if sharing experiences with a friend, while maintaining professionalism.',
		'default'  => 'Balance professionalism with accessibility, creating engaging content that informs and entertains.',
	);

	$prompt_parts[] = $tone_instructions[ $tone ] ?? $tone_instructions['default'];

	// Content structure
	$prompt_parts[] = 'Create a comprehensive article that includes:
        - An engaging <h1> title that includes key terms naturally
        - A compelling introduction that hooks the reader
        - Well-organized main sections with <h2> headings
        - Subsections using <h3> headings where appropriate for detailed breakdowns
        - A meta description (max 160 characters) summarizing the article for SEO
        - 3-5 focus keywords for the article
        - Relevant examples and references
        - A strong conclusion that summarizes key points';

	// Keywords and categories focus
	if ( ! empty( $keywords ) ) {
		$prompt_parts[] = sprintf(
			'Focus on these main topics and keywords: %s. Integrate them naturally throughout the content.',
			implode( ', ', array_map( 'sanitize_text_field', $keywords ) )
		);
	}

	if ( abcc_get_active_seo_plugin() !== 'none' ) {
		$prompt_parts[] = 'Additionally, provide the following SEO elements separated by [SEO] tags:
            - A compelling meta description (max 160 characters)
            - Primary keyword
            - Secondary keywords (2-3)
            - Social media excerpt (max 200 characters)
            
            Format the SEO section exactly like this:
            [SEO]
            Meta Description: Your meta description here
            Primary Keyword: Your primary keyword
            Secondary Keywords: keyword1, keyword2, keyword3
            Social Excerpt: Your social media excerpt here
            [SEO]';
	}

	if ( ! empty( $category_names ) ) {
		$prompt_parts[] = sprintf(
			'This content belongs in the following categories: %s. Ensure the content aligns with these themes.',
			implode( ', ', $category_names )
		);
	}

	// SEO and formatting guidelines
	$prompt_parts[] = sprintf(
		'Format requirements:
		- Use HTML formatting
		- Structure content with <h1> for the main title only
		- Keep the total content under %d tokens
		- Ensure the content is SEO-optimized with natural keyword placement
		- Break up text into readable paragraphs
		- Use engaging subheadings for each main section',
		$char_limit
	);

	return implode( "\n\n", $prompt_parts );
}

/**
 * Helper function to generate content using selected AI service.
 *
 * @param string $api_key API key
 * @param string $prompt Content prompt
 * @param string $service AI service to use
 * @param int    $char_limit Character limit
 * @return array|false
 */
function abcc_generate_content( $api_key, $prompt, $service, $char_limit ) {

	$result = false;

	switch ( $service ) {
		case 'gpt-3.5-turbo':
		case 'gpt-4-turbo-preview':
		case 'gpt-4':
			$result = abcc_openai_generate_text( $api_key, $prompt, $char_limit, $service );
			break;
		case 'gemini-pro':
			$result = abcc_gemini_generate_text( $api_key, $prompt, $char_limit );
			break;
		case 'claude-3-haiku':
		case 'claude-3-sonnet':
		case 'claude-3-opus':
			$result = abcc_claude_generate_text( $api_key, $prompt, $char_limit, $service );
			break;
	}

	if ( $result === false ) {
		error_log(
			sprintf(
				'Content generation failed for service %s. Prompt: %s',
				$service,
				substr( $prompt, 0, 100 ) . '...' // Log first 100 chars of prompt
			)
		);
	}

	return $result;
}

/**
 * Generates a featured image using AI services.
 *
 * @param string $api_key API key
 * @param array  $keywords Keywords for image generation
 * @param array  $category_names Category names for context
 * @return string|false Image URL on success, false on failure
 */
function abcc_generate_featured_image( $text_model, $keywords, $category_names = array() ) {
	try {
		// Check if image generation is enabled
		if ( ! get_option( 'openai_generate_images', true ) ) {
			return false;
		}

		// Build image prompt
		$prompt = abcc_build_image_prompt( $keywords, $category_names );

		// Determine which service to use
		$image_service = abcc_determine_image_service( $text_model );

		if ( empty( $image_service['service'] ) ) {
			error_log( 'No available image generation service' );
			return false;
		}

		error_log(
			sprintf(
				'Attempting to generate image using %s service with prompt: %s',
				$image_service['service'],
				$prompt
			)
		);

		// Generate image using determined service
		switch ( $image_service['service'] ) {
			case 'openai':
				$images = abcc_openai_generate_images( $image_service['api_key'], $prompt, 1 );
				if ( ! empty( $images ) && is_array( $images ) ) {
					return $images[0];
				}
				break;

			case 'stability':
				$result = abcc_stability_generate_images( $prompt, 1, $image_service['api_key'] );
				if ( $result ) {
					return $result;
				}
				break;
		}

		error_log( 'Image generation failed for selected service' );
		return false;

	} catch ( Exception $e ) {
		error_log( 'Image Generation Error: ' . $e->getMessage() );
		return false;
	}
}
/**
 * Sets the featured image for a post.
 *
 * @param int    $post_id Post ID
 * @param string $image_url Image URL
 * @return int|false Attachment ID on success, false on failure
 */
function abcc_set_featured_image( $post_id, $image_url ) {
	try {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Download and attach the image
		$attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new Exception( $attachment_id->get_error_message() );
		}

		// Set as featured image
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;

	} catch ( Exception $e ) {
		error_log( 'Featured Image Error: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Builds the image generation prompt.
 *
 * @param array $keywords Keywords for the image
 * @param array $category_names Category names for context
 * @return string
 */
function abcc_build_image_prompt( $keywords, $category_names ) {
	$prompt_parts = array();

	// Add keywords
	if ( ! empty( $keywords ) ) {
		$prompt_parts[] = implode( ', ', array_map( 'sanitize_text_field', $keywords ) );
	}

	// Add categories for context
	if ( ! empty( $category_names ) ) {
		$prompt_parts[] = 'Related to: ' . implode( ', ', $category_names );
	}

	// Add style guidance
	$prompt_parts[] = 'Create a high-quality, professional image suitable for a blog post';

	return implode( '. ', $prompt_parts );
}

/**
 * Check if the 'openai_generate_post_hook' event is scheduled and get schedule details.
 *
 * @return array|bool An array with schedule details if the event is scheduled, false otherwise.
 */
function get_openai_event_schedule() {
	$timestamp = wp_next_scheduled( 'abcc_openai_generate_post_hook' );

	if ( false === $timestamp ) {
		return false;
	}

	$schedule = wp_get_schedule( 'abcc_openai_generate_post_hook' );

	return array(
		'scheduled' => true,
		'schedule'  => $schedule,
		'next_run'  => date_i18n( 'Y-m-d H:i:s', $timestamp ),
		'timestamp' => $timestamp,
	);
}

/**
 * Generates a post on schedule using AI services.
 *
 * @return bool|int Returns post ID on success, false if conditions aren't met or on failure
 */
function abcc_openai_generate_post_scheduled() {
	try {
		// Get required parameters
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$tone          = get_option( 'openai_tone', 'default' );
		$auto_create   = get_option( 'openai_auto_create', 'none' );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );

		// Log scheduled attempt
		error_log(
			sprintf(
				'Scheduled post generation attempt - Auto Create: %s, Model: %s, Keywords count: %d',
				$auto_create,
				$prompt_select,
				count( $keywords )
			)
		);

		// Validate conditions
		if ( empty( $api_key ) ) {
			throw new Exception( 'API key not configured for scheduled post generation' );
		}

		if ( empty( $keywords ) ) {
			throw new Exception( 'No keywords configured for scheduled post generation' );
		}

		if ( 'none' === $auto_create ) {
			throw new Exception( 'Auto-create is disabled' );
		}

		// Generate the post
		$post_id = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			$auto_create,
			$char_limit
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		return $post_id;

	} catch ( Exception $e ) {
		error_log( 'Scheduled Post Generation Error: ' . $e->getMessage() );

		// Send admin notification about the failure if enabled
		if ( get_option( 'openai_email_notifications', false ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				__( 'Scheduled Post Generation Failed', 'automated-blog-content-creator' ),
				sprintf(
					__( 'The scheduled post generation failed with error: %s', 'automated-blog-content-creator' ),
					$e->getMessage()
				)
			);
		}

		return false;
	}
}

/**
 * Schedule or unschedule the event based on the selected option.
 */
function abcc_schedule_openai_event() {
	$selected_option = get_option( 'openai_auto_create', 'none' );

	// Unscheduling the event if it was scheduled previously.
	wp_clear_scheduled_hook( 'abcc_openai_generate_post_hook' );

	// Scheduling the event based on the selected option.
	if ( 'none' !== $selected_option ) {
		$schedule_interval = ( 'hourly' === $selected_option ) ? 'hourly' : ( ( 'weekly' === $selected_option ) ? 'weekly' : 'daily' );
		wp_schedule_event( time(), $schedule_interval, 'abcc_openai_generate_post_hook' );
	}
}

// Schedule or unschedule the event when the option is updated.
add_action( 'update_option_openai_auto_create', 'abcc_schedule_openai_event' );

// Trigger the OpenAI post generation.
add_action( 'abcc_openai_generate_post_hook', 'abcc_openai_generate_post_scheduled' );

/**
 * This is a PHP function that generates a post using OpenAI API and AJAX, with options for API key,
 * keywords, and character limit.
 */
function abcc_openai_generate_post_ajax() {
	check_ajax_referer( 'abcc_openai_generate_post' );

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = explode( "\n", get_option( 'openai_keywords', '' ) );
		$char_limit    = get_option( 'openai_char_limit', 200 );
		$tone          = get_option( 'openai_tone', 'default' );
		$prompt_select = get_option( 'prompt_select', 'gpt-3.5-turbo' );

		$result = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			false,
			$char_limit
		);

		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Post created successfully!', 'automated-blog-content-creator' ),
				'post_id' => $result,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
				'details' => WP_DEBUG ? $e->getTraceAsString() : null,
			)
		);
	}
}
add_action( 'wp_ajax_openai_generate_post', 'abcc_openai_generate_post_ajax' );

/**
 * This function deactivates a scheduled hook for generating posts using OpenAI in WordPress.
 */
function abcc_openai_deactivate_plugin() {
	wp_clear_scheduled_hook( 'abcc_openai_generate_post_hook' );
}
register_deactivation_hook( __FILE__, 'abcc_openai_deactivate_plugin' );

add_action( 'admin_notices', 'display_openai_settings_errors' );
function display_openai_settings_errors() {
	settings_errors( 'openai-settings' );
}


function abcc_generate_title_and_seo( $api_key, $keywords, $prompt_select, $site_info ) {
	$prompt  = 'Create a blog post title and SEO metadata for a post about: ' . implode( ', ', $keywords ) . "\n\n";
	$prompt .= 'Format the response exactly as follows:
    [TITLE]
    Your H1 title here
    [SEO]
    Meta Description: (max 160 chars)
    Primary Keyword: main keyword
    Secondary Keywords: keyword1, keyword2, keyword3
    Social Excerpt: (max 200 chars)
    [END]';

	// Use a small token limit for this call - 200 tokens should be plenty
	$result = abcc_generate_content( $api_key, $prompt, $prompt_select, 200 );

	if ( ! $result ) {
		throw new Exception( 'Failed to generate title and SEO data' );
	}

	// Parse the structured response
	$title    = '';
	$seo_data = array();
	$in_title = false;
	$in_seo   = false;

	foreach ( $result as $line ) {
		if ( strpos( $line, '[TITLE]' ) !== false ) {
			$in_title = true;
			continue;
		}
		if ( strpos( $line, '[SEO]' ) !== false ) {
			$in_seo   = true;
			$in_title = false;
			continue;
		}
		if ( strpos( $line, '[END]' ) !== false ) {
			break;
		}

		if ( $in_title ) {
			$title    = trim( $line );
			$in_title = false;
		} elseif ( $in_seo ) {
			if ( strpos( $line, 'Meta Description:' ) !== false ) {
				$seo_data['meta_description'] = trim( str_replace( 'Meta Description:', '', $line ) );
			} elseif ( strpos( $line, 'Primary Keyword:' ) !== false ) {
				$seo_data['primary_keyword'] = trim( str_replace( 'Primary Keyword:', '', $line ) );
			} elseif ( strpos( $line, 'Secondary Keywords:' ) !== false ) {
				$seo_data['secondary_keywords'] = array_map( 'trim', explode( ',', str_replace( 'Secondary Keywords:', '', $line ) ) );
			} elseif ( strpos( $line, 'Social Excerpt:' ) !== false ) {
				$seo_data['social_excerpt'] = trim( str_replace( 'Social Excerpt:', '', $line ) );
			}
		}
	}

	return array(
		'title'    => $title,
		'seo_data' => $seo_data,
	);
}


function abcc_generate_post_content( $api_key, $keywords, $prompt_select, $title, $char_limit ) {
	$prompt  = "Write a blog post with the following title: {$title}\n\n";
	$prompt .= 'Using these keywords: ' . implode( ', ', $keywords ) . "\n\n";
	$prompt .= 'Format requirements:
    - Use <h2>Heading</h2> for main sections
    - Use <h3>Heading</h3> for subsections
    - Put each paragraph in its own <p> tag
    - Do not include the title in the content
    - Put each section on a new line
    - Do not include empty lines or paragraphs
    - Ensure clean HTML without extra spaces or newlines';

	return abcc_generate_content( $api_key, $prompt, $prompt_select, $char_limit );
}
