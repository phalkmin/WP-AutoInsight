<?php
/**
 * Plugin Name:       WP-AutoInsight Hook Smoke Test
 * Description:       Adds a tab, a setting and a text provider to WP-AutoInsight through its public filters only. Manual QA tool; not part of the release.
 * Version:           0.1.0
 * Requires Plugins:  automated-blog-content-creator
 * License:           GPLv2 or later
 *
 * How to use: copy this folder into wp-content/plugins, activate it, and run a
 * local OpenAI-compatible mock on ABCCSMOKE_ENDPOINT. Then check that
 *   1. a "Smoke Test" tab shows in WP-AutoInsight and prints the setting,
 *   2. abcc_get_setting( 'abccsmoke_note' ) reads and writes,
 *   3. "Smoke model" is offered in the model pickers and generates text.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ABCCSMOKE_ENDPOINT' ) ) {
	define( 'ABCCSMOKE_ENDPOINT', 'http://127.0.0.1:8765/ok/v1/chat/completions' );
}
// The registry reads a provider's key from its constant first.
if ( ! defined( 'ABCCSMOKE_API' ) ) {
	define( 'ABCCSMOKE_API', 'smoke-test-key' );
}

add_filter(
	'abcc_settings_schema',
	function ( $schema ) {
		$schema['settings']['abccsmoke_note'] = array(
			'default'  => 'hello from the smoke test',
			'sanitize' => 'sanitize_text_field',
		);

		return $schema;
	}
);

add_filter(
	'abcc_admin_tabs',
	function ( $tabs ) {
		$tabs['abccsmoke'] = array(
			'label' => 'Smoke Test',
			'file'  => __DIR__ . '/tab-smoke.php',
		);

		return $tabs;
	}
);

add_filter(
	'abcc_provider_registry',
	function ( $registry ) {
		$registry['abccsmoke'] = array(
			'id'              => 'abccsmoke',
			'name'            => 'Smoke Provider',
			'group_label'     => 'Smoke Provider Models',
			'help_url'        => '',
			'constant'        => 'ABCCSMOKE_API',
			'option_name'     => 'abccsmoke_api_key',
			'wp_connector_id' => '',
			'transport'       => 'openai_compatible',
			'endpoint'        => ABCCSMOKE_ENDPOINT,
			'auth'            => 'bearer',
			'extra_headers'   => array(),
			'timeout'         => 30,
			'capabilities'    => array(
				'text_generation'     => true,
				'image_generation'    => false,
				'citations'           => false,
				'audio_transcription' => false,
			),
			'text_models'     => array(
				'smoke-model' => array(
					'name'           => 'Smoke model',
					'description'    => 'Answers from the local mock',
					'context_window' => 8192,
					'max_output'     => 4096,
				),
			),
		);

		return $registry;
	}
);
