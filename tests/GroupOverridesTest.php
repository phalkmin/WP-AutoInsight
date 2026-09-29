<?php
/**
 * Regression tests for per-keyword-group model / char_limit overrides (v4.5 Unit C).
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

abcc_test(
	'group without override keys inherits the global model and length',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'     => 'gpt-4.1-mini-2025-04-14',
			'openai_char_limit' => 333,
		);

		$params = abcc_resolve_group_generation_params( array( 'name' => 'Old', 'keywords' => array( 'a' ) ) );

		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', $params['model'] );
		abcc_assert_same( 333, $params['char_limit'] );
	}
);

abcc_test(
	'group model override is honored only when its provider has a key',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
			'claude_api_key' => 'sk-claude',
		);
		$group = array( 'keywords' => array( 'a' ), 'model' => 'claude-sonnet-4-6' );

		abcc_assert_same( 'claude-sonnet-4-6', abcc_resolve_group_generation_params( $group )['model'] );

		unset( $GLOBALS['abcc_test_options']['claude_api_key'] );
		abcc_assert_same( 'gpt-4.1-mini-2025-04-14', abcc_resolve_group_generation_params( $group )['model'], 'No key → inherit.' );
	}
);

abcc_test(
	'group char_limit override is clamped to the slider range and 0 inherits',
	function () {
		$GLOBALS['abcc_test_options'] = array( 'openai_char_limit' => 200 );

		abcc_assert_same( 800, abcc_resolve_group_generation_params( array( 'char_limit' => 800 ) )['char_limit'] );
		abcc_assert_same( 100, abcc_resolve_group_generation_params( array( 'char_limit' => 50 ) )['char_limit'] );
		abcc_assert_same( 4000, abcc_resolve_group_generation_params( array( 'char_limit' => 9999 ) )['char_limit'] );
		abcc_assert_same( 200, abcc_resolve_group_generation_params( array( 'char_limit' => 0 ) )['char_limit'] );
		abcc_assert_same( 200, abcc_resolve_group_generation_params( array( 'char_limit' => '' ) )['char_limit'] );
	}
);

abcc_test(
	'group source carries the resolved model and char_limit',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'     => 'gpt-4.1-mini-2025-04-14',
			'openai_char_limit' => 200,
			'claude_api_key'    => 'sk-claude',
		);

		$source = abcc_build_group_source(
			0,
			array(
				'name'       => 'Chem',
				'keywords'   => array( 'acids' ),
				'model'      => 'claude-sonnet-4-6',
				'char_limit' => 800,
			)
		);

		abcc_assert_same( 'claude-sonnet-4-6', $source['model'] );
		abcc_assert_same( 800, $source['char_limit'] );
	}
);

abcc_test(
	'blank model and zero char_limit passed to the payload builder still yield the globals',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'     => 'gemini-2.5-flash',
			'openai_char_limit' => 333,
		);

		$payload = abcc_build_generation_payload(
			array(
				'keywords'   => array( 'a' ),
				'model'      => '',
				'char_limit' => 0,
			)
		);

		abcc_assert_same( 'gemini-2.5-flash', $payload['model'] );
		abcc_assert_same( 333, $payload['char_limit'] );
	}
);

abcc_test(
	'scheduled run queues the group char_limit and model overrides',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'openai_auto_create'  => 'daily',
			'prompt_select'       => 'gpt-4.1-mini-2025-04-14',
			'openai_char_limit'   => 200,
			'claude_api_key'      => 'sk-claude',
			'abcc_keyword_groups' => array(
				array(
					'name'       => 'G',
					'keywords'   => array( 'alpha' ),
					'category'   => 0,
					'template'   => 'default',
					'model'      => 'claude-sonnet-4-6',
					'char_limit' => 800,
				),
			),
		);

		$job_id = abcc_openai_generate_post_scheduled();
		abcc_assert_true( is_int( $job_id ) && $job_id > 0, 'Scheduled run must queue a job.' );

		$payload = $GLOBALS['abcc_test_post_meta'][ $job_id ]['_abcc_job_payload'];
		abcc_assert_same( 800, $payload['char_limit'] );
		abcc_assert_same( 'claude-sonnet-4-6', $payload['model'] );
	}
);

abcc_test(
	'per-call Composer override still beats the group model override',
	function () {
		$GLOBALS['abcc_test_options'] = array(
			'prompt_select'  => 'gpt-4.1-mini-2025-04-14',
			'claude_api_key' => 'sk-claude',
		);

		$source  = abcc_build_group_source( 0, array( 'keywords' => array( 'a' ), 'model' => 'claude-sonnet-4-6' ) );
		$payload = abcc_build_generation_payload(
			array(
				'keywords'   => $source['keywords'],
				'model'      => $source['model'],
				'char_limit' => $source['char_limit'],
			)
		);
		abcc_assert_same( 'claude-sonnet-4-6', $payload['model'], 'Group override applies when no per-call override.' );

		$payload = abcc_apply_composer_overrides( $payload, array( 'model' => 'gpt-5.4' ) );
		abcc_assert_same( 'gpt-5.4', $payload['model'], 'Per-call override wins.' );
	}
);
