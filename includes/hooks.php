<?php
/**
 * Extension hook reference and hook-context helpers.
 *
 * Every action and filter listed here is stable public API from 4.6.0 on.
 * Names, argument order, and the credential-free `$context` shape are
 * final; renaming any of them breaks third-party extensions.
 *
 * Shared `$context` shape (built by abcc_hook_safe_context()):
 *
 *   provider   string  Provider ID, e.g. 'openai', 'claude', 'gemini', 'perplexity'.
 *   model      string  Model ID, e.g. 'gpt-5.4-mini'.
 *   char_limit int     Requested tokens for this call.
 *   job_id     int     Job post ID when running from the queue; 0 when inline.
 *   source     string  Where the call came from. Post bodies carry the payload
 *                      source ('manual', 'scheduled', 'topic', 'bulk',
 *                      'regenerate'); auxiliary calls use 'title', 'seo',
 *                      'repair', 'rewrite', 'audio', 'infographic'.
 *   attempt    int     1 = primary provider, 2+ = fallback provider.
 *   post_id    int     Only on abcc_post_inserted.
 *
 * `api_key` is never present. Anything else the pipeline attached passes
 * through unchanged.
 *
 * ---------------------------------------------------------------------------
 * ACTIONS
 * ---------------------------------------------------------------------------
 *
 * abcc_before_generate ( array $context )
 *   Fires once per logical generation, before the prompt filter and the
 *   provider call. Fallback retries do not re-fire it.
 *   add_action( 'abcc_before_generate', function ( $ctx ) { error_log( $ctx['source'] ); } );
 *
 * abcc_after_generate ( array $context, array $result )
 *   Fires once per logical generation with the final result tuple
 *   ( content, usage, truncated, error, raw ) after abcc_generation_result.
 *   $context carries attempt / served_provider / served_model when a
 *   fallback served the request.
 *   add_action( 'abcc_after_generate', function ( $ctx, $r ) { if ( is_wp_error( $r['error'] ) ) { … } }, 10, 2 );
 *
 * abcc_post_inserted ( int $post_id, array $context )
 *   Fires after a generated content post is inserted (keyword/topic posts
 *   and post-from-audio). Not fired for job or topic CPT rows.
 *   add_action( 'abcc_post_inserted', function ( $post_id, $ctx ) { update_post_meta( $post_id, '_mine', 1 ); }, 10, 2 );
 *
 * ---------------------------------------------------------------------------
 * FILTERS
 * ---------------------------------------------------------------------------
 *
 * abcc_generation_prompt ( string $prompt, array $context ) : string
 *   The full prompt about to be sent, after templates, {language} and the
 *   global format requirements were applied.
 *   add_filter( 'abcc_generation_prompt', function ( $p, $ctx ) { return $p . "\nCite sources."; }, 10, 2 );
 *
 * abcc_generation_result ( array $result, array $context ) : array
 *   The normalized result tuple before it is returned to the pipeline.
 *   Return the same shape; 'content' is an array of lines.
 *   add_filter( 'abcc_generation_result', function ( $r, $ctx ) { $r['content'] = array_map( 'trim', $r['content'] ); return $r; }, 10, 2 );
 *
 * abcc_settings_schema ( array $schema ) : array
 *   The versioned settings schema. Add keys under $schema['settings'] as
 *   array( 'default' => …, 'sanitize' => callable ) and they become
 *   readable/writable via abcc_get_setting() / abcc_update_setting().
 *   add_filter( 'abcc_settings_schema', function ( $s ) { $s['settings']['myext_flag'] = array( 'default' => false ); return $s; } );
 *
 * abcc_admin_tabs ( array $tabs ) : array
 *   Primary admin tabs: slug => array( 'label' => string, 'file' => string ).
 *   'file' is relative to includes/admin/ for core tabs or an absolute path
 *   for extensions. Order of the array is the nav order.
 *   add_filter( 'abcc_admin_tabs', function ( $t ) { $t['myext'] = array( 'label' => 'My Ext', 'file' => __DIR__ . '/tab-myext.php' ); return $t; } );
 *
 * abcc_provider_registry ( array $registry ) : array
 *   The provider/model registry (see includes/providers.php). Since 4.0.0.
 *
 * abcc_resolve_post_status ( string $status, array $args ) : string
 *   Final post status for a generated post. Since 4.2.0.
 *
 * abcc_model_cost_estimate ( array $options ) : array
 *   Model options grouped by configured provider. Since 4.0.0.
 *
 * abcc_fallback_backoff_us ( int $microseconds, int $attempt ) : int
 *   Sleep before a fallback attempt. Return 0 to disable. Since 4.5.0.
 *
 * @package WP-AutoInsight
 * @since 4.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip credentials and normalize a generation context before handing it to a hook.
 *
 * @since 4.5.0
 * @param array $context Raw pipeline context.
 * @return array Context safe to expose to third-party code.
 */
