<?php
/**
 * Topics can name their own keywords; the title call sees the brief (P1-09 / P3-04).
 */

abcc_test(
	'topic keywords are stored, returned, and default to the topic name',
	function () {
		$with = abcc_create_topic(
			array(
				'title'     => 'QA gen topic',
				'prompt'    => 'Escreva sobre tokusatsu dos anos 90.',
				'frequency' => 'daily',
				'keywords'  => "tokusatsu\nKamen Rider\n\n",
			)
		);
		abcc_assert_same( array( 'tokusatsu', 'Kamen Rider' ), abcc_get_topic( $with )['keywords'] );

		$without = abcc_create_topic(
			array(
				'title'     => 'Plain',
				'prompt'    => 'P',
				'frequency' => 'daily',
			)
		);
		abcc_assert_same( array(), abcc_get_topic( $without )['keywords'] );

		abcc_update_topic( $without, array( 'keywords' => array( ' alpha ', '' ) ) );
		abcc_assert_same( array( 'alpha' ), abcc_get_topic( $without )['keywords'] );
	}
);

abcc_test(
	'a topic job and the Composer source use the topic keywords, falling back to the name',
	function () {
		$topic_id = abcc_create_topic(
			array(
				'title'     => 'QA gen topic',
				'prompt'    => 'P',
				'frequency' => 'daily',
				'keywords'  => array( 'tokusatsu' ),
			)
		);
		$job_id   = abcc_queue_topic_generation( $topic_id );
		$payload  = get_post_meta( $job_id, '_abcc_job_payload', true );
		abcc_assert_same( array( 'tokusatsu' ), $payload['keywords'] );

		$source = abcc_resolve_composer_source( 'topic:' . $topic_id );
		abcc_assert_same( array( 'tokusatsu' ), $source['keywords'] );

		$plain  = abcc_create_topic(
			array(
				'title'     => 'Plain',
				'prompt'    => 'P',
				'frequency' => 'daily',
			)
		);
		$job_id = abcc_queue_topic_generation( $plain );
		abcc_assert_same( array( 'Plain' ), get_post_meta( $job_id, '_abcc_job_payload', true )['keywords'] );
	}
);

abcc_test(
	'title prompt includes the brief when a topic prompt is present',
	function () {
		abcc_test_queue_http_response( abcc_test_fake_generation( 'Os heróis de 1990' ) );
		abcc_generate_title( 'k', array( 'tokusatsu' ), 'gpt-5.4-mini', array( 'brief' => 'Escreva sobre tokusatsu dos anos 90.' ) );
		$sent = (string) $GLOBALS['abcc_http_last_request']['args']['body'];
		abcc_assert_true( false !== strpos( $sent, 'tokusatsu dos anos 90' ), 'Brief must reach the title prompt.' );
		abcc_assert_false( false !== strpos( $sent, 'anos 90..' ), 'A brief ending in a period gets no second one.' );

		abcc_test_queue_http_response( abcc_test_fake_generation( 'T' ) );
		abcc_generate_title( 'k', array( 'x' ), 'gpt-5.4-mini', array() );
		abcc_assert_false( false !== strpos( (string) $GLOBALS['abcc_http_last_request']['args']['body'], 'follows this brief' ), 'No brief, no brief sentence.' );
	}
);

abcc_test(
	'the brief resolves topic placeholders and drops the ones that describe the body call',
	function () {
		$brief = abcc_expand_brief( 'Write a guide about {keyword} for {category} readers, titled {title}, in {language}.', array( 'retro consoles' ), array( 'tone' => 'warm' ) );
		abcc_assert_true( false !== strpos( $brief, 'about retro consoles' ), $brief );
		abcc_assert_false( false !== strpos( $brief, '{' ), 'No placeholder survives: ' . $brief );
		abcc_assert_true( false !== strpos( $brief, 'titled this post, in' ), 'The title token reads naturally: ' . $brief );
	}
);
