<?php
/**
 * Block conversion keeps code, lists, quotes and inline emphasis (P3-03 / C-22).
 */

function abcc_test_blocks_html( $lines ) {
	return abcc_gutenberg_blocks( abcc_create_blocks( $lines ) );
}

abcc_test(
	'a pre/code sample becomes one code block and no empty paragraph',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Try it:</p>', '<pre><code>print("Hello World")', 'print("again")</code></pre>', '<p>Done.</p>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ), $html );
		abcc_assert_true( false !== strpos( $html, 'print(&quot;Hello World&quot;)' ) || false !== strpos( $html, 'print("Hello World")' ), 'Code text must survive.' );
		abcc_assert_false( false !== strpos( $html, '<p></p>' ), 'No empty paragraphs.' );
		abcc_assert_same( 2, substr_count( $html, '<!-- wp:paragraph -->' ) );
	}
);

abcc_test(
	'ul and ol become list blocks with list-item inner blocks',
	function () {
		$html = abcc_test_blocks_html( array( '<ul><li>One</li><li>Two <strong>bold</strong></li></ul>', '<ol><li>First</li></ol>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:list -->' ), $html );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:list {"ordered":true} -->' ), $html );
		abcc_assert_same( 3, substr_count( $html, '<!-- wp:list-item -->' ) );
		abcc_assert_true( false !== strpos( $html, '<strong>bold</strong>' ), 'Inline emphasis survives inside list items.' );
	}
);

abcc_test(
	'headings of every level and classed headings stay headings; strong and em survive in paragraphs',
	function () {
		$html = abcc_test_blocks_html( array( '<h2 class="x">Two</h2>', '<h4>Four</h4>', '<p>Keep <em>this</em> and <a href="https://e.test/">link</a>.</p>' ) );
		abcc_assert_true( false !== strpos( $html, '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Two</h2>' ), $html );
		abcc_assert_true( false !== strpos( $html, '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Four</h4>' ), $html );
		abcc_assert_true( false !== strpos( $html, '<em>this</em>' ) && false !== strpos( $html, 'href="https://e.test/"' ) );
	}
);

abcc_test(
	'blockquotes become quote blocks and plain lines become one paragraph each',
	function () {
		$html = abcc_test_blocks_html( array( '<blockquote><p>Quoted.</p></blockquote>', 'Plain line one', 'Plain line two' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:quote -->' ), $html );
		abcc_assert_same( 3, substr_count( $html, '<!-- wp:paragraph -->' ), 'Quote inner paragraph + two plain lines: ' . $html );
	}
);

abcc_test(
	'non-ascii text survives the DOM round trip',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Ação e coração — ok</p>' ) );
		abcc_assert_true( false !== strpos( $html, 'Ação e coração — ok' ), $html );
	}
);

abcc_test(
	'images at the root become image blocks; scripts are dropped',
	function () {
		$html = abcc_test_blocks_html( array( '<img src="https://e.test/a.png" alt="A">', '<script>alert(1)</script>', '<p>x</p>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:image -->' ), $html );
		abcc_assert_false( false !== strpos( $html, '<script' ) );
	}
);

abcc_test(
	'the legacy per-line converter still handles a simple h2 + p pair',
	function () {
		$blocks = abcc_create_blocks_by_line( array( '<h2>T</h2>', '<p>B</p>' ) );
		abcc_assert_same( 'heading', $blocks[0]['name'] );
		abcc_assert_same( 'paragraph', $blocks[1]['name'] );
	}
);

abcc_test(
	'a script-only reply yields no blocks instead of falling back to the per-line converter',
	function () {
		abcc_assert_same( array(), abcc_create_blocks( array( '<script>alert(1)</script>' ) ) );
	}
);

abcc_test(
	'a bare less-than sign does not truncate the post',
	function () {
		$html = abcc_test_blocks_html( array( '<p>if a < b then</p>', 'x < y', 'a <= b', 'x<5 works', 'Next' ) );
		abcc_assert_true( false !== strpos( $html, 'if a &lt; b then' ), $html );
		abcc_assert_true( false !== strpos( $html, 'x &lt; y' ), $html );
		abcc_assert_true( false !== strpos( $html, 'a &lt;= b' ), $html );
		abcc_assert_true( false !== strpos( $html, 'x&lt;5 works' ), $html );
		abcc_assert_true( false !== strpos( $html, '<p>Next</p>' ), $html );
	}
);

abcc_test(
	'code blocks keep indentation and blank lines',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code>    indented first', '', '  second</code></pre>' ) );
		abcc_assert_true( false !== strpos( $html, "<code>    indented first\n\n  second</code>" ), $html );
	}
);

