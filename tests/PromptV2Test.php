<?php
/**
 * Prompt v2 (v4.6 §5.1, §5.2, §6): system/user split, length-aware structure,
 * source notes, recent titles, answer-focused block.
 */

/**
 * Decode the body of the last provider request.
 *
 * @return array
 */
function abcc_test_prompt_v2_last_body() {
	return json_decode( $GLOBALS['abcc_http_last_request']['args']['body'], true );
}

abcc_test(
	'section count follows the word target',
	function () {
		abcc_assert_same( '0', abcc_get_section_count( 150 ) );
		abcc_assert_same( '0', abcc_get_section_count( 399 ) );
		abcc_assert_same( '3', abcc_get_section_count( 400 ) );
		abcc_assert_same( '3', abcc_get_section_count( 450 ) );
		abcc_assert_same( '4', abcc_get_section_count( 600 ) );
		abcc_assert_same( '6', abcc_get_section_count( 900 ) );
		abcc_assert_same( '6', abcc_get_section_count( 901 ) );
		abcc_assert_same( '8', abcc_get_section_count( 1500 ) );
		abcc_assert_same( '13', abcc_get_section_count( 3000 ) );
	}
);

abcc_test(
	'a short post is told to skip headings and a long one gets sections',
	function () {
		$short = abcc_build_system_prompt(
			array(
				'word_count'    => 150,
				'focus_keyword' => 'sourdough',
			)
		);
		abcc_assert_true( false !== strpos( $short, 'about 150 words' ) );
		abcc_assert_true( false !== strpos( $short, 'no section headings' ) );
		abcc_assert_true( false !== strpos( $short, 'two or three sentences each' ) );
		abcc_assert_false( false !== strpos( $short, 'in one heading' ), 'No heading rule when there are no headings.' );

		$long = abcc_build_system_prompt(
			array(
				'word_count'    => 900,
				'focus_keyword' => 'sourdough',
			)
		);
		abcc_assert_true( false !== strpos( $long, 'about 6 sections of roughly 130 words each' ) );
		abcc_assert_true( false !== strpos( $long, 'short hook paragraph before any heading' ) );
		abcc_assert_true( false !== strpos( $long, 'two or three sentences each' ) );
		abcc_assert_true( false !== strpos( $long, 'focus keyword "sourdough" in the first paragraph and in one heading' ) );
	}
);

abcc_test(
	'system block carries persona, grounding rules, ban list and the format block once',
	function () {
		$system = abcc_build_system_prompt(
			array(
				'word_count'    => 900,
				'focus_keyword' => 'sourdough',
				'category'      => 7,
			)
		);

		abcc_assert_true( false !== strpos( $system, 'WP-AutoInsight Test Site' ) );
		abcc_assert_true( false !== strpos( $system, 'practical answers about Category 7' ), 'Audience defaults to the category.' );
		abcc_assert_true( false !== strpos( $system, 'Do not invent statistics' ) );
		abcc_assert_true( false !== strpos( $system, 'firsthand experience' ) );
		abcc_assert_true( false !== strpos( $system, 'delve into' ) );
		abcc_assert_same( 1, substr_count( $system, trim( ABCC_CONTENT_FORMAT_REQUIREMENTS ) ) );
		abcc_assert_false( false !== strpos( $system, 'Facts to use' ), 'No facts rule without source notes.' );
	}
);

abcc_test(
	'audience setting replaces the derived default and the ban list is filterable',
	function () {
		abcc_update_setting( 'abcc_content_audience', 'home bakers on a budget' );
		add_filter(
			'abcc_banned_phrases',
			function () {
				return array( 'synergy' );
			}
		);

		$system = abcc_build_system_prompt( array( 'word_count' => 500 ) );

		abcc_assert_true( false !== strpos( $system, 'You write for home bakers on a budget.' ) );
		abcc_assert_true( false !== strpos( $system, 'synergy' ) );
		abcc_assert_false( false !== strpos( $system, 'delve into' ) );
	}
);

