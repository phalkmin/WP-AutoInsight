<?php
/**
 * Settings-form persistence: template arrays keyed by slug, and tracking
 * JSON surviving wp_insert_post()'s unslash.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abcc_test(
	'template input keyed by slug survives a leading read-only Default row',
	function () {
		$templates = abcc_sanitize_content_templates_input(
			array( 'default', 'custom_a', 'custom_b' ),
			array(
				'custom_a' => 'Alpha',
				'custom_b' => 'Beta',
			),
			array(
				'custom_a' => 'Prompt A',
				'custom_b' => 'Prompt B',
			)
		);

		abcc_assert_same(
			array(
				'name'   => 'Alpha',
				'prompt' => 'Prompt A',
			),
			$templates['custom_a']
		);
		abcc_assert_same(
			array(
				'name'   => 'Beta',
				'prompt' => 'Prompt B',
			),
			$templates['custom_b']
		);
		abcc_assert_same( abcc_get_default_content_template(), $templates['default'], 'Default is always the built-in.' );
		abcc_assert_same( 3, count( $templates ) );
	}
);

abcc_test(
	'template input skips junk slugs and tolerates missing fields',
	function () {
		$templates = abcc_sanitize_content_templates_input(
			array( '', 'default', 'Custom-X!' ),
			array( 'custom-x' => ' Trimmed ' ),
			'not-an-array'
		);

		abcc_assert_same( array( 'custom-x', 'default' ), array_keys( $templates ) );
		abcc_assert_same( 'Trimmed', $templates['custom-x']['name'] );
		abcc_assert_same( '', $templates['custom-x']['prompt'] );
	}
);

abcc_test(
	'generation tracking JSON survives the wp_insert_post unslash round-trip',
	function () {
		$payload = abcc_build_generation_payload(
			array(
				'keywords' => array( 'say "hi"', 'café', 'back\\slash' ),
				'tone'     => 'custom "voice"',
			)
		);
		$meta    = abcc_build_generation_tracking_meta( $payload );

		// Unslashed, core's stripslashes breaks the escaped quotes.
		$broken_id = wp_insert_post(
			array(
				'post_title' => 't',
				'meta_input' => $meta,
			),
			true
		);
		abcc_assert_same( null, json_decode( get_post_meta( $broken_id, '_abcc_generation_params', true ), true ), 'Stub mirrors core: unslashed JSON is corrupted.' );

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title' => 'say "hi"',
					'meta_input' => $meta,
				)
			),
			true
		);
		$params  = json_decode( get_post_meta( $post_id, '_abcc_generation_params', true ), true );

		abcc_assert_same( array( 'say "hi"', 'café', 'back\\slash' ), $params['keywords'] );
		abcc_assert_same( 'custom "voice"', $params['tone'] );
		abcc_assert_same( 'say "hi"', get_post( $post_id )->post_title );
	}
);

abcc_test(
	'empty keyword groups are not saved',
	function () {
		$groups = abcc_sanitize_keyword_groups_input(
			array( 'Real', '' ),
			array( "a\nb", '' ),
			array( 3, 0 ),
			array( 'default', 'default' ),
			array( '', '' ),
			array( 0, 0 )
		);
		abcc_assert_same( 1, count( $groups ) );
		abcc_assert_same( 'Real', $groups[0]['name'] );

		$groups = abcc_sanitize_keyword_groups_input(
			array( '', 'Low', '', 'High' ),
			array( '', " a \n\n b ", 'only keywords', 'k' ),
			array( 0, 0, 0, 0 ),
			array( 'default', 'default', 'default', 'default' ),
			array( '', '', '', '' ),
			array( 0, 50, 0, 9999 )
		);
		abcc_assert_same( array( 0, 1, 2 ), array_keys( $groups ), 'Skipped rows leave no gaps.' );
		abcc_assert_same( array( 'a', 'b' ), $groups[0]['keywords'], 'Keywords split on lines and trimmed.' );
		abcc_assert_same( 100, $groups[0]['char_limit'], 'Below 100 clamps up.' );
		abcc_assert_same( '', $groups[1]['name'], 'A keywords-only group is kept.' );
		abcc_assert_same( array( 'only keywords' ), $groups[1]['keywords'] );
		abcc_assert_same( 0, $groups[1]['char_limit'], '0 means use the global setting.' );
		abcc_assert_same( 4000, $groups[2]['char_limit'], 'Above 4000 clamps down.' );
	}
);
