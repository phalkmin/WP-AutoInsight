<?php
/**
 * Block creation and formatting functions
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The function abcc_create_block creates a WordPress block with specified attributes and content.
 *
 * @param string $block_name The block name parameter specifies the name of the block to create.
 * @param array  $attributes The attributes parameter contains block attributes.
 * @param string $content The content parameter contains the block content.
 *
 * @return string The function returns a block of content in the format used by the WordPress block editor.
 */
function abcc_create_block( $block_name, $attributes = array(), $content = '' ) {
	$content = trim( $content );

	if ( 'heading' === $block_name ) {
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

	if ( 'paragraph' === $block_name ) {
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
 * @param array $text_array The array of text items as input.
 *
 * @return array The function returns an array of blocks.
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
 * @param array $blocks The array of blocks to process.
 *
 * @return string The function returns the concatenated block contents.
 */
function abcc_gutenberg_blocks( $blocks = array() ) {
	$block_contents = '';
	foreach ( $blocks as $block ) {
		$block_contents .= abcc_create_block( $block['name'], $block['attributes'], $block['content'] );
	}
	return $block_contents;
}
