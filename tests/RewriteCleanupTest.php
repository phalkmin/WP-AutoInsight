<?php
/**
 * Rewrite prompt and output hygiene (C-02, P3-01).
 */

abcc_test(
	'rewrite input keeps structure and links, drops block comments and images',
	function () {
		$in  = '<!-- wp:paragraph --><p>Hi <a href="https://e.test/">there</a>.</p><!-- /wp:paragraph --><!-- wp:image --><figure><img src="x.png"/></figure><!-- /wp:image --><h2>Two</h2>';
		$out = abcc_prepare_rewrite_input( $in );
		abcc_assert_false( false !== strpos( $out, 'wp:paragraph' ) );
		abcc_assert_false( false !== strpos( $out, '<img' ) );
		abcc_assert_true( false !== strpos( $out, '<a href="https://e.test/">there</a>' ) );
		abcc_assert_true( false !== strpos( $out, '<h2>Two</h2>' ) );
	}
);

abcc_test(
	'rewrite prompt carries the language and the shared format rules once, with no inline HTML rule list',
	function () {
		$GLOBALS['abcc_test_locale'] = 'pt_BR';
		$prompt                      = abcc_build_rewrite_prompt( 'Título', '<p>corpo</p>' );
		abcc_assert_true( false !== strpos( $prompt, 'Portuguese' ), $prompt );
		abcc_assert_same( 1, substr_count( $prompt, ABCC_CONTENT_FORMAT_REQUIREMENTS ) );
		abcc_assert_false( false !== strpos( $prompt, 'Use proper HTML formatting' ) );
		abcc_assert_true( false !== strpos( $prompt, 'Do not repeat the title' ) );
		abcc_assert_true( false !== strpos( $prompt, 'code fences' ) );
	}
);

abcc_test(
	'rewrite cleaner strips fences, trailing commentary and a repeated title',
	function () {
		$lines = array(
			'```html',
			'<h2>My Post Title</h2>',
			'<p>Real content.</p>',
			'<h2>Section</h2>',
			'<p>More.</p>',
			'```',
			'<p>**SEO-optimized keywords included:** a, b</p>',
			'**Instructions followed:** yes',
		);
		$clean = abcc_clean_rewrite_output( $lines, 'My Post Title' );
		abcc_assert_same( array( '<p>Real content.</p>', '<h2>Section</h2>', '<p>More.</p>' ), $clean );
	}
);

abcc_test(
	'rewrite cleaner keeps a first heading that is not the title',
	function () {
		$clean = abcc_clean_rewrite_output( array( '<h2>Intro</h2>', '<p>x</p>' ), 'Different Title' );
		abcc_assert_same( array( '<h2>Intro</h2>', '<p>x</p>' ), $clean );
	}
);

function abcc_test_rewrite_post_fixture() {
	$post_id                                        = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_title'   => 'Hello',
			'post_content' => '<p>Saved body text here.</p>',
		)
	);
	$GLOBALS['abcc_test_options']['openai_api_key'] = 'sk-test';
	$GLOBALS['abcc_test_options']['prompt_select']  = 'gpt-5.4-mini';
	$GLOBALS['abcc_test_options']['openai_char_limit'] = 200;
	$GLOBALS['abcc_test_last_json']                    = null;
	return $post_id;
}

abcc_test(
	'rewrite returns block markup and leaves post_content untouched',
	function () {
		$post_id = abcc_test_rewrite_post_fixture();
		$_POST   = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
			'content' => '<p>Unsaved editor text.</p>',
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( "```html\n<h2>Hello</h2>\n<p>Rewritten.</p>\n```" ) );

		abcc_handle_rewrite_post();

		$json = $GLOBALS['abcc_test_last_json'];
		abcc_assert_same( true, $json['success'] );
		abcc_assert_true( false !== strpos( $json['data']['content'], '<!-- wp:paragraph --><p>Rewritten.</p>' ), $json['data']['content'] );
		abcc_assert_false( false !== strpos( $json['data']['content'], 'Hello</h2>' ), 'Repeated title removed.' );
		abcc_assert_same( '<p>Saved body text here.</p>', get_post( $post_id )->post_content, 'Server must not save.' );

		$sent = (string) $GLOBALS['abcc_http_last_request']['args']['body'];
		abcc_assert_true( false !== strpos( $sent, 'Unsaved editor text' ), 'The editor content, not the saved one, is rewritten.' );
		$_POST = array();
	}
);

abcc_test(
	'rewrite refuses a truncated result',
	function () {
		$post_id = abcc_test_rewrite_post_fixture();
		$_POST   = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
		);
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'choices' => array(
						array(
							'message'       => array( 'content' => '<p>cut' ),
							'finish_reason' => 'length',
						),
					),
				)
			)
		);

		abcc_handle_rewrite_post();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], 'cut off' ) );
		$_POST = array();
	}
);

