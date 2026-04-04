<?php
/**
 * SEO integration functions.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Checks which SEO plugin is active and returns its identifier.
 *
 * @return string Identifier of the active SEO plugin or 'none'.
 */
function abcc_get_active_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
		return 'yoast';
	}

	if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
		return 'rankmath';
	}

	// Fallback for cases where constants might not be defined yet (like early in the load process).
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
		return 'yoast';
	}

	if ( is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
		return 'rankmath';
	}

	return 'none';
}

/**
 * Gets the appropriate meta fields based on the active SEO plugin.
 *
 * @param array $seo_data Array containing SEO metadata.
 * @return array Meta input array for wp_insert_post.
 */
function abcc_get_seo_meta_fields( $seo_data ) {
	$active_seo_plugin = abcc_get_active_seo_plugin();
	$meta_input        = array(
		'_abcc_social_excerpt' => $seo_data['social_excerpt'],
	);

	switch ( $active_seo_plugin ) {
		case 'yoast':
			$meta_input = array_merge(
				$meta_input,
				array(
					'_yoast_wpseo_metadesc'              => $seo_data['meta_description'],
					'_yoast_wpseo_focuskw'               => $seo_data['primary_keyword'],
					'_yoast_wpseo_metakeywords'          => implode( ',', (array) $seo_data['secondary_keywords'] ),
					'_yoast_wpseo_opengraph-description' => $seo_data['social_excerpt'],
				)
			);
			break;

		case 'rankmath':
			// RankMath supports multiple focus keywords separated by commas.
			$focus_keywords = array_merge( array( $seo_data['primary_keyword'] ), (array) $seo_data['secondary_keywords'] );
			$focus_keywords = array_unique( array_filter( array_map( 'trim', $focus_keywords ) ) );

			$meta_input = array_merge(
				$meta_input,
				array(
					'rank_math_description'    => $seo_data['meta_description'],
					'rank_math_focus_keyword'  => implode( ',', $focus_keywords ),
					'rank_math_og_description' => $seo_data['social_excerpt'],
				)
			);
			break;
	}

	return $meta_input;
}

/**
 * Generates a title and SEO metadata for a post.
 *
 * @param string $api_key API key for the selected service.
 * @param array  $keywords Keywords to focus the article on.
 * @param string $prompt_select Which AI service to use.
 * @param array  $site_info Site information.
 * @return array Title and SEO data.
 */
function abcc_generate_title_and_seo( $api_key, $keywords, $prompt_select, $site_info ) {
	$site_context = '';
	if ( ! empty( $site_info['site_name'] ) ) {
		$site_context = "This post is for a site called '{$site_info['site_name']}'";
		if ( ! empty( $site_info['site_description'] ) ) {
			$site_context .= " - {$site_info['site_description']}";
		}
		$site_context .= ". Ensure the title and meta reflect this context.\n\n";
	}

	$prompt  = $site_context;
	$prompt .= 'Create a blog post title and SEO metadata for a post about: ' . implode( ', ', $keywords ) . "\n\n";
	$prompt .= 'Respond ONLY with a valid JSON object using this exact schema:
    {
        "title": "string",
        "meta_description": "string (max 160 chars)",
        "primary_keyword": "string",
        "secondary_keywords": ["string", "string", "string"],
        "social_excerpt": "string (max 200 chars)"
    }';

	// Use a small token limit for this call - 300 tokens should be plenty for JSON.
	$result = abcc_generate_content( $api_key, $prompt, $prompt_select, 300 );
	if ( false === $result ) {
		throw new Exception( 'Failed to generate title and SEO data' );
	}

	return abcc_extract_title_and_seo_from_response( $result, $keywords, $api_key, $prompt_select );
}

/**
 * Extract title and SEO data from a model response.
 *
 * @param array|string $result        Response lines or raw string.
 * @param array        $keywords      Keywords.
 * @param string       $api_key       API key used for fallback title generation.
 * @param string       $prompt_select Model identifier.
 * @return array
 */