abcc_test(
	'article prompt splits system from brief; source notes and recent titles are appended when the template has no token',
	function () {
		$parts = abcc_build_article_prompt(
			'Write about {keyword}.',
			'A Title',
			array( 'widgets' ),
			array(
				'char_limit'    => 1200,
				'source_notes'  => "Widgets ship in 3 sizes.\nSee https://example.com/specs",
				'recent_titles' => array( 'Widget Care 101', 'Why Widgets Break' ),
			)
		);

		abcc_assert_true( false !== strpos( $parts['user'], 'Write about widgets.' ) );
		abcc_assert_true( false !== strpos( $parts['user'], "Facts to use:\nWidgets ship in 3 sizes." ) );
		abcc_assert_true( false !== strpos( $parts['user'], "- Widget Care 101\n- Why Widgets Break" ) );
		abcc_assert_false( false !== strpos( $parts['user'], 'Format requirements' ), 'The brief carries no format block.' );

		abcc_assert_true( false !== strpos( $parts['system'], 'about 900 words' ) );
		abcc_assert_true( false !== strpos( $parts['system'], 'cite only URLs that appear there' ) );
		abcc_assert_true( false !== strpos( $parts['system'], 'already published on this site' ) );
		abcc_assert_true( false !== strpos( $parts['system'], 'Format requirements' ) );
	}
);

abcc_test(
	'tokens for notes, titles and the format block render in place and are not duplicated',
	function () {
		$parts = abcc_build_article_prompt(
			"Facts: {source_notes}\nCovered: {recent_titles}\nSections: {section_count}\nFor {audience} of {site_description}.\n{format_block}",
			'A Title',
			array( 'widgets' ),
			array(
				'char_limit'    => 1200,
				'source_notes'  => 'Widgets ship in 3 sizes.',
				'recent_titles' => array( 'Widget Care 101' ),
			)
		);

		abcc_assert_true( false !== strpos( $parts['user'], 'Facts: Widgets ship in 3 sizes.' ) );
		abcc_assert_true( false !== strpos( $parts['user'], 'Covered: - Widget Care 101' ) );
		abcc_assert_true( false !== strpos( $parts['user'], 'Sections: 6' ) );
		abcc_assert_true( false !== strpos( $parts['user'], 'For people searching for practical answers about widgets of Testing.' ) );
		abcc_assert_false( false !== strpos( $parts['user'], 'Facts to use:' ), 'No appended block when the token was used.' );
		abcc_assert_same( 1, substr_count( $parts['user'] . $parts['system'], trim( ABCC_CONTENT_FORMAT_REQUIREMENTS ) ), 'Format block is sent once, where the template put it.' );
	}
);

abcc_test(
	'a title or note containing a placeholder token stays literal',
	function () {
		$parts = abcc_build_article_prompt(
			'Write "{title}" about {keyword}.',
			'Using {keywords} and {language} in templates',
			array( 'placeholders' ),
			array(
				'char_limit'   => 800,
				'keywords_all' => array( 'SECRET-LIST' ),
				'source_notes' => 'The {site_name} token is documented.',
			)
		);

		abcc_assert_true( false !== strpos( $parts['user'], 'Using {keywords} and {language} in templates' ) );
		abcc_assert_false( false !== strpos( $parts['user'], 'SECRET-LIST' ) );
		abcc_assert_true( false !== strpos( $parts['user'], 'The {site_name} token is documented.' ) );
	}
);

abcc_test(
	'old single-string expander still appends the format block exactly once',
	function () {
		$prompt = abcc_expand_content_prompt( 'Write about {keyword} in {language}.', 'T', array( 'widgets' ), array() );
		abcc_assert_same( 1, substr_count( $prompt, ABCC_CONTENT_FORMAT_REQUIREMENTS ) );

		$inline = abcc_expand_content_prompt( "{format_block}\nWrite about {keyword} in {language}.", 'T', array( 'widgets' ), array() );
		abcc_assert_same( 1, substr_count( $inline, trim( ABCC_CONTENT_FORMAT_REQUIREMENTS ) ) );
	}
);

