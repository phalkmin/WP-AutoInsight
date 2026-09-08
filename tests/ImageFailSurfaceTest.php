<?php
/**
 * Featured image failures must be visible to the user.
 */

abcc_test(
	'a failed image generation records the reason on the post',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'No image',
			)
		);

		abcc_record_image_generation_attempt( $post_id, false, 'Stability AI returned HTTP 402' );

		$meta = (string) get_post_meta( $post_id, '_abcc_image_generation_attempted', true );

		abcc_assert_true( '' !== $meta, 'A failed attempt must be recorded.' );
		abcc_assert_true(
			false !== strpos( $meta, 'HTTP 402' ),
			'The recorded value should carry the reason: ' . $meta
		);
	}
);

abcc_test(
	'a successful image generation records success, not a reason',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Has image',
			)
		);

		abcc_record_image_generation_attempt( $post_id, true, '' );

		abcc_assert_same(
			'1',
			(string) get_post_meta( $post_id, '_abcc_image_generation_attempted', true ),
			'Success should record "1".'
		);
	}
);

abcc_test(
	'the failure notice renders only for failed attempts',
	function () {
		$failed  = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Failed',
			)
		);
		$ok      = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Fine',
			)
		);
		$untried = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Untried',
			)
		);

		abcc_record_image_generation_attempt( $failed, false, 'Provider refused the prompt' );
		abcc_record_image_generation_attempt( $ok, true, '' );

		abcc_assert_true(
			false !== strpos( abcc_get_image_failure_notice_html( $failed ), 'Provider refused the prompt' ),
			'A failed post must render the reason.'
		);
		abcc_assert_same( '', abcc_get_image_failure_notice_html( $ok ), 'A successful post needs no notice.' );
		abcc_assert_same( '', abcc_get_image_failure_notice_html( $untried ), 'An untried post needs no notice.' );
	}
);

abcc_test(
	'image retry rebuilds its inputs from the generation params on the post',
	function () {
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A Post About Coffee',
			)
		);

		update_post_meta(
			$post_id,
			'_abcc_generation_params',
			wp_json_encode(
				array(
					'keywords'      => array( 'espresso machines', 'grinders' ),
					'focus_keyword' => 'espresso machines',
					'model'         => 'claude-sonnet-4-6',
				)
			)
		);

		$context = abcc_get_image_retry_context( $post_id );

		abcc_assert_same( array( 'espresso machines' ), $context['keywords'], 'Retry should reuse the focus keyword the original image was built from.' );
		abcc_assert_same( 'claude-sonnet-4-6', $context['model'], 'Retry should reuse the original generation model.' );
	}
);

abcc_test(
	'image retry falls back to the post title, never to an empty keyword',
	function () {
		// No _abcc_generation_params (audio/legacy post) — the old code read
		// _abcc_job_keywords, which only exists on JOB posts, and its (array)''
		// cast produced array('') so the title fallback never fired.
		$post_id = wp_insert_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A Legacy Post',
			)
		);

		$context = abcc_get_image_retry_context( $post_id );

		abcc_assert_same( array( 'A Legacy Post' ), $context['keywords'], 'Without params the post title must be the keyword.' );

		foreach ( $context['keywords'] as $kw ) {
			abcc_assert_true( '' !== trim( (string) $kw ), 'No retry keyword may be empty.' );
		}
	}
);
