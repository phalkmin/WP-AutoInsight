<?php
/**
 * WordPress.org review request notice.
 *
 * Shown only on the plugin's own screens and the Posts list, once a site has
 * generated enough posts over enough time. Snooze and dismissal are per user.
 *
 * @package WP-AutoInsight
 * @since 4.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thresholds the notice waits for.
 *
 * @since 4.6.0
 * @return array{min_posts:int,min_days:int,snooze_days:int,max_snoozes:int,error_cooldown:int}
 */
function abcc_get_review_notice_rules() {
	return array(
		'min_posts'      => 5,
		'min_days'       => 14,
		'snooze_days'    => 30,
		'max_snoozes'    => 2,
		'error_cooldown' => DAY_IN_SECONDS,
	);
}

/**
 * Per-user notice state, normalized.
 *
 * @since 4.6.0
 * @param int $user_id User ID.
 * @return array{snoozed_until:int,snooze_count:int,done:bool}
 */
function abcc_get_review_notice_state( $user_id ) {
	$state = get_user_meta( (int) $user_id, 'abcc_review_notice_state', true );
	$state = is_array( $state ) ? $state : array();

	return array(
		'snoozed_until' => isset( $state['snoozed_until'] ) ? (int) $state['snoozed_until'] : 0,
		'snooze_count'  => isset( $state['snooze_count'] ) ? (int) $state['snooze_count'] : 0,
		'done'          => ! empty( $state['done'] ),
	);
}

/**
 * Whether the notice should show for a user: admin, enough posts, enough
 * days, not snoozed or dismissed, and no generation error in the last day.
 *
 * @since 4.6.0
 * @param int $user_id User ID.
 * @return bool
 */
function abcc_should_show_review_notice( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 || ! user_can( $user_id, 'manage_options' ) ) {
		return false;
	}

	$state = abcc_get_review_notice_state( $user_id );
	$now   = time();
	$rules = abcc_get_review_notice_rules();
	if ( $state['done'] || $now < $state['snoozed_until'] || $state['snooze_count'] >= $rules['max_snoozes'] ) {
		return false;
	}

	if ( (int) abcc_get_setting( 'abcc_generation_success_count', 0 ) < $rules['min_posts'] ) {
		return false;
	}

	$first = (int) abcc_get_setting( 'abcc_first_generation_at', 0 );
	if ( $first <= 0 || ( $now - $first ) < $rules['min_days'] * DAY_IN_SECONDS ) {
		return false;
	}

	$last_error = (int) abcc_get_setting( 'abcc_last_generation_error_at', 0 );
	if ( $last_error > 0 && ( $now - $last_error ) < $rules['error_cooldown'] ) {
		return false;
	}

	return true;
}

/**
 * The plugin's own admin screens and the Posts list; never the whole admin.
 *
 * @since 4.6.0
 * @return bool
 */
function abcc_is_review_notice_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->id ) ) {
		return false;
	}
	$id = (string) $screen->id;

	return 'edit-post' === $id || false !== strpos( $id, 'automated-blog-content-creator' );
}

/**
 * Apply a notice action to a user's state and persist it.
 *
 * @since 4.6.0
 * @param int    $user_id     User ID.
 * @param string $action_type One of done|snooze|dismiss.
 * @return array|WP_Error The stored state, or an error for an unknown action.
 */
function abcc_apply_review_notice_action( $user_id, $action_type ) {
	$user_id = (int) $user_id;
	$state   = abcc_get_review_notice_state( $user_id );
	$rules   = abcc_get_review_notice_rules();

	switch ( $action_type ) {
		case 'done':
		case 'dismiss':
			$state['done'] = true;
			break;
		case 'snooze':
			if ( $state['snooze_count'] >= $rules['max_snoozes'] ) {
				$state['done'] = true;
				break;
			}
			++$state['snooze_count'];
			$state['snoozed_until'] = time() + $rules['snooze_days'] * DAY_IN_SECONDS;
			break;
		default:
			return new WP_Error( 'abcc_invalid_review_action', __( 'Unknown review notice action.', 'automated-blog-content-creator' ) );
	}

	update_user_meta( $user_id, 'abcc_review_notice_state', $state );

	return $state;
}

/**
 * AJAX: record a review-notice choice for the current user.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_handle_review_notice_action() {
	check_ajax_referer( 'abcc_review_notice_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'automated-blog-content-creator' ) ) );
		return;
	}

	$action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';

	try {
		$state = abcc_apply_review_notice_action( get_current_user_id(), $action_type );
		if ( is_wp_error( $state ) ) {
			throw new Exception( $state->get_error_message() );
		}
		wp_send_json_success( array( 'state' => $state ) );
	} catch ( Exception $e ) {
		abcc_debug_log( 'Review notice action failed: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => __( 'Could not save your choice. Please try again.', 'automated-blog-content-creator' ) ) );
	}
}
add_action( 'wp_ajax_abcc_review_notice_action', 'abcc_handle_review_notice_action' );

/**
 * Count a completed generation; the first one starts the 14-day clock.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_record_generation_success() {
	abcc_update_setting( 'abcc_generation_success_count', (int) abcc_get_setting( 'abcc_generation_success_count', 0 ) + 1 );

	if ( (int) abcc_get_setting( 'abcc_first_generation_at', 0 ) <= 0 ) {
		abcc_update_setting( 'abcc_first_generation_at', time() );
	}
}

/**
 * Stamp a failed generation so the notice stays away for a day.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_record_generation_failure() {
	abcc_update_setting( 'abcc_last_generation_error_at', time() );
}

/**
 * Render the notice and enqueue its script when every condition holds.
 *
 * @since 4.6.0
 * @return void
 */
function abcc_render_review_notice() {
	if ( ! abcc_is_review_notice_screen() ) {
		return;
	}

	if ( ! abcc_should_show_review_notice( get_current_user_id() ) ) {
		return;
	}

	wp_enqueue_script( 'abcc-review-notice', plugins_url( '/js/review-notice.js', __DIR__ ), array( 'jquery' ), ABCC_VERSION, true );

	$count       = (int) abcc_get_setting( 'abcc_generation_success_count', 0 );
	$review_url  = 'https://wordpress.org/support/plugin/automated-blog-content-creator/reviews/#new-post';
	$support_url = 'https://wordpress.org/support/plugin/automated-blog-content-creator/';
	?>
	<div class="notice notice-info abcc-review-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'abcc_review_notice_nonce' ) ); ?>">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of posts generated successfully */
					__( 'You\'ve generated %d posts with WP-AutoInsight. If it\'s saving you time, would you leave a review on WordPress.org? It helps other site owners find the plugin.', 'automated-blog-content-creator' ),
					$count
				)
			);
			?>
		</p>
		<p>
			<a class="button button-primary abcc-review-notice-action" data-action-type="done" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'automated-blog-content-creator' ); ?></a>
			<button type="button" class="button abcc-review-notice-action" data-action-type="snooze"><?php esc_html_e( 'Maybe later', 'automated-blog-content-creator' ); ?></button>
			<button type="button" class="button abcc-review-notice-action" data-action-type="dismiss"><?php esc_html_e( 'Don\'t ask again', 'automated-blog-content-creator' ); ?></button>
		</p>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: opening link tag to the support forum, 2: closing link tag */
					__( 'Something not working? %1$sTell us on the support forum%2$s.', 'automated-blog-content-creator' ),
					'<a href="' . esc_url( $support_url ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'abcc_render_review_notice' );