abcc_test(
	'body call sends a real system message per transport',
	function () {
		abcc_update_setting( 'abcc_content_templates', array( 'default' => abcc_get_default_content_template() ) );

		// OpenAI-compatible: system role.
		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );
		abcc_generate_post_content_with_template( 'k', array( 'widgets' ), 'openai::gpt-5.4-mini', 'A Title', 1200, array( 'template' => 'default' ) );
		$body = abcc_test_prompt_v2_last_body();
		abcc_assert_same( 'system', $body['messages'][0]['role'] );
		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'Format requirements' ) );
		abcc_assert_same( 'user', $body['messages'][1]['role'] );
		abcc_assert_false( false !== strpos( $body['messages'][1]['content'], 'Format requirements' ) );

		// Anthropic: top-level system.
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'content'     => array(
						array(
							'type' => 'text',
							'text' => '<p>Body.</p>',
						),
					),
					'stop_reason' => 'end_turn',
					'usage'       => array(
						'input_tokens'  => 5,
						'output_tokens' => 5,
					),
				)
			)
		);
		abcc_generate_post_content_with_template( 'k', array( 'widgets' ), 'claude::claude-sonnet-5-5', 'A Title', 1200, array( 'template' => 'default' ) );
		$body = abcc_test_prompt_v2_last_body();
		abcc_assert_true( false !== strpos( $body['system'], 'staff writer' ) );
		abcc_assert_false( false !== strpos( $body['messages'][0]['content'], 'Format requirements' ) );

		// Gemini: systemInstruction.
		abcc_test_queue_http_response(
			wp_json_encode(
				array(
					'candidates'    => array(
						array(
							'content'      => array( 'parts' => array( array( 'text' => '<p>Body.</p>' ) ) ),
							'finishReason' => 'STOP',
						),
					),
					'usageMetadata' => array(
						'promptTokenCount'     => 5,
						'candidatesTokenCount' => 5,
					),
				)
			)
		);
		abcc_generate_post_content_with_template( 'k', array( 'widgets' ), 'gemini::gemini-2.5-flash-lite', 'A Title', 1200, array( 'template' => 'default' ) );
		$body = abcc_test_prompt_v2_last_body();
		abcc_assert_true( false !== strpos( $body['systemInstruction']['parts'][0]['text'], 'staff writer' ) );
	}
);

abcc_test(
	'a model without a system role gets the system block prepended to the brief',
	function () {
		add_filter(
			'abcc_model_flags',
			function ( $flags ) {
				$flags['system_role'] = false;
				return $flags;
			}
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );
		abcc_generate_post_content( 'k', array( 'widgets' ), 'openai::gpt-5.4-mini', 'A Title', 1200 );
		$body = abcc_test_prompt_v2_last_body();

		abcc_assert_same( 1, count( $body['messages'] ) );
		abcc_assert_same( 'user', $body['messages'][0]['role'] );
		abcc_assert_true( 0 === strpos( $body['messages'][0]['content'], 'You are a staff writer' ) );
	}
);

