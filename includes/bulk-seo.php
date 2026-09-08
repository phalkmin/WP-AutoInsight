<?php
/**
 * Bulk SEO regeneration — post-list bulk action.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue one SEO-regen job per post. Per-post queue failures are isolated.
 *
 * @since 4.3.0
 * @param array $post_ids Post IDs to regenerate SEO for.
 * @return array { @type int $queued, @type int $failed }
 */
function abcc_queue_seo_regen_batch( $post_ids ) {
	$queued = 0;
	$failed = 0;
	$model  = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );

	foreach ( (array) $post_ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			++$failed;
			continue;
		}

		// Per-post authorization: WordPress core does not filter custom
		// bulk-action IDs by capability, so a user could submit IDs for posts
		// they cannot edit. Skip any the current user lacks edit rights on.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			++$failed;
			continue;
		}

		$payload = abcc_build_generation_payload(
			array(
				'type'    => 'seo_regen',
				'post_id' => $post_id,
				'model'   => $model,
				'source'  => 'seo_regen',
			)
		);
		$job_id  = abcc_queue_generation_job( $payload, array( 'defer_spawn' => true ) );

		if ( is_wp_error( $job_id ) ) {
			++$failed;
			continue;
		}
		++$queued;
	}

	if ( $queued > 0 && abcc_is_wp_cron_available() ) {
		spawn_cron();
	}

	return array(
		'queued' => $queued,
		'failed' => $failed,
	);
}

/**
 * Register the "Regenerate SEO" bulk action on the posts list.
 *
 * @since 4.3.0
 * @param array $actions Existing bulk actions.
 * @return array
 */
function abcc_register_seo_bulk_action( $actions ) {
	if ( 'none' !== abcc_get_active_seo_plugin() && abcc_current_user_can_prompt() ) {
		$actions['abcc_seo_regen'] = __( 'Regenerate SEO (WP-AutoInsight)', 'automated-blog-content-creator' );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-post', 'abcc_register_seo_bulk_action' );

/**
 * Handle the "Regenerate SEO" bulk action.
 *
 * @since 4.3.0
 * @param string $redirect  Redirect URL.
 * @param string $doaction  The action being taken.
 * @param array  $post_ids  Selected post IDs.
 * @return string
 */
function abcc_handle_seo_bulk_action( $redirect, $doaction, $post_ids ) {
	if ( 'abcc_seo_regen' !== $doaction ) {
		return $redirect;
	}

	if ( ! abcc_current_user_can_prompt() ) {
		return $redirect;
	}

	$result   = abcc_queue_seo_regen_batch( $post_ids );
	$redirect = add_query_arg(
		array(
			'abcc_seo_queued' => $result['queued'],
			'abcc_seo_failed' => $result['failed'],
		),
		$redirect
	);

	return $redirect;
}
add_filter( 'handle_bulk_actions-edit-post', 'abcc_handle_seo_bulk_action', 10, 3 );

/**
 * Admin notice after a bulk SEO regen run.
 *
 * @since 4.3.0
 * @return void
 */
function abcc_seo_bulk_admin_notice() {
	if ( ! isset( $_GET['abcc_seo_queued'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a count set by our own redirect.
		return;
	}

	// Only on the posts list screen — the query args can linger in history.
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-post' !== $screen->id ) {
			return;
		}
	}

	$queued = isset( $_GET['abcc_seo_queued'] ) ? absint( $_GET['abcc_seo_queued'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$failed = isset( $_GET['abcc_seo_failed'] ) ? absint( $_GET['abcc_seo_failed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	// Warn (not success) when nothing queued or some posts failed.
	$notice_class = ( $queued > 0 && 0 === $failed ) ? 'notice-success' : 'notice-warning';

	printf(
		'<div class="notice %s is-dismissible"><p>%s</p></div>',
		esc_attr( $notice_class ),
		esc_html(
			sprintf(
				/* translators: 1: queued count, 2: failed count */
				_n(
					'Queued SEO regeneration for %1$d post (%2$d failed). They process in the background — see the job log.',
					'Queued SEO regeneration for %1$d posts (%2$d failed). They process in the background — see the job log.',
					$queued,
					'automated-blog-content-creator'
				),
				$queued,
				$failed
			)
		)
	);
}
add_action( 'admin_notices', 'abcc_seo_bulk_admin_notice' );