abcc_test(
	'a blockquote keeps inner headings and lists',
	function () {
		$html = abcc_test_blocks_html( array( '<blockquote><h3>QH</h3><p>qp</p><ul><li>q item</li></ul></blockquote>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:quote -->' ), $html );
		foreach ( array( 'QH', 'qp', 'q item' ) as $needle ) {
			abcc_assert_true( false !== strpos( $html, $needle ), $needle . ' missing: ' . $html );
		}
	}
);

abcc_test(
	'a bare line with inline markup stays one paragraph',
	function () {
		$html = abcc_test_blocks_html( array( 'Hello <strong>world</strong> again' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:paragraph -->' ), $html );
		abcc_assert_true( false !== strpos( $html, '<p>Hello <strong>world</strong> again</p>' ), $html );

		$html = abcc_test_blocks_html( array( 'Fact.<sup><a href="https://e.test/1">[1]</a></sup> More.' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:paragraph -->' ), $html );
		abcc_assert_true( false !== strpos( $html, 'Fact.<sup><a href="https://e.test/1">[1]</a></sup> More.' ), $html );
	}
);

abcc_test(
	'a stray closing div does not drop the rest of the post',
	function () {
		$html = abcc_test_blocks_html( array( '<p>a</p></div><p>b</p>' ) );
		abcc_assert_true( false !== strpos( $html, '<p>a</p>' ) && false !== strpos( $html, '<p>b</p>' ), $html );
	}
);

abcc_test(
	'code blocks escape shortcode brackets',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code>[gallery ids="1"]</code></pre>' ) );
		abcc_assert_false( false !== strpos( $html, '[gallery' ), $html );
		abcc_assert_true( false !== strpos( $html, '&#91;gallery' ), $html );
	}
);

abcc_test(
	'a nested list becomes a nested list block inside its list item',
	function () {
		$html = abcc_test_blocks_html( array( '<ul><li>Parent<ul><li>Child</li></ul></li></ul>' ) );
		abcc_assert_same( 2, substr_count( $html, '<!-- wp:list -->' ), $html );
		abcc_assert_true( false !== strpos( $html, '<li>Parent<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Child</li>' ), $html );
	}
);

abcc_test(
	'a figure caption survives on the image block',
	function () {
		$html = abcc_test_blocks_html( array( '<figure><img src="https://e.test/a.png" alt="A"><figcaption>Cap <em>x</em></figcaption></figure>' ) );
		abcc_assert_true( false !== strpos( $html, '<figcaption class="wp-element-caption">Cap <em>x</em></figcaption></figure><!-- /wp:image -->' ), $html );
	}
);

abcc_test(
	'layout containers are walked and head content is dropped',
	function () {
		$html = abcc_test_blocks_html( array( '<head><title>T</title></head><header><p>h</p></header><aside><p>s</p></aside><footer><p>f</p></footer>' ) );
		abcc_assert_same( 3, substr_count( $html, '<!-- wp:paragraph -->' ), $html );
		abcc_assert_false( false !== strpos( $html, '>T<' ), $html );
	}
);

abcc_test(
	'unescaped comparison operators inside pre stay code text',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code>for (i=0; i<n; i++) { if (a>b) x = y; }</code></pre>', '<p>after</p>' ) );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ), $html );
		abcc_assert_true( false !== strpos( $text, 'i<n' ) && false !== strpos( $text, 'a>b' ), $html );
		abcc_assert_true( false !== strpos( $html, '<p>after</p>' ), $html );
	}
);

abcc_test(
	'escaped code tags inside pre stay literal text',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code>Use &lt;code&gt; tags here</code></pre>' ) );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ), $html );
		abcc_assert_true( false !== strpos( $text, 'Use <code> tags here' ), $html );
	}
);

abcc_test(
	'a code tag with an ampersand attribute is restored as markup',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code class="language-php" data-x="a&amp;b">echo 1;</code></pre>' ) );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ), $html );
		abcc_assert_true( false !== strpos( $text, 'echo 1;' ), $html );
		abcc_assert_false( false !== strpos( $text, '<code' ), $html );
	}
);