abcc_test(
	'abcc_generation_prompt gets the brief as a string and abcc_generation_system_prompt gets the system block',
	function () {
		$seen = array();
		add_filter(
			'abcc_generation_prompt',
			function ( $prompt, $context ) use ( &$seen ) {
				$seen['prompt']  = $prompt;
				$seen['context'] = $context;
				return $prompt . "\nEXTRA-BRIEF";
			},
			10,
			2
		);
		add_filter(
			'abcc_generation_system_prompt',
			function ( $system, $context ) use ( &$seen ) {
				$seen['system']         = $system;
				$seen['system_context'] = $context;
				return $system . "\nEXTRA-SYSTEM";
			},
			10,
			2
		);

		abcc_test_queue_http_response( abcc_test_fake_generation( '<p>Body.</p>' ) );
		abcc_generate_post_content( 'k', array( 'widgets' ), 'openai::gpt-5.4-mini', 'A Title', 1200, array( 'job_id' => 0 ) );
		$body = abcc_test_prompt_v2_last_body();

		abcc_assert_true( is_string( $seen['prompt'] ) && false !== strpos( $seen['prompt'], 'widgets' ) );
		abcc_assert_true( is_string( $seen['system'] ) && false !== strpos( $seen['system'], 'staff writer' ) );
		abcc_assert_false( isset( $seen['context']['system'] ), 'The system block is not part of the hook context.' );
		abcc_assert_false( isset( $seen['context']['api_key'] ) );
		abcc_assert_same( 'openai', $seen['system_context']['provider'] );
		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'EXTRA-SYSTEM' ) );
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'EXTRA-BRIEF' ) );
	}
);

abcc_test(
	'title and SEO calls get a short system block with no article rules',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'A Fine Title' ) );
		abcc_generate_title( 'k', array( 'widgets' ), 'openai::gpt-5.4-mini', array( 'recent_titles' => array( 'Widget Care 101' ) ) );
		$body = abcc_test_prompt_v2_last_body();

		abcc_assert_same( 'system', $body['messages'][0]['role'] );
		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'headlines and search metadata' ) );
		abcc_assert_false( false !== strpos( $body['messages'][0]['content'], 'Do not invent statistics' ) );
		abcc_assert_false( false !== strpos( $body['messages'][0]['content'], 'Format requirements' ) );
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'Widget Care 101' ), 'The title call knows what already exists.' );

		abcc_test_queue_http_response( abcc_test_fake_seo_json( 'A Fine Title', 'Desc', 'widgets' ) );
		abcc_generate_title_and_seo( 'k', array( 'widgets' ), 'openai::gpt-5.4-mini', array( 'site_name' => 'S' ), array( 'recent_titles' => array( 'Widget Care 101' ) ) );
		$body = abcc_test_prompt_v2_last_body();

		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'headlines and search metadata' ) );
		abcc_assert_false( false !== strpos( $body['messages'][0]['content'], 'staff writer' ) );
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'Widget Care 101' ) );
	}
);

abcc_test(
	'recent titles come from generated posts of the same topic',
	function () {
		$mine = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Topic Post One',
			)
		);
		update_post_meta( $mine, '_abcc_generated', '1' );
		update_post_meta( $mine, '_abcc_topic_id', 4242 );

		$other = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Another Topic Post',
			)
		);
		update_post_meta( $other, '_abcc_generated', '1' );
		update_post_meta( $other, '_abcc_topic_id', 99 );

		$manual = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Hand Written',
			)
		);

		$titles = abcc_get_recent_titles( array( 'topic_id' => 4242 ) );
		abcc_assert_same( array( 'Topic Post One' ), $titles );

		$all = abcc_get_recent_titles( array() );
		abcc_assert_true( in_array( 'Topic Post One', $all, true ) && in_array( 'Another Topic Post', $all, true ) );
		abcc_assert_false( in_array( 'Hand Written', $all, true ), 'Only generated posts count.' );

		wp_delete_post( $mine, true );
		wp_delete_post( $other, true );
		wp_delete_post( $manual, true );
	}
);

