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
 * Resolve the post status for a generated post.
 *
 * The single choke point for post-status decisions. Precedence:
 * 1. post_status  — explicit per-call override (e.g. Composer "Save as" toggle,
 *                   @since 4.3.0). Highest priority; beats force_draft.
 * 2. force_draft  — per-request flag (e.g. bulk handler's draft checkbox).
 * 3. topic_id     — per-topic override meta (Topic Library, v4.2 Unit D;
 *                   read defensively so it is inert until topics exist).
 * 4. global       — abcc_default_post_status setting.
 *
 * @since 4.2.0
 * @param array $context Optional resolution context: post_status (string, explicit
 *                       override — empty string means "no override"), force_draft (bool),
 *                       topic_id (int), source (string).
 * @return string 'draft' or 'publish'.
 */
function abcc_resolve_post_status( $context = array() ) {
	if ( ! empty( $context['post_status'] ) ) {
		return apply_filters( 'abcc_resolve_post_status', abcc_sanitize_post_status( $context['post_status'] ), $context );
	}

	if ( ! empty( $context['force_draft'] ) ) {
		return apply_filters( 'abcc_resolve_post_status', 'draft', $context );
	}

	if ( ! empty( $context['topic_id'] ) ) {
		$override = get_post_meta( (int) $context['topic_id'], '_abcc_topic_post_status_override', true );
		if ( ! empty( $override ) ) {
			return apply_filters( 'abcc_resolve_post_status', abcc_sanitize_post_status( $override ), $context );
		}
	}

	$value = abcc_sanitize_post_status( abcc_get_setting( 'abcc_default_post_status', 'draft' ) );

	return apply_filters( 'abcc_resolve_post_status', $value, $context );
}

/**
 * Builds a normalized generation payload with defaults.
 *
 * @since 3.6.0
 * @param array $args Overrides for the defaults.
 * @return array The normalized payload.
 */
function abcc_build_generation_payload( $args = array() ) {
	$defaults = array(
		'keywords'      => array(),
		'focus_keyword' => '',
		'model'         => abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' ),
		'tone'          => abcc_get_setting( 'openai_tone', 'default' ),
		'char_limit'    => (int) abcc_get_setting( 'openai_char_limit', 200 ),
		'post_type'     => 'post',
		'category'      => 0,
		'template'      => 'default',
		'source'        => 'manual', // manual, scheduled, bulk, regenerate
	);

	$payload = wp_parse_args( $args, $defaults );

	// Resolve the 'custom' tone keyword to the user's description (review #3).
	if ( 'custom' === $payload['tone'] ) {
		$custom          = trim( (string) abcc_get_setting( 'custom_tone', '' ) );
		$payload['tone'] = '' !== $custom ? $custom : 'professional';
	}

	return $payload;
}

/**
 * Resolve a Composer source token into concrete generation inputs.
 *
 * Pure resolution only — reads settings/topics, queues nothing. The empty
 * token (and any stale/deleted/keyword-less token) falls back to the first
 * keyword group that has keywords, which is the plugin's historical
 * "Generate Post Now" behavior. This keeps every existing install working
 * byte-for-byte on upgrade with no migration.
 *
 * @since 4.3.0
 * @param string $token '' | 'group:N' | 'topic:N'
 * @return array|WP_Error Resolved source array, or WP_Error 'abcc_no_source'.
 */
function abcc_resolve_composer_source( $token ) {
	$token  = abcc_sanitize_composer_source( $token );
	$groups = (array) abcc_get_setting( 'abcc_keyword_groups', array() );

	// 1. Explicit topic token.
	if ( 0 === strpos( $token, 'topic:' ) ) {
		$topic_id = (int) substr( $token, strlen( 'topic:' ) );
		$topic    = function_exists( 'abcc_get_topic' ) ? abcc_get_topic( $topic_id ) : null;

		if ( null !== $topic && '' !== trim( (string) $topic['title'] ) ) {
			return array(
				'token'    => 'topic:' . $topic_id,
				'type'     => 'topic',
				'keywords' => array( $topic['title'] ),
				'category' => 0,
				'template' => 'default',
				'topic_id' => $topic_id,
				'prompt'   => (string) $topic['prompt'],
				'label'    => $topic['title'],
			);
		}
		// Stale/deleted topic -> fall through to group fallback.
	}

	// 2. Explicit group token.
	if ( 0 === strpos( $token, 'group:' ) ) {
		$index = (int) substr( $token, strlen( 'group:' ) );
		if ( isset( $groups[ $index ] ) && ! empty( $groups[ $index ]['keywords'] ) ) {
			return abcc_build_group_source( $index, $groups[ $index ] );
		}
		// Stale/keyword-less group -> fall through.
	}

	// 3. Fallback: first group with keywords.
	foreach ( $groups as $index => $group ) {
		if ( ! empty( $group['keywords'] ) ) {
			return abcc_build_group_source( (int) $index, $group );
		}
	}

	// 4. Nothing to generate from.
	return new WP_Error(
		'abcc_no_source',
		__( 'Add a keyword group or a Topic before generating.', 'automated-blog-content-creator' )
	);
}

/**
 * Apply per-call Composer overrides to a generation payload.
 *
 * Overrides affect only the payload for THIS generation. They are never
 * written back to the global prompt_select / abcc_default_post_status —
 * "my global config" and "what I'm about to generate" stay distinct.
 *
 * @since 4.3.0
 * @param array $payload   Payload from abcc_build_generation_payload().
 * @param array $overrides Optional 'model' and/or 'post_status'.
 * @return array
 */
function abcc_apply_composer_overrides( $payload, $overrides ) {
	if ( ! empty( $overrides['model'] ) ) {
		$payload['model'] = sanitize_text_field( $overrides['model'] );
	}
	if ( ! empty( $overrides['post_status'] ) ) {
		$payload['post_status'] = abcc_sanitize_post_status( $overrides['post_status'] );
	}
	return $payload;
}

/**
 * Shape a keyword group into the resolver's return array.
 *
 * @since 4.3.0
 * @param int   $index Group index.
 * @param array $group Group data.
 * @return array
 */
function abcc_build_group_source( $index, $group ) {
	return array(
		'token'    => 'group:' . (int) $index,
		'type'     => 'group',
		'keywords' => (array) $group['keywords'],
		'category' => isset( $group['category'] ) ? (int) $group['category'] : 0,
		'template' => isset( $group['template'] ) ? $group['template'] : 'default',
		'topic_id' => 0,
		'prompt'   => '',
		'label'    => isset( $group['name'] ) ? $group['name'] : ( 'Group ' . ( (int) $index + 1 ) ),
	);
}

/**
 * Build the tracking meta written to generated posts.
 *
 * @param array $payload Generation payload.
 * @return array
 */
function abcc_build_generation_tracking_meta( $payload ) {
	return array(
		'_abcc_generated'         => '1',
		'_abcc_model'             => $payload['model'],
		'_abcc_generation_params' => wp_json_encode(
			array(
				'keywords'      => (array) $payload['keywords'],
				'focus_keyword' => isset( $payload['focus_keyword'] ) ? (string) $payload['focus_keyword'] : '',
				'model'         => $payload['model'],
				'tone'          => $payload['tone'],
				'char_limit'    => (int) $payload['char_limit'],
				'post_type'     => $payload['post_type'],
				'category'      => (int) $payload['category'],
				'template'      => $payload['template'],
				'source'        => $payload['source'],
			)
		),
	);
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
 * @param string  $post_type     The post type
 * @param array   $options       Additional options (e.g., template, category, source)
 * @return int|WP_Error Post ID on success, WP_Error on failure
 */
function abcc_openai_generate_post( $api_key, $keywords, $prompt_select, $tone = 'default', $auto_create = false, $char_limit = 200, $post_type = 'post', $options = array() ) {
	try {
		$generate_seo = abcc_get_setting( 'openai_generate_seo', true ) && 'none' !== abcc_get_active_seo_plugin();

		// Pick one keyword from the group to keep the post focused. The full
		// list remains available to templates via {keywords}.
		$focus_keyword  = abcc_pick_focus_keyword( (array) $keywords );
		$focus_keywords = '' !== $focus_keyword ? array( $focus_keyword ) : (array) $keywords;

		$payload     = abcc_build_generation_payload(
			array(
				'keywords'      => $keywords,
				'focus_keyword' => $focus_keyword,
				'model'         => $prompt_select,
				'tone'          => $tone,
				'char_limit'    => $char_limit,
				'post_type'     => $post_type,
				'category'      => isset( $options['category'] ) ? (int) $options['category'] : 0,
				'template'      => isset( $options['template'] ) ? $options['template'] : 'default',
				'source'        => isset( $options['source'] ) ? sanitize_text_field( $options['source'] ) : ( $auto_create ? 'scheduled' : 'manual' ),
			)
		);
		$category_id = (int) $payload['category'];
		$template    = $payload['template'];
		$source      = $payload['source'];

		if ( true === $generate_seo ) {
			// Generate title and SEO data.
			$title_and_seo = abcc_generate_title_and_seo(
				$api_key,
				$focus_keywords,
				$prompt_select,
				array(
					'site_name'        => get_bloginfo( 'name' ),
					'site_description' => get_bloginfo( 'description' ),
				)
			);
			$title         = $title_and_seo['title'];
			$seo_data      = $title_and_seo['seo_data'];
		} else {
			// Just generate a title.
			$title    = abcc_generate_title( $api_key, $focus_keywords, $prompt_select );
			$seo_data = array();
		}

		// Then, generate the content.
		$content_array = abcc_generate_post_content_with_template(
			$api_key,
			$focus_keywords,
			$prompt_select,
			$title,
			$char_limit,
			array(
				'template'      => $template,
				'tone'          => $tone,
				'category'      => $category_id,
				'keywords_all'  => (array) $keywords,
				// Topic Library: a topic's own prompt replaces the stored template.
				'custom_prompt' => isset( $options['prompt'] ) ? (string) $options['prompt'] : '',
			)
		);

		if ( false === $content_array || ! is_array( $content_array ) ) {
			throw new Exception( 'Content generation failed - no content returned from AI service' );
		}

		$content_array = abcc_filter_generated_content_lines( $content_array );

		// Process Perplexity citations if applicable.
		$citation_provider = abcc_get_provider_for_model( $prompt_select );
		if ( abcc_provider_supports_citations( $citation_provider ) ) {
			$generation_id = 'abcc_pplx_citations_' . get_current_user_id();
			$citations     = get_transient( $generation_id );
			if ( ! empty( $citations ) ) {
				$citation_style = abcc_get_setting( 'abcc_perplexity_citation_style', 'inline' );
				$content_array  = abcc_process_perplexity_citations( $content_array, $citations, $citation_style );
				delete_transient( $generation_id );
			}
		}

		$format_content = abcc_create_blocks( $content_array );
		$post_content   = abcc_gutenberg_blocks( $format_content );

		$post_data = array(
			'post_title'    => $title,
			'post_content'  => wp_kses_post( $post_content ),
			'post_status'   => abcc_resolve_post_status(
				array(
					'post_status' => isset( $options['post_status'] ) ? $options['post_status'] : '',
					'force_draft' => ! empty( $options['draft_only'] ),
					'topic_id'    => isset( $options['topic_id'] ) ? (int) $options['topic_id'] : 0,
					'source'      => $source,
				)
			),
			'post_author'   => ! empty( $options['post_author'] ) ? (int) $options['post_author'] : get_current_user_id(),
			'post_type'     => $post_type,
			'post_category' => $category_id ? array( $category_id ) : array( (int) get_option( 'default_category', 1 ) ),
		);

		// Add SEO data if Yoast is active.
		if ( true === $generate_seo && ! empty( $seo_data ) ) {
			$post_data['meta_input'] = abcc_get_seo_meta_fields( $seo_data );
		}

		// Ensure our tracking meta is present.
		if ( ! isset( $post_data['meta_input'] ) ) {
			$post_data['meta_input'] = array();
		}
		$post_data['meta_input'] = array_merge( $post_data['meta_input'], abcc_build_generation_tracking_meta( $payload ) );

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		if ( abcc_get_setting( 'openai_generate_images', true ) ) {
			try {
				$category_ids   = $category_id ? array( $category_id ) : array( (int) get_option( 'default_category', 1 ) );
				$category_names = array();

				if ( ! empty( $category_ids ) ) {
					foreach ( $category_ids as $cat_id ) {
						$category = get_category( $cat_id );
						if ( $category ) {
							$category_names[] = $category->name;
						}
					}
				}

				$image_url = abcc_generate_featured_image( $prompt_select, $focus_keywords, $category_names );

				if ( $image_url ) {
					$alt_text = abcc_get_setting( 'abcc_auto_alt_text', true )
						? abcc_build_featured_image_alt_text( $title, $seo_data['primary_keyword'] ?? '' )
						: '';

					abcc_set_featured_image( $post_id, $image_url, $alt_text );
				}
			} catch ( Exception $e ) {
				// Image failures should not abort successful text generation.
				unset( $e );
			}
		}

		if ( abcc_get_setting( 'openai_email_notifications', false ) ) {
			abcc_send_post_notification( $post_id );
		}

		return $post_id;

	} catch ( Exception $e ) {
		return new WP_Error( 'post_generation_failed', $e->getMessage() );
	}
}


/**
 * Filters generated content lines, dropping title/SEO markers and blanks.
 *
 * @since 4.2.0
 * @param array $content_array Raw content lines from the AI service.
 * @return array Filtered content lines.
 * @throws Exception When no content lines survive filtering.
 */
function abcc_filter_generated_content_lines( array $content_array ) {
	$content_array = array_filter(
		array_map( 'trim', $content_array ),
		static function ( $line ) {
			return '' !== $line
				&& false === strpos( $line, '<title>' )
				&& false === strpos( $line, '[SEO]' );
		}
	);

	if ( empty( $content_array ) ) {
		throw new Exception( 'Content generation failed' );
	}

	return array_values( $content_array );
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

	$provider = abcc_get_model_provider( $service );
	$callback = abcc_get_provider_text_generation_callback( $provider );

	if ( empty( $callback ) || ! is_callable( $callback ) ) {
		return $result;
	}

	$response = call_user_func( $callback, $api_key, $prompt, $char_limit, $service );

	if ( abcc_provider_supports_citations( $provider ) ) {
		$perplexity_result = $response;
		if ( false !== $perplexity_result && ! empty( $perplexity_result['text'] ) ) {
			// Store citations in a transient for downstream use.
			$generation_id = 'abcc_pplx_citations_' . get_current_user_id();
			set_transient( $generation_id, $perplexity_result['citations'], 300 );
			$result = $perplexity_result['text'];
		}
	} else {
		$result = $response;
	}

	return $result;
}

/**
 * Clean one model-emitted line into plain title text.
 *
 * Strips HTML, list markers ("1. ", "2)", "-", "*", "•"), markdown
 * bold/italic, leading heading markers, and wrapping quotes.
 *
 * @since 4.3.0
 * @param string $raw Raw response line.
 * @return string
 */
function abcc_clean_title_line( $raw ) {
	$title = wp_strip_all_tags( (string) $raw );
	$title = trim( $title );
	// List markers models use when returning "options": numbering or bullets.
	$title = preg_replace( '/^\s*(?:\d+[.)]\s*|[-*•]\s+)/u', '', $title );
	// Markdown bold/italic markers.
	$title = preg_replace( '/\*{1,3}(.+?)\*{1,3}/', '$1', $title );
	// Leading heading markers (e.g. "## ", "# ").
	$title = ltrim( $title, '# ' );
	// Wrapping quotes.
	$title = trim( trim( $title ), '"\'`' );

	return trim( $title );
}

/**
 * Pick one usable title out of a raw model response.
 *
 * Chatty models answer the title prompt with a preamble ("Here are some
 * catchy blog post titles...:") followed by a list of options; taking the
 * first line blindly turns that preamble into the post title. Preamble
 * lines end with a colon, real titles don't — return the first cleaned
 * line that doesn't. When every line looks like preamble, fall back to the
 * first line minus its trailing colon.
 *
 * @since 4.3.0
 * @param array $lines Response lines.
 * @return string Clean title, or '' when the response has no usable text.
 */
function abcc_extract_generated_title( $lines ) {
	$candidates = array();

	foreach ( (array) $lines as $line ) {
		$line = abcc_clean_title_line( $line );
		if ( '' !== $line ) {
			$candidates[] = $line;
		}
	}

	if ( empty( $candidates ) ) {
		return '';
	}

	foreach ( $candidates as $candidate ) {
		if ( ':' !== substr( $candidate, -1 ) ) {
			return $candidate;
		}
	}

	return rtrim( $candidates[0], ': ' );
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
	$prompt  = 'Create a catchy blog post title about: ' . implode( ', ', $keywords ) . '. ';
	$prompt .= 'Respond with only the title itself on a single line — no introduction, no list of alternatives, no numbering, and no quotation marks.';

	// Use a small token limit for this call - 50 tokens should be plenty for a title
	$result = abcc_generate_content( $api_key, $prompt, $prompt_select, 50 );

	if ( false === $result || empty( $result ) ) {
		throw new Exception( 'Failed to generate title' );
	}

	$title = abcc_extract_generated_title( (array) $result );

	if ( '' === $title ) {
		throw new Exception( 'Failed to generate title' );
	}

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
    - Ensure clean HTML without extra spaces or newlines
    - Always close every HTML tag before ending your response';

	// Perplexity needs a minimum token floor to complete a structured HTML post without truncation.
	if ( 0 === strpos( $prompt_select, 'sonar' ) ) {
		$char_limit = max( $char_limit, 800 );
	}

	return abcc_generate_content( $api_key, $prompt, $prompt_select, $char_limit );
}

/**
 * Generates post content using a specific template.
 *
 * @param string $api_key       API key.
 * @param array  $keywords      Keywords.
 * @param string $prompt_select Model.
 * @param string $title         Title.
 * @param int    $char_limit    Limit.
 * @param array  $args          Template and other args.
 * @return array|false
 */
function abcc_generate_post_content_with_template( $api_key, $keywords, $prompt_select, $title, $char_limit, $args = array() ) {
	// Topic Library: a topic's own prompt takes the template's place. It goes
	// through the same placeholder substitution, so {keyword}/{title}/... work.
	if ( ! empty( $args['custom_prompt'] ) ) {
		$args['char_limit'] = $char_limit;
		$prompt             = abcc_expand_content_prompt( (string) $args['custom_prompt'], $title, $keywords, $args );

		if ( 0 === strpos( $prompt_select, 'sonar' ) ) {
			$char_limit = max( $char_limit, 800 );
		}

		return abcc_generate_content( $api_key, $prompt, $prompt_select, $char_limit );
	}

	$template_slug = $args['template'] ?? 'default';
	$templates     = abcc_get_setting( 'abcc_content_templates', array() );
	$template      = $templates[ $template_slug ] ?? ( $templates['default'] ?? array() );

	if ( empty( $template ) ) {
		// Fallback to legacy style if no templates exist.
		return abcc_generate_post_content( $api_key, $keywords, $prompt_select, $title, $char_limit );
	}

	$args['char_limit'] = $char_limit;
	$prompt             = abcc_build_content_template_prompt( $template_slug, $title, $keywords, $args );

	// Perplexity needs a minimum token floor to complete a structured HTML post without truncation.
	if ( 0 === strpos( $prompt_select, 'sonar' ) ) {
		$char_limit = max( $char_limit, 800 );
	}

	return abcc_generate_content( $api_key, $prompt, $prompt_select, $char_limit );
}

/**
 * Builds the full content-generation prompt for a given template.
 *
 * Pure helper extracted from abcc_generate_post_content_with_template so the
 * substitution logic can be unit-tested without hitting an AI provider.
 *
 * @since 4.2.0
 * @param string $template_slug Template slug to look up in abcc_content_templates.
 * @param string $title         Post title.
 * @param array  $keywords      Keywords passed to the prompt. Typically a single-element
 *                              array containing the focus keyword chosen by
 *                              abcc_pick_focus_keyword().
 * @param array  $args          Additional substitution data:
 *                              - tone          string  Tone keyword.
 *                              - category      int     Category ID (0 = General).
 *                              - char_limit    int     Char/token limit (drives {word_count}).
 *                              - keywords_all  array   Full keyword group, used for {keywords}.
 *                                                      Falls back to $keywords if absent.
 * @return string The fully expanded prompt with format requirements appended.
 */
function abcc_build_content_template_prompt( $template_slug, $title, $keywords, $args = array() ) {
	$templates = abcc_get_setting( 'abcc_content_templates', array() );
	$template  = $templates[ $template_slug ] ?? ( $templates['default'] ?? array() );

	if ( empty( $template ) || empty( $template['prompt'] ) ) {
		return '';
	}

	return abcc_expand_content_prompt( $template['prompt'], $title, $keywords, $args );
}

/**
 * Expand placeholders in a content prompt and append format requirements.
 *
 * Substitution core shared by stored templates and Topic Library prompts.
 *
 * @since 4.2.0
 * @param string $raw_prompt Prompt text with {placeholder} tokens.
 * @param string $title      Post title.
 * @param array  $keywords   Keywords (index 0 is the focus keyword).
 * @param array  $args       Substitution data: tone, category, char_limit, keywords_all.
 * @return string
 */
function abcc_expand_content_prompt( $raw_prompt, $title, $keywords, $args = array() ) {
	$category_id   = isset( $args['category'] ) ? (int) $args['category'] : 0;
	$category_name = $category_id ? get_cat_name( $category_id ) : 'General';
	$char_limit    = isset( $args['char_limit'] ) ? (int) $args['char_limit'] : 200;
	$focus_keyword = isset( $keywords[0] ) ? (string) $keywords[0] : '';
	$keywords_all  = ! empty( $args['keywords_all'] ) ? (array) $args['keywords_all'] : (array) $keywords;

	$replacements = array(
		'{keyword}'    => $focus_keyword,
		'{keywords}'   => implode( ', ', $keywords_all ),
		'{title}'      => $title,
		'{tone}'       => $args['tone'] ?? 'professional',
		'{site_name}'  => get_bloginfo( 'name' ),
		'{category}'   => $category_name,
		'{word_count}' => round( $char_limit * 0.75 ),
	);

	$prompt = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw_prompt );

	return $prompt . ABCC_CONTENT_FORMAT_REQUIREMENTS;
}

/**
 * Picks one keyword from a keyword group at random.
 *
 * Keyword groups are pools of related topics (one per line in the UI). To keep
 * generated articles focused, a single keyword is drawn for each post; the
 * full list remains available for templates that explicitly want it via
 * {keywords}.
 *
 * @since 4.2.0
 * @param array $keywords Keyword group entries.
 * @return string The picked keyword, or '' when no usable entry exists.
 */
function abcc_pick_focus_keyword( $keywords ) {
	if ( ! is_array( $keywords ) ) {
		return '';
	}

	$candidates = array_values(
		array_filter(
			array_map(
				static function ( $keyword ) {
					return is_string( $keyword ) ? trim( $keyword ) : '';
				},
				$keywords
			),
			static function ( $keyword ) {
				return '' !== $keyword;
			}
		)
	);

	if ( empty( $candidates ) ) {
		return '';
	}

	return $candidates[ array_rand( $candidates ) ];
}

/**
 * Processes Perplexity citations and integrates them into the content array.
 *
 * @since 3.3.0
 * @param array  $content_array Array of content lines.
 * @param array  $citations     Array of citation URLs from Perplexity.
 * @param string $style         Citation style: 'inline', 'references', or 'both'.
 * @return array Modified content array with citations applied.
 */
function abcc_process_perplexity_citations( $content_array, $citations, $style ) {
	if ( empty( $citations ) ) {
		return $content_array;
	}

	$has_inline     = in_array( $style, array( 'inline', 'both' ), true );
	$has_references = in_array( $style, array( 'references', 'both' ), true );

	// Process inline citations: replace [1], [2] etc. with superscript links.
	if ( $has_inline ) {
		$content_array = array_map(
			function ( $line ) use ( $citations ) {
				return preg_replace_callback(
					'/\[(\d+)\]/',
					function ( $matches ) use ( $citations ) {
						$num   = (int) $matches[1];
						$index = $num - 1;
						if ( isset( $citations[ $index ] ) ) {
							return sprintf(
								'<sup><a href="%s" target="_blank" rel="noopener noreferrer">[%d]</a></sup>',
								esc_url( $citations[ $index ] ),
								$num
							);
						}
						return $matches[0];
					},
					$line
				);
			},
			$content_array
		);
	} elseif ( 'references' === $style ) {
		// Strip [N] markers when only showing references section.
		$content_array = array_map(
			function ( $line ) {
				return preg_replace( '/\[\d+\]/', '', $line );
			},
			$content_array
		);
	}

	// Add references section at the bottom.
	if ( $has_references ) {
		$content_array[] = '<h2>' . __( 'Sources', 'automated-blog-content-creator' ) . '</h2>';
		foreach ( $citations as $index => $url ) {
			$number          = $index + 1;
			$display_domain  = wp_parse_url( $url, PHP_URL_HOST );
			$content_array[] = '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">[' . $number . '] ' . esc_html( $display_domain ) . '</a></p>';
		}
	}

	return $content_array;
}