function abcc_extract_title_and_seo_from_response( $result, $keywords, $api_key, $prompt_select ) {

	// Join lines if result is an array.
	$raw_response = is_array( $result ) ? implode( "\n", $result ) : $result;

	// Attempt to find JSON in the response (sometimes models wrap it in markdown blocks).
	if ( preg_match( '/\{.*\}/s', $raw_response, $matches ) ) {
		$json_data = json_decode( $matches[0], true );
	} else {
		$json_data = json_decode( $raw_response, true );
	}

	$title    = '';
	$seo_data = array();

	if ( $json_data && is_array( $json_data ) ) {
		$title                          = $json_data['title'] ?? '';
		$seo_data['meta_description']   = $json_data['meta_description'] ?? '';
		$seo_data['primary_keyword']    = $json_data['primary_keyword'] ?? '';
		$seo_data['secondary_keywords'] = $json_data['secondary_keywords'] ?? array();
		$seo_data['social_excerpt']     = $json_data['social_excerpt'] ?? '';
	} else {
		// Fallback to the old bracket-based parsing if JSON fails (for older models or unexpected output).
		error_log( 'WP-AutoInsight: JSON SEO parsing failed, attempting legacy bracket parsing fallback.' );

		$in_title = false;
		$in_seo   = false;
		$lines    = is_array( $result ) ? $result : explode( "\n", $result );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			if ( false !== strpos( $line, '[TITLE]' ) ) {
				$in_title = true;
				continue;
			}
			if ( false !== strpos( $line, '[SEO]' ) ) {
				$in_seo   = true;
				$in_title = false;
				continue;
			}

			if ( $in_title && empty( $title ) ) {
				$title    = preg_replace( '/\*{1,3}(.+?)\*{1,3}/', '$1', $line );
				$title    = ltrim( $title, '# ' );
				$in_title = false;
			} elseif ( $in_seo ) {
				if ( false !== strpos( $line, 'Meta Description:' ) ) {
					$seo_data['meta_description'] = trim( str_replace( 'Meta Description:', '', $line ) );
				} elseif ( false !== strpos( $line, 'Primary Keyword:' ) ) {
					$seo_data['primary_keyword'] = trim( str_replace( 'Primary Keyword:', '', $line ) );
				} elseif ( false !== strpos( $line, 'Secondary Keywords:' ) ) {
					$seo_data['secondary_keywords'] = array_map( 'trim', explode( ',', str_replace( 'Secondary Keywords:', '', $line ) ) );
				} elseif ( false !== strpos( $line, 'Social Excerpt:' ) ) {
					$seo_data['social_excerpt'] = trim( str_replace( 'Social Excerpt:', '', $line ) );
				}
			}
		}
	}

	// Final Fallbacks.
	if ( empty( $title ) ) {
		$title = abcc_generate_title( $api_key, $keywords, $prompt_select );
	}

	if ( empty( $seo_data['meta_description'] ) ) {
		$seo_data['meta_description'] = wp_trim_words( 'Learn about ' . implode( ', ', $keywords ), 20 );
	}
	if ( empty( $seo_data['primary_keyword'] ) ) {
		$seo_data['primary_keyword'] = $keywords[0] ?? 'blog post';
	}
	if ( empty( $seo_data['secondary_keywords'] ) ) {
		$seo_data['secondary_keywords'] = array_slice( $keywords, 1, 3 );
	}
	if ( empty( $seo_data['social_excerpt'] ) ) {
		$seo_data['social_excerpt'] = wp_trim_words( $title . ' - ' . implode( ', ', $keywords ), 25 );
	}

	return array(
		'title'    => sanitize_text_field( $title ),
		'seo_data' => array(
			'meta_description'   => sanitize_text_field( $seo_data['meta_description'] ),
			'primary_keyword'    => sanitize_text_field( $seo_data['primary_keyword'] ),
			'secondary_keywords' => array_map( 'sanitize_text_field', (array) $seo_data['secondary_keywords'] ),
			'social_excerpt'     => sanitize_text_field( $seo_data['social_excerpt'] ),
		),
	);
}
