<?php
/**
 * Block creation and formatting functions.
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
	$content = 'code' === $block_name ? trim( (string) $content, "\r\n" ) : trim( (string) $content );

	if ( 'heading' === $block_name ) {
		// A model that emits <h7> or a malformed level would otherwise produce
		// invalid HTML that Gutenberg silently drops.
		$level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 2;
		$level = max( 1, min( 6, $level ) );
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
			wp_kses_post( $content )
		);
	}

	if ( 'list' === $block_name ) {
		$ordered = ! empty( $attributes['ordered'] );
		$tag     = $ordered ? 'ol' : 'ul';
		// $content is list-item block markup built by abcc_html_to_blocks(); items were kses'd there.
		return sprintf(
			'<!-- wp:list%1$s --><%2$s class="wp-block-list">%3$s</%2$s><!-- /wp:list -->',
			$ordered ? ' {"ordered":true}' : '',
			$tag,
			$content
		);
	}

	if ( 'code' === $block_name ) {
		// Brackets are encoded so sample shortcodes print instead of running.
		return sprintf( '<!-- wp:code --><pre class="wp-block-code"><code>%s</code></pre><!-- /wp:code -->', str_replace( '[', '&#91;', esc_html( $content ) ) );
	}

	if ( 'quote' === $block_name ) {
		// $content is inner block markup built by abcc_html_to_blocks().
		return sprintf( '<!-- wp:quote --><blockquote class="wp-block-quote">%s</blockquote><!-- /wp:quote -->', $content );
	}

	if ( 'image' === $block_name ) {
		$src = isset( $attributes['src'] ) ? esc_url( $attributes['src'] ) : '';
		$alt = isset( $attributes['alt'] ) ? esc_attr( $attributes['alt'] ) : '';
		if ( '' === $src ) {
			return '';
		}
		// $content is the optional caption.
		$caption = '' !== $content ? '<figcaption class="wp-element-caption">' . wp_kses_post( $content ) . '</figcaption>' : '';
		return sprintf( '<!-- wp:image --><figure class="wp-block-image"><img src="%s" alt="%s"/>%s</figure><!-- /wp:image -->', $src, $alt, $caption );
	}

	if ( 'separator' === $block_name ) {
		return '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
	}

	if ( 'html' === $block_name ) {
		return sprintf( '<!-- wp:html -->%s<!-- /wp:html -->', wp_kses_post( $content ) );
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
 * @since 4.5.2 Fallback when DOMDocument is unavailable.
 *
 * @param array $text_array The array of text items as input.
 *
 * @return array The function returns an array of blocks.
 */
function abcc_create_blocks_by_line( $text_array ) {
	$blocks = array();
	foreach ( $text_array as $item ) {
		$item = trim( $item );
		if ( empty( $item ) ) {
			continue;
		}

		// Handle headings.
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

		// Handle regular paragraphs (allow anchor and superscript tags for citations).
		$blocks[] = array(
			'name'       => 'paragraph',
			'attributes' => array(),
			'content'    => wp_kses(
				$item,
				array(
					'a'   => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'sup' => array(),
				)
			),
		);
	}
	return $blocks;
}

/**
 * Inline tags kept inside paragraphs and list items.
 *
 * @since 4.5.2
 * @return array wp_kses allowlist.
 */
function abcc_get_inline_block_allowlist() {
	return array(
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
			'title'  => array(),
		),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'code'   => array(),
		'sup'    => array(),
		'sub'    => array(),
		'br'     => array(),
	);
}

/**
 * Inner HTML of a DOM node.
 *
 * @since 4.5.2
 * @param DOMNode     $node Node.
 * @param DOMDocument $doc  Owner document.
 * @return string
 */
function abcc_dom_inner_html( $node, $doc ) {
	$html = '';
	foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$html .= $doc->saveHTML( $child );
	}
	return $html;
}

