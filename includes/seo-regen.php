<?php
/**
 * Bulk SEO regeneration worker.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regenerate SEO (title, meta description, focus keyword) for an existing
 * post, in place. Never creates a new post.
 *
 * @since 4.3.0
 *
 * @param int    $post_id Target post ID.
 * @param string $model   Model to use; '' falls back to the global prompt_select setting.
 * @return true|WP_Error Returns true on success, WP_Error on failure.
 */
function abcc_run_seo_regen( $post_id, $model = '' ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return new WP_Error(
			'abcc_seo_regen_no_post',
			__( 'Post not found for SEO regeneration.', 'automated-blog-content-creator' )
		);
	}

	if ( 'none' === abcc_get_active_seo_plugin() ) {
		return new WP_Error(
			'abcc_seo_regen_no_plugin',
			__( 'No supported SEO plugin (Yoast or Rank Math) is active.', 'automated-blog-content-creator' )
		);
	}

	$model   = ( '' !== $model ) ? $model : abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
	$api_key = abcc_check_api_key( $model );
	if ( empty( $api_key ) ) {
		return new WP_Error(
			'abcc_seo_regen_no_key',
			__( 'No API key configured for the selected model.', 'automated-blog-content-creator' )
		);
	}

	// Keywords: prefer stored generation params, else the post title as a fallback.
	$keywords   = array();
	$raw_params = get_post_meta( (int) $post_id, '_abcc_generation_params', true );
	if ( $raw_params ) {
		$decoded = json_decode( $raw_params, true );
		if ( is_array( $decoded ) && ! empty( $decoded['keywords'] ) ) {
			$keywords = (array) $decoded['keywords'];
		}
	}
	if ( empty( $keywords ) ) {
		$keywords = array( $post->post_title );
	}

	$site_info = array(
		'site_name'        => get_bloginfo( 'name' ),
		'site_description' => get_bloginfo( 'description' ),
	);

	try {
		$seo = abcc_generate_title_and_seo( $api_key, $keywords, $model, $site_info );
	} catch ( Exception $e ) {
		return new WP_Error(
			'abcc_seo_regen_failed',
			__( 'SEO generation failed.', 'automated-blog-content-creator' )
		);
	}

	if ( empty( $seo ) || empty( $seo['seo_data'] ) ) {
		return new WP_Error(
			'abcc_seo_regen_failed',
			__( 'SEO generation returned no usable data.', 'automated-blog-content-creator' )
		);
	}

	if ( ! empty( $seo['title'] ) ) {
		wp_update_post(
			array(
				'ID'         => (int) $post_id,
				'post_title' => $seo['title'],
			)
		);
	}

	$meta_fields = abcc_get_seo_meta_fields( $seo['seo_data'] );
	foreach ( $meta_fields as $meta_key => $meta_value ) {
		update_post_meta( (int) $post_id, $meta_key, $meta_value );
	}

	return true;
}