abcc_test(
	'rewrite on an empty body errors before calling the provider',
	function () {
		$post_id                    = abcc_test_rewrite_post_fixture();
		$_POST                      = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
			'content' => '<!-- wp:image --><figure><img src="a.png"/></figure><!-- /wp:image -->',
		);
		$GLOBALS['abcc_http_queue'] = array();

		abcc_handle_rewrite_post();

		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], 'nothing to rewrite' ) );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'], 'No provider call.' );
		$_POST = array();
	}
);

abcc_test(
	'rewrite budget grows with the input',
	function () {
		$post_id = abcc_test_rewrite_post_fixture();
		$long    = '<p>' . str_repeat( 'word ', 2000 ) . '</p>';
		$_POST   = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
			'content' => $long,
		);
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>ok</p>' ) );

		abcc_handle_rewrite_post();

		$sent = json_decode( (string) $GLOBALS['abcc_http_last_request']['args']['body'], true );
		$cap  = isset( $sent['max_completion_tokens'] ) ? (int) $sent['max_completion_tokens'] : (int) ( $sent['max_tokens'] ?? 0 );
		abcc_assert_true( $cap > 2000, 'A 2000-word input needs more than the 200-token setting; got ' . $cap );
		$_POST = array();
	}
);

abcc_test(
	'rewrite prompt keeps literal placeholders in the body verbatim',
	function () {
		$prompt = abcc_build_rewrite_prompt( 'T', '<p>Use {keyword} and {title} here</p>' );
		abcc_assert_true( false !== strpos( $prompt, '<p>Use {keyword} and {title} here</p>' ), $prompt );
	}
);

abcc_test(
	'rewrite refuses a post over 3000 words before calling the provider',
	function () {
		$post_id                    = abcc_test_rewrite_post_fixture();
		$_POST                      = array(
			'post_id' => $post_id,
			'nonce'   => 'n',
			'content' => '<p>' . str_repeat( 'word ', 3100 ) . '</p>',
		);
		$GLOBALS['abcc_http_queue'] = array();
		abcc_handle_rewrite_post();
		abcc_assert_same( false, $GLOBALS['abcc_test_last_json']['success'] );
		abcc_assert_true( false !== strpos( $GLOBALS['abcc_test_last_json']['data']['message'], '3,000' ) );
		abcc_assert_same( null, $GLOBALS['abcc_http_last_request'] );
		$_POST = array();
	}
);

abcc_test(
	'rewrite input keeps links on a short post and maps h1/h6',
	function () {
		$out = abcc_prepare_rewrite_input( '<h1>A</h1><p>' . str_repeat( 'w ', 100 ) . '<a href="https://e.test/">x</a></p><h6>B</h6>' );
		abcc_assert_true( false !== strpos( $out, '<a href="https://e.test/">x</a>' ) );
		abcc_assert_true( false !== strpos( $out, '<h2>A</h2>' ) );
		abcc_assert_true( false !== strpos( $out, '<h4>B</h4>' ) );
	}
);

abcc_test(
	'rewrite cleaner keeps a Step label and compares entity-encoded titles',
	function () {
		$clean = abcc_clean_rewrite_output( array( '<h2>Tom &amp; Jerry</h2>', '<p>**Step 1:** do it</p>' ), 'Tom & Jerry' );
		abcc_assert_same( array( '<p>**Step 1:** do it</p>' ), $clean );
	}
);

abcc_test(
	'rewrite JS applies the result in the editor and never reloads',
	function () {
		$js = file_get_contents( dirname( __DIR__ ) . '/js/meta-boxes.js' );
		abcc_assert_true( false !== strpos( $js, 'function abccSetEditorContent' ) );
		abcc_assert_true( false !== strpos( $js, 'function abccGetEditorContent' ) );
		abcc_assert_true( false !== strpos( $js, 'replaceBlocks' ), 'Block editor path must use an undoable replace.' );
		abcc_assert_false( false !== strpos( $js, 'window.location.reload' ), 'No reload after rewrite.' );
	}
);

abcc_test(
	'a title containing the body sentinel does not misplace the body',
	function () {
		$prompt = abcc_build_rewrite_prompt( 'Odd [[ABCC_REWRITE_BODY]] title', '<p>THE BODY</p>' );
		abcc_assert_true( false !== strpos( $prompt, "Original content:\n<p>THE BODY</p>" ), $prompt );
		abcc_assert_true( false !== strpos( $prompt, 'Original title: Odd [[ABCC_REWRITE_BODY]] title' ), $prompt );
	}
);

abcc_test(
	'rewrite cleaner keeps code indentation and blank lines inside pre',
	function () {
		$lines = array( '<p>Intro</p>', '<pre><code>def f():', '    x = 1', '', '    return x</code></pre>', '  <p>After</p>  ', '' );
		$clean = abcc_clean_rewrite_output( $lines, 'T' );
		abcc_assert_same( array( '<p>Intro</p>', '<pre><code>def f():', '    x = 1', '', '    return x</code></pre>', '<p>After</p>' ), $clean );

		$html = abcc_gutenberg_blocks( abcc_create_blocks( $clean ) );
		abcc_assert_true( false !== strpos( $html, "def f():\n    x = 1\n\n    return x" ), $html );
	}
);