abcc_test(
	'a topic post records its topic and carries the source notes into the body call',
	function () {
		abcc_update_setting( 'openai_generate_seo', false );
		abcc_update_setting( 'openai_generate_images', false );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'Widget Sizes Explained' ) );
		abcc_test_queue_http_response( abcc_test_fake_generation( "<p>Body one.</p>\n<p>Body two.</p>" ) );

		$post_id = abcc_openai_generate_post(
			'k',
			array( 'widgets' ),
			'openai::gpt-5.4-mini',
			'default',
			false,
			1200,
			'post',
			array(
				'topic_id'     => 5151,
				'prompt'       => 'Explain {keyword} sizes.',
				'source_notes' => 'Widgets ship in 3 sizes.',
				'source'       => 'topic',
			)
		);

		abcc_assert_true( is_int( $post_id ) && $post_id > 0 );
		abcc_assert_same( 5151, (int) get_post_meta( $post_id, '_abcc_topic_id', true ) );

		$body = abcc_test_prompt_v2_last_body();
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'Explain widgets sizes.' ) );
		abcc_assert_true( false !== strpos( $body['messages'][1]['content'], 'Widgets ship in 3 sizes.' ) );
		abcc_assert_true( false !== strpos( $body['messages'][0]['content'], 'cite only URLs that appear there' ) );

		wp_delete_post( $post_id, true );
	}
);

abcc_test(
	'answer-focused structure is off by default, opt-in globally, overridable per topic and skipped for short posts',
	function () {
		abcc_assert_false( abcc_resolve_answer_mode(), 'Off for new and existing installs.' );

		$off = abcc_build_article_prompt( 'Write about {keyword}.', 'T', array( 'widgets' ), array( 'char_limit' => 1200 ) );
		abcc_assert_false( false !== strpos( $off['system'], 'Answer-focused structure' ) );

		abcc_update_setting( 'abcc_answer_engine_mode', true );
		abcc_assert_true( abcc_resolve_answer_mode() );

		update_post_meta( 7001, '_abcc_topic_answer_mode', 'off' );
		abcc_assert_false( abcc_resolve_answer_mode( 7001 ), 'A topic can opt out.' );

		abcc_update_setting( 'abcc_answer_engine_mode', false );
		update_post_meta( 7002, '_abcc_topic_answer_mode', 'on' );
		abcc_assert_true( abcc_resolve_answer_mode( 7002 ), 'A topic can opt in.' );

		$on = abcc_build_article_prompt(
			'Write about {keyword}.',
			'T',
			array( 'widgets' ),
			array(
				'char_limit'  => 1200,
				'answer_mode' => true,
			)
		);
		abcc_assert_true( false !== strpos( $on['system'], 'a section of common questions' ) );
		abcc_assert_true( false !== strpos( $on['system'], 'exactly 3 short bullet points' ) );
		abcc_assert_true( false !== strpos( $on['system'], 'inside the word target' ) );
		abcc_assert_false( false !== stripos( $on['system'], 'cite a source' ), 'No citation rule: it invites invented URLs.' );

		$short = abcc_build_article_prompt(
			'Write about {keyword}.',
			'T',
			array( 'widgets' ),
			array(
				'char_limit'  => 200,
				'answer_mode' => true,
			)
		);
		abcc_assert_false( false !== strpos( $short['system'], 'Answer-focused structure' ), 'Skipped under 400 words.' );
	}
);

abcc_test(
	'answer-focused headings are described in the content language, never quoted in English',
	function () {
		abcc_update_setting( 'abcc_content_language', 'pt_BR' );

		$on = abcc_build_article_prompt(
			'Write about {keyword}.',
			'T',
			array( 'widgets' ),
			array(
				'char_limit'  => 1200,
				'answer_mode' => true,
			)
		);
		$language = abcc_resolve_content_language();

		abcc_assert_same( 2, substr_count( $on['system'], 'written in ' . $language ), 'Both fixed headings name the content language.' );
		// A quoted English heading is copied into the post whatever its language.
		abcc_assert_false( false !== stripos( $on['system'], 'key takeaways' ), $on['system'] );
		abcc_assert_false( false !== stripos( $on['system'], 'frequently asked questions' ), $on['system'] );
	}
);
