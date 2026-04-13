<?php
/**
 * Admin buttons functionality.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add "Create Post" button to the posts list screen.
 */
function abcc_add_create_post_button() {
	if ( ! abcc_current_user_can_prompt() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== $screen->base ) {
		return;
	}

	$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );
	if ( ! in_array( $screen->post_type, $selected_post_types, true ) ) {
		return;
	}
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Add button and status span next to "Add New" button.
		$('.page-title-action').after(
			'<span id="abcc-create-post-status" style="display:none; margin-left:8px; vertical-align:middle;"></span>' +
			'<a href="#" id="abcc-create-post" class="page-title-action" data-post-type="<?php echo esc_attr( $screen->post_type ); ?>">' +
			'<?php esc_html_e( 'Create AI Post', 'automated-blog-content-creator' ); ?>' +
			'</a>'
		);

		// Handle button click.
		$(document).on('click', '#abcc-create-post', function(e) {
			e.preventDefault();

			const $button = $(this);
			const $status = $('#abcc-create-post-status');
			const postType = $button.data('post-type') || 'post';

			if (!confirm('<?php echo esc_js( __( 'Create a new post using AI?', 'automated-blog-content-creator' ) ); ?>')) {
				return;
			}

			$button.css('pointer-events', 'none');
			abcc.showStatus($status, '<?php echo esc_js( __( 'Queueing generation job\u2026', 'automated-blog-content-creator' ) ); ?>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'abcc_create_post',
					nonce: '<?php echo esc_js( wp_create_nonce( 'abcc_admin_buttons' ) ); ?>',
					post_type: postType
				},
				success: function(response) {
					if (response.success) {
						abcc.showStatus($status, response.data.message);
						pollJobStatus(response.data.job_id);
					} else {
						abcc.setError($status, response.data.message || '<?php echo esc_js( __( 'An error occurred', 'automated-blog-content-creator' ) ); ?>');
						$button.css('pointer-events', 'auto');
					}
				},
				error: function() {
					abcc.setError($status, '<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
					$button.css('pointer-events', 'auto');
				}
			});

			function pollJobStatus(jobId) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'abcc_get_job_status',
						nonce: '<?php echo esc_js( wp_create_nonce( 'abcc_openai_generate_post' ) ); ?>',
						job_id: jobId
					},
					success: function(jobResponse) {
						if (!jobResponse.success) {
							abcc.setError($status, jobResponse.data.message || '<?php echo esc_js( __( 'An error occurred', 'automated-blog-content-creator' ) ); ?>');
							$button.css('pointer-events', 'auto');
							return;
						}

						const job = jobResponse.data;
						abcc.showStatus($status, 'Status: ' + job.statusLabel);

						if ('queued' === job.status || 'running' === job.status) {
							setTimeout(function() {
								pollJobStatus(jobId);
							}, 3000);
							return;
						}

						if ('succeeded' === job.status && job.post_id) {
							abcc.showStatus($status, '<?php echo esc_js( __( 'Done! Redirecting\u2026', 'automated-blog-content-creator' ) ); ?>', 'success');
							window.location.href = '<?php echo esc_url( admin_url( 'post.php?action=edit&post=' ) ); ?>' + job.post_id;
							return;
						}

						abcc.setError($status, job.message || '<?php echo esc_js( __( 'Generation failed.', 'automated-blog-content-creator' ) ); ?>');
						$button.css('pointer-events', 'auto');
					},
					error: function() {
						abcc.setError($status, '<?php echo esc_js( __( 'Network error occurred', 'automated-blog-content-creator' ) ); ?>');
						$button.css('pointer-events', 'auto');
					}
				});
			}
		});
	});
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'abcc_add_create_post_button' );
