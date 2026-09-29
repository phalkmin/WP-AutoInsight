<?php
/**
 * Content sub-tab: Keywords & Templates
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from admin.php: $keyword_groups, $content_templates, $tone, $custom_tone_value.

// Per-group "Advanced" overrides. Only models the user can call are offered;
// the "Use default" labels show the current globals so inheritance is visible.
$abcc_group_model_choices = abcc_get_available_text_model_options();
$abcc_global_model_label  = abcc_get_model_display_name( abcc_get_setting( 'prompt_select', '' ) );
$abcc_global_char_limit   = (int) abcc_get_setting( 'openai_char_limit', 200 );

/**
 * Render the Advanced block for one keyword group.
 *
 * @param string $index_attr Index (or "__INDEX__" for the JS template).
 * @param array  $group      Group data.
 * @return void
 */
$abcc_render_group_advanced = function ( $index_attr, $group ) use ( $abcc_group_model_choices, $abcc_global_model_label, $abcc_global_char_limit ) {
	$group_model = isset( $group['model'] ) ? (string) $group['model'] : '';
	$group_limit = isset( $group['char_limit'] ) ? (int) $group['char_limit'] : 0;
	$is_open     = '' !== $group_model || $group_limit > 0;
	?>
	<details class="abcc-group-advanced" <?php echo $is_open ? 'open' : ''; ?>>
		<summary><?php esc_html_e( 'Advanced', 'automated-blog-content-creator' ); ?></summary>
		<div class="abcc-group-advanced-fields">
			<div class="abcc-group-advanced-field">
				<label class="abcc-field-label" for="abcc_group_model_<?php echo esc_attr( $index_attr ); ?>"><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></label>
				<select id="abcc_group_model_<?php echo esc_attr( $index_attr ); ?>" name="abcc_group_model[<?php echo esc_attr( $index_attr ); ?>]">
					<option value="" <?php selected( '', $group_model ); ?>>
						<?php
						printf(
							/* translators: %s: current global model name */
							esc_html__( 'Use default (%s)', 'automated-blog-content-creator' ),
							esc_html( $abcc_global_model_label )
						);
						?>
					</option>
					<?php foreach ( $abcc_group_model_choices as $choice_group ) : ?>
						<optgroup label="<?php echo esc_attr( $choice_group['group'] ); ?>">
							<?php foreach ( $choice_group['options'] as $choice_id => $choice_data ) : ?>
								<option value="<?php echo esc_attr( $choice_id ); ?>" <?php selected( $choice_id, $group_model ); ?>><?php echo esc_html( abcc_format_model_option_label( $choice_id, $choice_data ) ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Only providers with a saved key are listed. Add a key under Connections → API Keys to see more.', 'automated-blog-content-creator' ); ?></p>
			</div>
			<div class="abcc-group-advanced-field">
				<label class="abcc-field-label" for="abcc_group_char_limit_<?php echo esc_attr( $index_attr ); ?>"><?php esc_html_e( 'Length (tokens)', 'automated-blog-content-creator' ); ?></label>
				<input type="number" id="abcc_group_char_limit_<?php echo esc_attr( $index_attr ); ?>" name="abcc_group_char_limit[<?php echo esc_attr( $index_attr ); ?>]"
					min="0" max="4000" step="100" class="small-text"
					value="<?php echo $group_limit > 0 ? esc_attr( $group_limit ) : ''; ?>"
					placeholder="<?php echo esc_attr( sprintf( /* translators: %d: current global length */ __( 'Default (%d)', 'automated-blog-content-creator' ), $abcc_global_char_limit ) ); ?>">
				<p class="description"><?php esc_html_e( '0 or blank = use the global setting.', 'automated-blog-content-creator' ); ?></p>
			</div>
		</div>
	</details>
	<?php
};
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="keywords">

		<h2><?php esc_html_e( 'Keyword Groups', 'automated-blog-content-creator' ); ?>
			<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Organize keywords into groups. Each group can target a specific category and use a specific template. The scheduler rotates through groups automatically.', 'automated-blog-content-creator' ) ) ); ?>
		</h2>
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
								<p class="description"><?php esc_html_e( 'One keyword from this group is picked at random for each generated post. Use {keyword} in your template to insert the chosen keyword, or {keywords} for the full comma-separated list.', 'automated-blog-content-creator' ); ?></p>
							</div>
							<div class="abcc-group-category">
								<label class="abcc-field-label"><?php esc_html_e( 'Target Category', 'automated-blog-content-creator' ); ?></label>
								<?php abcc_category_dropdown_single( $group['category'] ?? 0, "abcc_group_category[$index]" ); ?>
							</div>
							<div class="abcc-group-template">
								<label class="abcc-field-label"><?php esc_html_e( 'Template', 'automated-blog-content-creator' ); ?></label>
								<select name="abcc_group_template[<?php echo esc_attr( $index ); ?>]">
									<?php foreach ( $content_templates as $tpl_slug => $tpl ) : ?>
										<option value="<?php echo esc_attr( $tpl_slug ); ?>" <?php selected( $group['template'] ?? 'default', $tpl_slug ); ?>>
											<?php echo esc_html( $tpl['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<?php $abcc_render_group_advanced( (string) $index, (array) $group ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<template id="abcc-group-advanced-template"><?php $abcc_render_group_advanced( '__INDEX__', array() ); ?></template>

		<button type="button" id="abcc-add-group" class="button">
			<?php esc_html_e( '+ Add Group', 'automated-blog-content-creator' ); ?>
		</button>

		<hr>

		<h2><?php esc_html_e( 'Content Templates', 'automated-blog-content-creator' ); ?>
			<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Templates define the prompt pattern sent to the AI. Available placeholders: {keyword} (one keyword randomly picked from the group for this post), {keywords} (full comma-separated list), {title}, {tone}, {site_name}, {category}, {word_count}.', 'automated-blog-content-creator' ) ) ); ?>
		</h2>

		<div id="abcc-templates-container" class="abcc-groups-container">
			<?php foreach ( $content_templates as $tpl_slug => $tpl ) : ?>
				<div class="abcc-group-item abcc-template-item" data-slug="<?php echo esc_attr( $tpl_slug ); ?>">
					<div class="abcc-group-header">
						<strong><?php echo esc_html( $tpl['name'] ); ?></strong>
						<?php if ( 'default' !== $tpl_slug ) : ?>
							<span class="abcc-remove-item abcc-remove-template">&times; <?php esc_html_e( 'Remove', 'automated-blog-content-creator' ); ?></span>
						<?php else : ?>
							<em class="description"><?php esc_html_e( '(built-in, read-only)', 'automated-blog-content-creator' ); ?></em>
						<?php endif; ?>
					</div>
					<input type="hidden" name="abcc_template_slug[]" value="<?php echo esc_attr( $tpl_slug ); ?>">
					<?php if ( 'default' !== $tpl_slug ) : ?>
						<input type="text" name="abcc_template_name[<?php echo esc_attr( $tpl_slug ); ?>]" value="<?php echo esc_attr( $tpl['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'automated-blog-content-creator' ); ?>">
						<textarea name="abcc_template_prompt[<?php echo esc_attr( $tpl_slug ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $tpl['prompt'] ); ?></textarea>
					<?php else : ?>
						<textarea rows="3" class="large-text" readonly><?php echo esc_textarea( $tpl['prompt'] ); ?></textarea>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" id="abcc-add-template" class="button">
			<?php esc_html_e( '+ Add Template', 'automated-blog-content-creator' ); ?>
		</button>

		<hr>

		<h2><?php esc_html_e( 'Writing Style', 'automated-blog-content-creator' ); ?></h2>
		<p class="description"><?php esc_html_e( 'SEO metadata and draft-first defaults are in Settings → General → Content Defaults.', 'automated-blog-content-creator' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="openai_tone"><?php esc_html_e( 'Tone', 'automated-blog-content-creator' ); ?></label>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'The writing tone applied to all generated content. "Custom" lets you describe your own tone.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<select id="openai_tone" name="openai_tone" data-autosave-key="openai_tone">
						<option value="professional" <?php selected( $tone, 'professional' ); ?>><?php esc_html_e( 'Professional & formal', 'automated-blog-content-creator' ); ?></option>
						<option value="friendly" <?php selected( $tone, 'friendly' ); ?>><?php esc_html_e( 'Conversational & relaxed', 'automated-blog-content-creator' ); ?></option>
						<option value="warm" <?php selected( $tone, 'warm' ); ?>><?php esc_html_e( 'Warm & approachable', 'automated-blog-content-creator' ); ?></option>
						<option value="custom" <?php selected( $tone, 'custom' ); ?>><?php esc_html_e( 'Custom…', 'automated-blog-content-creator' ); ?></option>
					</select>
					<div id="abcc-custom-tone-wrapper" class="abcc-mt-8"<?php echo 'custom' !== $tone ? ' style="display:none;"' : ''; ?>>
						<input type="text" id="custom_tone" name="custom_tone" value="<?php echo esc_attr( $custom_tone_value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Describe your tone…', 'automated-blog-content-creator' ); ?>">
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="abcc_content_language"><?php esc_html_e( 'Content language', 'automated-blog-content-creator' ); ?></label>
					<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'The language generated posts are written in. Defaults to your site language.', 'automated-blog-content-creator' ) ) ); ?>
				</th>
				<td>
					<?php $abcc_language = abcc_sanitize_content_language( abcc_get_setting( 'abcc_content_language', 'site' ) ); ?>
					<select id="abcc_content_language" name="abcc_content_language" data-autosave-key="abcc_content_language">
						<?php foreach ( abcc_get_content_language_choices() as $abcc_value => $abcc_label ) : ?>
							<option value="<?php echo esc_attr( $abcc_value ); ?>" <?php selected( $abcc_language, $abcc_value ); ?>>
								<?php echo esc_html( $abcc_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Content Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