/**
 * How the walker treats a DOM node: 'inline' joins the current paragraph run,
 * 'skip' is dropped, 'block' ends the run and becomes its own block(s).
 *
 * @since 4.5.2
 * @param DOMNode $node Node.
 * @return string 'inline', 'skip' or 'block'.
 */
function abcc_dom_node_role( $node ) {
	if ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return 'inline';
	}
	if ( XML_ELEMENT_NODE !== $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return 'skip';
	}

	$tag = strtolower( $node->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	if ( in_array( $tag, array( 'script', 'style', 'title', 'head', 'meta', 'link', 'noscript', 'template' ), true ) ) {
		return 'skip';
	}
	if ( preg_match( '/^h[1-6]$/', $tag ) || in_array( $tag, abcc_get_block_container_tags(), true ) ) {
		return 'block';
	}
	if ( in_array( $tag, array( 'p', 'ul', 'ol', 'pre', 'blockquote', 'img', 'figure', 'hr', 'table' ), true ) ) {
		return 'block';
	}
	return 'inline';
}

/**
 * Wrapper elements whose children are walked as if they sat at the root.
 *
 * @since 4.5.2
 * @return string[]
 */
function abcc_get_block_container_tags() {
	return array( 'div', 'section', 'article', 'main', 'body', 'html', 'header', 'footer', 'aside', 'nav', 'details', 'summary', 'dl', 'dt', 'dd', 'li', 'figcaption' );
}

/**
 * Append the block(s) a block-level DOM node represents.
 *
 * @since 4.5.2
 * @param DOMNode     $node   Node.
 * @param array       $blocks Accumulator.
 * @param DOMDocument $doc    Owner document.
 * @return void
 */
function abcc_blocks_from_dom_node( $node, array &$blocks, $doc ) {
	$role = abcc_dom_node_role( $node );
	if ( 'skip' === $role ) {
		return;
	}
	if ( 'inline' === $role ) {
		$run = abcc_dom_inline_html( $node, $doc );
		abcc_flush_inline_run( $run, $blocks );
		return;
	}

	$tag = strtolower( $node->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
		$text = trim( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		// The post title is the page's H1; the body runs h2-h4, as on the rewrite input.
		if ( '' !== $text ) {
			$blocks[] = array(
				'name'       => 'heading',
				'attributes' => array(
					'level'     => min( 4, max( 2, (int) $m[1] ) ),
					'className' => 'wp-block-heading',
				),
				'content'    => $text,
			);
		}
		return;
	}

	if ( in_array( $tag, abcc_get_block_container_tags(), true ) ) {
		abcc_blocks_from_dom_children( $node, $blocks, $doc );
		return;
	}

	switch ( $tag ) {
		case 'p':
			$content = wp_kses( abcc_dom_inner_html( $node, $doc ), abcc_get_inline_block_allowlist() );
			if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
				$blocks[] = array(
					'name'       => 'paragraph',
					'attributes' => array(),
					'content'    => $content,
				);
			}
			return;

		case 'ul':
		case 'ol':
			$items = abcc_dom_list_items( $node, $doc );
			if ( '' !== $items ) {
				$blocks[] = array(
					'name'       => 'list',
					'attributes' => array( 'ordered' => 'ol' === $tag ),
					'content'    => $items,
				);
			}
			return;

		case 'pre':
			// One code child carries the sample; several are siblings of one sample.
			$code = $node->getElementsByTagName( 'code' );
			$text = 1 === $code->length ? $code->item( 0 )->textContent : $node->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( '' !== trim( $text ) ) {
				$blocks[] = array(
					'name'       => 'code',
					'attributes' => array(),
					'content'    => trim( $text, "\r\n" ),
				);
			}
			return;

		case 'blockquote':
			$inner_blocks = array();
			abcc_blocks_from_dom_children( $node, $inner_blocks, $doc );
			$content = abcc_gutenberg_blocks( $inner_blocks );
			if ( '' !== $content ) {
				$blocks[] = array(
					'name'       => 'quote',
					'attributes' => array(),
					'content'    => $content,
				);
			}
			return;

		case 'img':
			$src = (string) $node->getAttribute( 'src' );
			if ( preg_match( '#^(https?:)?//|^/#', $src ) ) {
				$blocks[] = array(
					'name'       => 'image',
					'attributes' => array(
						'src' => $src,
						'alt' => (string) $node->getAttribute( 'alt' ),
					),
					'content'    => '',
				);
			}
			return;

		case 'figure':
			$img = $node->getElementsByTagName( 'img' );
			if ( ! $img->length ) {
				// A figure without an image (table, quote, embed) is only a wrapper.
				abcc_blocks_from_dom_children( $node, $blocks, $doc );
				return;
			}
			$count = count( $blocks );
			abcc_blocks_from_dom_node( $img->item( 0 ), $blocks, $doc );
			$caption = $node->getElementsByTagName( 'figcaption' );
			if ( count( $blocks ) > $count && $caption->length ) {
				// The image block carries its caption as content.
				$blocks[ $count ]['content'] = trim( wp_kses( abcc_dom_inner_html( $caption->item( 0 ), $doc ), abcc_get_inline_block_allowlist() ) );
			}
			return;

		case 'hr':
			$blocks[] = array(
				'name'       => 'separator',
				'attributes' => array(),
				'content'    => '',
			);
			return;

		case 'table':
			$blocks[] = array(
				'name'       => 'html',
				'attributes' => array(),
				'content'    => $doc->saveHTML( $node ),
			);
			return;
	}
}

