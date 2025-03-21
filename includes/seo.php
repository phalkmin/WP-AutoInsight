<?php
/**
 * SEO integration functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
 * Generates a title and SEO metadata for a post.
 *
 * @param string $api_key API key for the selected service
 * @param array  $keywords Keywords to focus the article on
 * @param string $prompt_select Which AI service to use
 * @param array  $site_info Site information
 * @return array Title and SEO data
 */
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
		if ( false !== strpos( $line, '[TITLE]' ) ) {
			$in_title = true;
			continue;
		}
		if ( false !== strpos( $line, '[SEO]' ) ) {
			$in_seo   = true;
			$in_title = false;
			continue;
		}
		if ( false !== strpos( $line, '[END]' ) ) {
			break;
		}

		if ( $in_title ) {
			$title    = trim( $line );
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

	return array(
		'title'    => $title,
		'seo_data' => $seo_data,
	);
}