abcc_test(
	'a pre with two code children keeps both texts in one code block',
	function () {
		$html = abcc_test_blocks_html( array( '<pre><code>first_line()</code>', '<code>second_line()</code></pre>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ), $html );
		abcc_assert_true( false !== strpos( $html, 'first_line()' ), $html );
		abcc_assert_true( false !== strpos( $html, 'second_line()' ), 'The second code child must survive: ' . $html );
	}
);

abcc_test(
	'body headings map h1 to h2 and h5/h6 to h4',
	function () {
		$html = abcc_test_blocks_html( array( '<h1>Repeated Title</h1>', '<h3>Three</h3>', '<h5>Five</h5>', '<h6>Six</h6>' ) );
		abcc_assert_false( false !== strpos( $html, '<h1' ), 'No second page H1 in the body: ' . $html );
		abcc_assert_true( false !== strpos( $html, '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Repeated Title</h2>' ), $html );
		abcc_assert_true( false !== strpos( $html, '<h3 class="wp-block-heading">Three</h3>' ), $html );
		abcc_assert_true( false !== strpos( $html, '<h4 class="wp-block-heading">Five</h4>' ) && false !== strpos( $html, '<h4 class="wp-block-heading">Six</h4>' ), $html );
	}
);

abcc_test(
	'a raw block tag inside inline code does not swallow the rest of the post',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Wrap it in <code><div></code> like this.</p>', '<h2>Next section</h2>', '<p>More text.</p>', '<ul><li>One</li><li>Two</li></ul>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:heading' ), $html );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:list -->' ) );
		abcc_assert_same( 2, substr_count( $html, '<!-- wp:paragraph -->' ) );
		abcc_assert_true( false !== strpos( $html, '<code>&lt;div&gt;</code>' ), 'The sample stays visible as text.' );
	}
);

abcc_test(
	'an unclosed comment opener is kept as text instead of dropping the rest',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Intro.</p>', '<!-- model note', '<h2>Heading</h2>', '<p>Body after.</p>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:heading' ), $html );
		abcc_assert_true( false !== strpos( $html, 'Body after.' ) );
		abcc_assert_true( false !== strpos( $html, '&lt;!-- model note' ), 'The note survives as text.' );
	}
);

abcc_test(
	'a figure without an image keeps its table and caption',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Intro.</p>', '<figure><table><tr><td>Cell</td></tr></table><figcaption>Table 1</figcaption></figure>', '<p>After.</p>' ) );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:html -->' ), $html );
		abcc_assert_true( false !== strpos( $html, 'Cell' ) );
		abcc_assert_true( false !== strpos( $html, 'Table 1' ) );
	}
);

abcc_test(
	'output cut inside a tag drops the fragment instead of absorbing the wrapper',
	function () {
		$html = abcc_test_blocks_html( array( '<p>Read more at <a href="https://exa' ) );
		abcc_assert_true( false !== strpos( $html, 'Read more at' ), $html );
		abcc_assert_false( false !== strpos( $html, '&lt;/div&gt;' ), 'No wrapper leaks into an attribute.' );
		abcc_assert_false( false !== strpos( $html, '<a ' ), 'No broken anchor.' );
	}
);

abcc_test(
	'a pre tag mentioned in inline code does not pair with a later real code block',
	function () {
		$html = abcc_test_blocks_html(
			array(
				'<h2>Preformatted text in HTML</h2>',
				'<p>Wrap code in the <code><pre></code> element to keep spacing.</p>',
				'<h2>Example</h2>',
				'<pre><code>if (a < b) {',
				'    swap();',
				'}</code></pre>',
				'<h2>Conclusion</h2>',
				'<p>That is all.</p>',
			)
		);
		abcc_assert_same( 3, substr_count( $html, '<!-- wp:heading' ), $html );
		abcc_assert_same( 1, substr_count( $html, '<!-- wp:code -->' ) );
		abcc_assert_true( false !== strpos( $html, '<code>&lt;pre&gt;</code>' ), 'The mention stays visible as text.' );
		abcc_assert_true( false !== strpos( $html, 'swap();' ) );
	}
);