/**
 * List-item block markup for a ul/ol; nested lists become nested list blocks.
 *
 * @since 4.5.2
 * @param DOMNode     $list_node List element.
 * @param DOMDocument $doc  Owner document.
 * @return string
 */
function abcc_dom_list_items( $list_node, $doc ) {
	$items = '';
	foreach ( $list_node->childNodes as $li ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			continue;
		}
		$text   = '';
		$nested = array();
		foreach ( $li->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$child_tag = XML_ELEMENT_NODE === $child->nodeType ? strtolower( $child->nodeName ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( 'ul' === $child_tag || 'ol' === $child_tag ) {
				abcc_blocks_from_dom_node( $child, $nested, $doc );
				continue;
			}
			$text .= $doc->saveHTML( $child );
		}
		$item = trim( wp_kses( $text, abcc_get_inline_block_allowlist() ) );
		if ( '' === trim( wp_strip_all_tags( $item ) ) ) {
			$item = '';
		}
		$item .= abcc_gutenberg_blocks( $nested );
		if ( '' !== $item ) {
			$items .= '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->';
		}
	}
	return $items;
}

/**
 * HTML for an inline node as it joins a paragraph run.
 *
 * @since 4.5.2
 * @param DOMNode     $node Inline node.
 * @param DOMDocument $doc  Owner document.
 * @return string
 */
function abcc_dom_inline_html( $node, $doc ) {
	if ( XML_ELEMENT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return $doc->saveHTML( $node );
	}
	return esc_html( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
}

/**
 * Close a run of inline HTML as one paragraph block when it has text.
 *
 * @since 4.5.2
 * @param string $run    Inline HTML; reset to '' after the flush.
 * @param array  $blocks Accumulator.
 * @return void
 */
function abcc_flush_inline_run( &$run, array &$blocks ) {
	$content = trim( wp_kses( $run, abcc_get_inline_block_allowlist() ) );
	$run     = '';
	if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
		$blocks[] = array(
			'name'       => 'paragraph',
			'attributes' => array(),
			'content'    => $content,
		);
	}
}

/**
 * Append the blocks for each child of a DOM node. Consecutive text and inline
 * elements form one paragraph; a newline in text or a block element ends it.
 *
 * @since 4.5.2
 * @param DOMNode     $node   Parent node.
 * @param array       $blocks Accumulator.
 * @param DOMDocument $doc    Owner document.
 * @return void
 */
