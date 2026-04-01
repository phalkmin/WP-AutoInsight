<?php
/**
 * Tab: Content Settings
 *
 * @package WP-AutoInsight
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables expected from admin.php:
// $schedule_info, $keyword_groups, $content_templates, $tone, $custom_tone_value

?>
<div class="tab-pane active">
	<?php if ( $schedule_info ) : ?>
		<div class="notice notice-info">
			<?php
			$time_diff = human_time_diff( time(), $schedule_info['timestamp'] );
			printf(
				// Translators: %1$s is the human-readable time difference, %2$s is the next run date.
				'<p>' . esc_html__( 'Next post scheduled in %1$s — %2$s. %3$s', 'automated-blog-content-creator' ) . '</p>',
				'<strong>' . esc_html( $time_diff ) . '</strong>',
				'<strong>' . esc_html( $schedule_info['next_run'] ) . '</strong>',
				'<a href="?page=automated-blog-content-creator-post&tab=advanced-settings#scheduling-settings">' . esc_html__( 'Change schedule →', 'automated-blog-content-creator' ) . '</a>'
			);
			?>
		</div>
	<?php else : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'There are no scheduled posts to be published.', 'automated-blog-content-creator' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		
		<h2><?php esc_html_e( 'Keyword Groups', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Organize your keywords into groups with specific categories and templates.', 'automated-blog-content-creator' ) ) ); ?></h2>
		<p class="description"><?php esc_html_e( 'Each keyword group can have its own category and content template. Scheduled generation will rotate through these groups.', 'automated-blog-content-creator' ); ?></p>
		
		<div id="abcc-keyword-groups-container" class="abcc-groups-container">
			<?php if ( ! empty( $keyword_groups ) ) : ?>
				<?php foreach ( $keyword_groups as $index => $group ) : ?>
					<div class="abcc-group-item" data-index="<?php echo esc_attr( $index ); ?>">
						<div class="abcc-group-header">
							<input type="text" name="abcc_group_name[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $group['name'] ); ?>" class="abcc-group-name-input" placeholder="<?php esc_attr_e( 'Group Name', 'automated-blog-content-creator' ); ?>">
							<span class="abcc-remove-item abcc-remove-group" title="<?php esc_attr_e( 'Remove Group', 'automated-blog-content-creator' ); ?>">&times; <?php esc_html_e( 'Remove', 'automated-blog-content-creator' ); ?></span>
						</div>
						<div class="abcc-group-body">
							<div class="abcc-group-keywords">
								<label class="abcc-field-label"><?php esc_html_e( 'Keywords (one per line)', 'automated-blog-content-creator' ); ?></label>
								<textarea name="abcc_group_keywords[<?php echo esc_attr( $index ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", (array) $group['keywords'] ) ); ?></textarea>
							</div>
							<div class="abcc-group-category">
								<label class="abcc-field-label"><?php esc_html_e( 'Target Category', 'automated-blog-content-creator' ); ?></label>
								<?php abcc_category_dropdown_single( $group['category'] ?? 0, "abcc_group_category[$index]" ); ?>
							</div>
							<div class="abcc-group-template">
								<label class="abcc-field-label"><?php esc_html_e( 'Content Template', 'automated-blog-content-creator' ); ?></label>
								<select name="abcc_group_template[<?php echo esc_attr( $index ); ?>]">
									<?php foreach ( $content_templates as $slug => $template ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $group['template'] ?? 'default', $slug ); ?>><?php echo esc_html( $template['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" id="abcc-add-group" class="button button-secondary abcc-add-item-button">
			<span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span> <?php esc_html_e( 'Add Keyword Group', 'automated-blog-content-creator' ); ?>
		</button>

		<hr style="margin: 30px 0;">

		<h2><?php esc_html_e( 'Content Templates', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Define the structure and instructions for your AI-generated content.', 'automated-blog-content-creator' ) ) ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use placeholders to inject dynamic data into your prompts: {keywords}, {title}, {tone}, {site_name}, {category}.', 'automated-blog-content-creator' ); ?></p>
		
		<div id="abcc-content-templates-container" class="abcc-templates-container">
			<?php foreach ( $content_templates as $slug => $template ) : ?>
				<?php if ( 'default' === $slug ) : ?>
				<div class="abcc-template-item abcc-template-item--locked" data-slug="default">
					<div class="abcc-template-header">
						<strong class="abcc-template-name-input"><?php echo esc_html( $template['name'] ); ?></strong>
						<span class="abcc-template-badge"><?php esc_html_e( 'System default — read only', 'automated-blog-content-creator' ); ?></span>
					</div>
					<div class="abcc-template-body" style="grid-template-columns: 1fr;">
						<div class="abcc-template-prompt">
							<label class="abcc-field-label"><?php esc_html_e( 'Prompt Pattern', 'automated-blog-content-creator' ); ?></label>
							<textarea rows="3" class="large-text" readonly style="background:#f6f7f7; color:#50575e; cursor:default; resize:none;"><?php echo esc_textarea( $template['prompt'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'HTML structure rules (headings, paragraphs, tag closing) are enforced automatically on top of any template — including this one.', 'automated-blog-content-creator' ); ?></p>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="abcc-template-item" data-slug="<?php echo esc_attr( $slug ); ?>">
					<div class="abcc-template-header">
						<input type="text" name="abcc_template_name[]" value="<?php echo esc_attr( $template['name'] ); ?>" class="abcc-template-name-input" placeholder="<?php esc_attr_e( 'Template Name', 'automated-blog-content-creator' ); ?>">
						<input type="hidden" name="abcc_template_slug[]" value="<?php echo esc_attr( $slug ); ?>">
						<span class="abcc-remove-item abcc-remove-template" title="<?php esc_attr_e( 'Remove Template', 'automated-blog-content-creator' ); ?>">&times; <?php esc_html_e( 'Remove', 'automated-blog-content-creator' ); ?></span>
					</div>
					<div class="abcc-template-body" style="grid-template-columns: 1fr;">
						<div class="abcc-template-prompt">
							<label class="abcc-field-label"><?php esc_html_e( 'Prompt Pattern', 'automated-blog-content-creator' ); ?></label>
							<textarea name="abcc_template_prompt[]" rows="6" class="large-text"><?php echo esc_textarea( $template['prompt'] ); ?></textarea>
							<div class="abcc-placeholder-list">
								<?php esc_html_e( 'Available Placeholders:', 'automated-blog-content-creator' ); ?>
								<span class="abcc-placeholder-tag">{keywords}</span>
								<span class="abcc-placeholder-tag">{title}</span>
								<span class="abcc-placeholder-tag">{tone}</span>
								<span class="abcc-placeholder-tag">{site_name}</span>
								<span class="abcc-placeholder-tag">{category}</span>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<button type="button" id="abcc-add-template" class="button button-secondary abcc-add-item-button">
			<span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span> <?php esc_html_e( 'Add Content Template', 'automated-blog-content-creator' ); ?>
		</button>

		<hr style="margin: 30px 0;">

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Target Post Types', 'automated-blog-content-creator' ); ?></th>
				<td>
					<?php
					$selected_post_types = get_option( 'abcc_selected_post_types', array( 'post' ) );
					$post_types          = get_post_types( array( 'public' => true ), 'objects' );
					echo '<select id="abcc_selected_post_types" class="wpai-post-type-select" name="abcc_selected_post_types[]" multiple style="width:100%;">';
					foreach ( $post_types as $public_type ) {
						$selected = in_array( $public_type->name, $selected_post_types, true ) ? ' selected="selected"' : '';
						echo '<option value="' . esc_attr( $public_type->name ) . '"' . esc_attr( $selected ) . '>' . esc_html( $public_type->label ) . '</option>';
					}
					echo '</select>';
					?>
					<p class="description"><?php esc_html_e( 'Select which post types should have the "Create Post" button', 'automated-blog-content-creator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tone', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Controls the writing style of your generated content.', 'automated-blog-content-creator' ) ) ); ?></th>
				<td>
					<select name="openai_tone" id="openai_tone">
						<option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional & formal', 'automated-blog-content-creator' ); ?></option>
						<option value="casual" <?php selected( $tone, 'casual' ); ?>><?php esc_html_e( 'Conversational & relaxed', 'automated-blog-content-creator' ); ?></option>
						<option value="friendly" <?php selected( $tone, 'friendly' ); ?>><?php esc_html_e( 'Warm & approachable', 'automated-blog-content-creator' ); ?></option>
						<option value="custom" <?php selected( $tone, 'custom' ); ?>><?php esc_html_e( 'Custom (define your own)', 'automated-blog-content-creator' ); ?></option>
					</select>
					<div id="custom_tone_container" style="display: <?php echo $tone === 'custom' ? 'block' : 'none'; ?>; margin-top: 10px;">
						<input type="text" name="custom_tone" value="<?php echo esc_attr( $custom_tone_value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom tone', 'automated-blog-content-creator' ); ?>">
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Workflow Settings', 'automated-blog-content-creator' ); ?></th>
				<td>
					<label for="abcc_draft_first">
						<input type="checkbox" name="abcc_draft_first" id="abcc_draft_first" value="1" <?php checked( get_option( 'abcc_draft_first', true ) ); ?>>
						<?php esc_html_e( 'Always save as draft for review before publishing', 'automated-blog-content-creator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'When enabled, new posts will be created as drafts. Recommended for quality control.', 'automated-blog-content-creator' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<hr style="margin: 30px 0;">

	<div id="abcc-bulk-generate-panel" class="abcc-collapsible-panel">
		<div class="abcc-panel-header">
			<h2><?php esc_html_e( 'Bulk Generate', 'automated-blog-content-creator' ); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></h2>
			<p class="description"><?php esc_html_e( 'Create multiple posts at once from a list of keywords.', 'automated-blog-content-creator' ); ?></p>
		</div>
		<div class="abcc-panel-content" style="display: none; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-top: none;">
			<div class="abcc-bulk-grid">
				<div class="abcc-bulk-keywords">
					<label class="abcc-field-label"><?php esc_html_e( 'Keywords (one per line)', 'automated-blog-content-creator' ); ?></label>
					<textarea id="abcc-bulk-keywords-input" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'Example:\nArtificial Intelligence\nMachine Learning\nData Science', 'automated-blog-content-creator' ); ?>"></textarea>
					<p style="margin: 10px 0;">
						<label for="abcc-bulk-keywords-file" class="abcc-field-label"><?php esc_html_e( 'Or upload a .txt file', 'automated-blog-content-creator' ); ?></label><br>
						<input type="file" id="abcc-bulk-keywords-file" accept=".txt,text/plain">
					</p>
					<p class="description"><?php esc_html_e( 'Each line will trigger one separate post generation.', 'automated-blog-content-creator' ); ?></p>
				</div>
				<div class="abcc-bulk-settings">
					<div class="abcc-field-group">
						<label class="abcc-field-label"><?php esc_html_e( 'Content Template', 'automated-blog-content-creator' ); ?></label>
						<select id="abcc-bulk-template" class="widefat">
							<?php foreach ( $content_templates as $slug => $template ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $template['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="abcc-field-group" style="margin-top: 15px;">
						<label class="abcc-field-label"><?php esc_html_e( 'AI Model', 'automated-blog-content-creator' ); ?></label>
						<select id="abcc-bulk-model" class="widefat">
							<?php
							$model_options = abcc_get_ai_model_options();
							$current_model = get_option( 'prompt_select', 'gpt-4.1-mini' );
							foreach ( $model_options as $provider => $data ) :
								printf( '<optgroup label="%s">', esc_attr( $data['group'] ) );
								foreach ( $data['options'] as $m_id => $m_data ) {
									printf( '<option value="%s" %s>%s</option>', esc_attr( $m_id ), selected( $current_model, $m_id, false ), esc_html( $m_data['name'] ) );
								}
								echo '</optgroup>';
							endforeach;
							?>
						</select>
					</div>
					<div class="abcc-field-group" style="margin-top: 15px;">
						<label>
							<input type="checkbox" id="abcc-bulk-draft" checked disabled>
							<?php esc_html_e( 'Create as draft (mandatory for bulk)', 'automated-blog-content-creator' ); ?>
						</label>
					</div>
					<div style="margin-top: 20px;">
						<button type="button" id="abcc-start-bulk" class="button button-primary button-large" disabled>
							<?php esc_html_e( 'Generate 0 Posts', 'automated-blog-content-creator' ); ?>
						</button>
					</div>
				</div>
			</div>
			<div id="abcc-bulk-progress" style="margin-top: 25px; display: none;">
				<h3><?php esc_html_e( 'Generation Progress', 'automated-blog-content-creator' ); ?></h3>
				<div id="abcc-bulk-log" style="max-height: 300px; overflow-y: auto; background: #f0f0f1; padding: 15px; border-radius: 4px; border: 1px solid #ccd0d4; font-family: monospace;">
				</div>
			</div>
		</div>
	</div>

	<hr style="margin: 40px 0 20px;">
	<div id="abcc-manual-generation-status" style="margin: 10px 0;"></div>
	<?php if ( ! abcc_is_wp_cron_available() || abcc_has_stale_queued_jobs() ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Background jobs may be delayed. WP-Cron appears disabled or queued jobs have not started yet. If this keeps happening, check your site cron configuration.', 'automated-blog-content-creator' ); ?>
			</p>
		</div>
	<?php endif; ?>
	<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
		<?php if ( count( $keyword_groups ) > 1 ) : ?>
			<select id="abcc-group-select" style="max-width: 260px;">
				<?php foreach ( $keyword_groups as $i => $group ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>">
						<?php
						// translators: %d: Group number.
						echo esc_html( $group['name'] ?? sprintf( __( 'Group %d', 'automated-blog-content-creator' ), $i + 1 ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<button type="button" name="generate-post" id="generate-post" class="button button-secondary">
			<?php esc_html_e( 'Create post manually', 'automated-blog-content-creator' ); ?>
		</button>
	</div>

	<hr style="margin: 40px 0 20px;">
	<h2><?php esc_html_e( 'Generation Log', 'automated-blog-content-creator' ); ?></h2>
	<div class="abcc-job-log-toolbar">
		<label for="abcc-job-filter">
			<?php esc_html_e( 'Show:', 'automated-blog-content-creator' ); ?>
		</label>
		<select id="abcc-job-filter">
			<option value=""><?php esc_html_e( 'All jobs', 'automated-blog-content-creator' ); ?></option>
			<option value="queued"><?php esc_html_e( 'Queued', 'automated-blog-content-creator' ); ?></option>
			<option value="running"><?php esc_html_e( 'Running', 'automated-blog-content-creator' ); ?></option>
			<option value="failed"><?php esc_html_e( 'Failed', 'automated-blog-content-creator' ); ?></option>
			<option value="succeeded"><?php esc_html_e( 'Succeeded', 'automated-blog-content-creator' ); ?></option>
		</select>
		<label class="abcc-job-log-autorefresh">
			<input type="checkbox" id="abcc-job-auto-refresh" checked>
			<?php esc_html_e( 'Auto-refresh', 'automated-blog-content-creator' ); ?>
		</label>
		<button type="button" id="abcc-job-refresh" class="button button-secondary">
			<?php esc_html_e( 'Refresh now', 'automated-blog-content-creator' ); ?>
		</button>
	</div>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Status', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Source', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Keywords Used', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Template', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Created', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Runtime', 'automated-blog-content-creator' ); ?></th>
				<th><?php esc_html_e( 'Result', 'automated-blog-content-creator' ); ?></th>
			</tr>
		</thead>
		<tbody id="abcc-job-log-body">
			<?php echo abcc_render_job_log_rows(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</tbody>
	</table>
	<p>
		<?php esc_html_e( 'The latest jobs update automatically while generation runs in the background.', 'automated-blog-content-creator' ); ?>
	</p>
</div>