function abcc_hook_safe_context( array $context ) {
	unset( $context['api_key'] );

	$context['provider']   = isset( $context['provider'] ) ? (string) $context['provider'] : '';
	$context['model']      = isset( $context['model'] ) ? (string) $context['model'] : '';
	$context['char_limit'] = isset( $context['char_limit'] ) ? (int) $context['char_limit'] : 0;
	$context['job_id']     = isset( $context['job_id'] ) ? (int) $context['job_id'] : 0;
	$context['attempt']    = isset( $context['attempt'] ) ? max( 1, (int) $context['attempt'] ) : 1;
	$context['source']     = isset( $context['source'] ) && '' !== (string) $context['source']
		? (string) $context['source']
		: 'manual';

	return $context;
}

/**
 * Registry of primary admin tabs.
 *
 * @since 4.5.0
 * @return array<string, array{label: string, file: string}> slug => tab.
 *         'file' is a path relative to includes/admin/ (core) or absolute (extensions).
 */
function abcc_get_admin_tabs() {
	$tabs = array(
		'dashboard'   => array(
			'label' => __( 'Dashboard', 'automated-blog-content-creator' ),
			'file'  => 'tab-dashboard.php',
		),
		'content'     => array(
			'label' => __( 'Content', 'automated-blog-content-creator' ),
			'file'  => 'tab-content.php',
		),
		'topics'      => array(
			'label' => __( 'Topics', 'automated-blog-content-creator' ),
			'file'  => 'tab-topics.php',
		),
		'media'       => array(
			'label' => __( 'Media', 'automated-blog-content-creator' ),
			'file'  => 'tab-media.php',
		),
		'connections' => array(
			'label' => __( 'Connections', 'automated-blog-content-creator' ),
			'file'  => 'tab-connections.php',
		),
		'settings'    => array(
			'label' => __( 'Settings', 'automated-blog-content-creator' ),
			'file'  => 'tab-settings.php',
		),
	);

	return apply_filters( 'abcc_admin_tabs', $tabs );
}

/**
 * Resolve a tab registry entry to the partial that renders it.
 *
 * Relative entries are reduced to their basename inside includes/admin/ so a
 * misbehaving filter cannot include files outside that directory. Absolute
 * entries (extensions) are used as-is when the file exists.
 *
 * @since 4.5.0
 * @param array $tab Registry entry with a 'file' key.
 * @return string Absolute path, or '' when nothing renderable was found.
 */
function abcc_resolve_admin_tab_file( array $tab ) {
	$file = isset( $tab['file'] ) ? (string) $tab['file'] : '';

	if ( '' === $file ) {
		return '';
	}

	if ( '/' === $file[0] ) {
		return file_exists( $file ) ? $file : '';
	}

	$path = __DIR__ . '/admin/' . basename( $file );

	return file_exists( $path ) ? $path : '';
}