function abcc_blocks_from_dom_children( $node, array &$blocks, $doc ) {
	$run = '';
	foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$role = abcc_dom_node_role( $child );
		if ( 'skip' === $role ) {
			continue;
		}
		if ( 'block' === $role ) {
			abcc_flush_inline_run( $run, $blocks );
			abcc_blocks_from_dom_node( $child, $blocks, $doc );
			continue;
		}
		if ( XML_ELEMENT_NODE === $child->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$run .= abcc_dom_inline_html( $child, $doc );
			continue;
		}
		$segments = preg_split( '/\r?\n/', $child->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$last     = count( $segments ) - 1;
		foreach ( $segments as $i => $segment ) {
			$run .= esc_html( $segment );
			if ( $i < $last ) {
				abcc_flush_inline_run( $run, $blocks );
			}
		}
	}
	abcc_flush_inline_run( $run, $blocks );
}

/**
 * Convert generated HTML into block arrays by walking the DOM.
 *
 * @since 4.5.2
 * @param string $html Generated post HTML (may span lines).
 * @return array Blocks for abcc_gutenberg_blocks(); empty when nothing usable.
 */
function abcc_html_to_blocks( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return array();
	}

	// Inline samples hold raw tags too (<code><pre></code>) and run before the block pass,
	// or a mention of pre would pair with a real block's closer. A span stops at a
	// paragraph end or a newline so an unclosed code tag cannot swallow the next paragraph.
	$html = preg_replace_callback(
		'#(<code\b[^>]*>)((?:(?!</p>|\n).)*?)(</code>)#is',
		function ( $m ) {
			return $m[1] . str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $m[2] ) . $m[3];
		},
		$html
	);

	// Code samples hold raw comparisons (i<n) that would open tags; only code tags stay markup.
	$html = preg_replace_callback(
		'#(<pre\b[^>]*>)(.*?)(</pre>)#is',
		function ( $m ) {
			// Raw code tags are parked behind sentinels; source entities are never touched.
			$tags  = array();
			$inner = preg_replace_callback(
				'#</?code\b[^>]*>#i',
				function ( $tag ) use ( &$tags ) {
					$key          = "\x00ABCC_CODE_" . count( $tags ) . "\x00";
					$tags[ $key ] = $tag[0];
					return $key;
				},
				$m[2]
			);
			$inner = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $inner );
			return $m[1] . strtr( $inner, $tags ) . $m[3];
		},
		$html
	);

	// libxml ends the text at a "<" that opens no tag, so escape those first.
	$html = preg_replace( '/<(?![a-zA-Z\/!?])/', '&lt;', $html );

	// An unterminated comment opener would swallow the rest of the post; keep it as text.
	$pos = strrpos( $html, '<!--' );
	while ( false !== $pos && false === strpos( $html, '-->', $pos ) ) {
		$html = substr_replace( $html, '&lt;!--', $pos, 4 );
		$pos  = strrpos( substr( $html, 0, $pos ), '<!--' );
	}

	// Output cut inside a tag ("<a href=\"https://exa") would absorb the wrapper's closer.
	$html = preg_replace( '/<[a-zA-Z][^<>]*$/', '', $html );

	$doc      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	// The XML prolog keeps UTF-8 intact.
	$loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return array();
	}

	// A stray closer can end the wrapper early, so walk every top-level node.
	$blocks = array();
	abcc_blocks_from_dom_children( $doc, $blocks, $doc );

	return $blocks;
}

/**
 * The function `abcc_create_blocks` creates an array of blocks from generated content lines.
 *
 * @param array $text_array The array of text items as input.
 * @return array The function returns an array of blocks.
 */
function abcc_create_blocks( $text_array ) {
	$lines = array_map( 'strval', (array) $text_array );
	if ( '' === trim( implode( '', $lines ) ) ) {
		return array();
	}

	// An empty DOM result is final; the per-line converter would keep text from dropped nodes.
	if ( class_exists( 'DOMDocument' ) ) {
		// Lines join untrimmed so code indentation and blank lines survive.
		return abcc_html_to_blocks( implode( "\n", $lines ) );
	}

	return abcc_create_blocks_by_line( $lines );
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
