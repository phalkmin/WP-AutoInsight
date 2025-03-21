<?php
/**
 * Content generation functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
		$generate_seo = get_option( 'openai_generate_seo', true ) && 'none' !== abcc_get_active_seo_plugin();

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
				return ! strpos( $line, '<title>' ) && '' !== trim( $line );
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
				$category_ids   = get_option( 'openai_selected_categories', array() );
				$category_names = array();

				if ( ! empty( $category_ids ) ) {
					foreach ( $category_ids as $cat_id ) {
						$category = get_category( $cat_id );
						if ( $category ) {
							$category_names[] = $category->name;
						}
					}
				}

				$image_url = abcc_generate_featured_image( $prompt_select, $keywords, $category_names );
				if ( $image_url ) {
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

	$prompt_parts[] = isset( $tone_instructions[ $tone ] ) ? $tone_instructions[ $tone ] : $tone_instructions['default'];

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

	if ( 'none' !== abcc_get_active_seo_plugin() ) {
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

	// OpenAI models
	if ( 0 === strpos( $service, 'gpt-' ) ) {
		$result = abcc_openai_generate_text( $api_key, $prompt, $char_limit, $service );
	} elseif ( 0 === strpos( $service, 'claude' ) ) {
		$result = abcc_claude_generate_text( $api_key, $prompt, $char_limit, $service );
	} elseif ( 'gemini-pro' === $service ) {
		$result = abcc_gemini_generate_text( $api_key, $prompt, $char_limit );
	}

	if ( false === $result ) {
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
 * Generates a title for a post.
 *
 * @param string $api_key API key for the selected service
 * @param array  $keywords Keywords to focus the title on
 * @param string $prompt_select Which AI service to use
 * @return string The generated title
 */
function abcc_generate_title( $api_key, $keywords, $prompt_select ) {
	$prompt = 'Create a catchy blog post title about: ' . implode( ', ', $keywords );

	// Use a small token limit for this call - 50 tokens should be plenty for a title
	$result = abcc_generate_content( $api_key, $prompt, $prompt_select, 50 );

	if ( ! $result || empty( $result ) ) {
		throw new Exception( 'Failed to generate title' );
	}

	// Take the first line as the title
	$title = trim( $result[0] );

	// Remove any quotes that might be around the title
	$title = trim( $title, '"\'`' );

	return $title;
}

/**
 * Generates post content.
 *
 * @param string $api_key API key for the selected service
 * @param array  $keywords Keywords to focus the content on
 * @param string $prompt_select Which AI service to use
 * @param string $title The post title
 * @param int    $char_limit Maximum token limit
 * @return array Array of content lines
 */
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
