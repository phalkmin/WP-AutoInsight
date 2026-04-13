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
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<button type="button" id="abcc-add-group" class="button">
			<?php esc_html_e( '+ Add Group', 'automated-blog-content-creator' ); ?>
		</button>

		<hr>

		<h2><?php esc_html_e( 'Content Templates', 'automated-blog-content-creator' ); ?>
			<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Templates define the prompt pattern sent to the AI. Use {keywords}, {tone}, {site_name}, {category}, {word_count} as placeholders.', 'automated-blog-content-creator' ) ) ); ?>
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
						<input type="text" name="abcc_template_name[]" value="<?php echo esc_attr( $tpl['name'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Template name', 'automated-blog-content-creator' ); ?>">
						<textarea name="abcc_template_prompt[]" rows="3" class="large-text"><?php echo esc_textarea( $tpl['prompt'] ); ?></textarea>
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
		</table>

		<?php submit_button( __( 'Save Content Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
