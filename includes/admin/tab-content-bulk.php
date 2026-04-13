<?php
/**
 * Content sub-tab: Bulk Generate
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$model_options = abcc_get_ai_model_options();
$current_model = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
$templates     = abcc_get_setting( 'abcc_content_templates', array() );
?>
<div class="tab-pane active">
	<h2><?php esc_html_e( 'Bulk Generate', 'automated-blog-content-creator' ); ?>
		<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Generate one post per keyword. Posts are created sequentially to respect API rate limits. Using draft mode is strongly recommended for bulk runs.', 'automated-blog-content-creator' ) ) ); ?>
	</h2>

	<div class="abcc-bulk-form">
		<div class="abcc-bulk-keywords">
			<label for="abcc-bulk-keywords-input">
				<strong><?php esc_html_e( 'Keywords (one per line)', 'automated-blog-content-creator' ); ?></strong>
			</label>
			<textarea id="abcc-bulk-keywords-input" rows="8" class="large-text" placeholder="<?php esc_attr_e( 'artificial intelligence&#10;content marketing tips&#10;WordPress SEO guide', 'automated-blog-content-creator' ); ?>"></textarea>
			<p class="description">
				<label>
					<?php esc_html_e( 'Or upload a .txt file:', 'automated-blog-content-creator' ); ?>
					<input type="file" id="abcc-bulk-file-upload" accept=".txt" class="abcc-ml-6">
				</label>
			</p>
		</div>

		<div class="abcc-bulk-options">
			<label for="abcc-bulk-template"><?php esc_html_e( 'Template:', 'automated-blog-content-creator' ); ?></label>
			<select id="abcc-bulk-template">
				<?php foreach ( $templates as $slug => $tpl ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $tpl['name'] ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="abcc-bulk-model"><?php esc_html_e( 'Model:', 'automated-blog-content-creator' ); ?></label>
			<select id="abcc-bulk-model">
				<?php foreach ( $model_options as $provider_data ) : ?>
					<optgroup label="<?php echo esc_attr( $provider_data['group'] ); ?>">
						<?php foreach ( $provider_data['options'] as $model_id => $model_data ) : ?>
							<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>>
								<?php echo esc_html( $model_data['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>

			<label>
				<input type="checkbox" id="abcc-bulk-draft" checked>
				<?php esc_html_e( 'Create as draft (recommended for bulk runs)', 'automated-blog-content-creator' ); ?>
				<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Draft mode lets you review all generated posts before publishing. Strongly recommended when generating multiple posts at once.', 'automated-blog-content-creator' ) ) ); ?>
			</label>
		</div>

		<button type="button" id="abcc-bulk-start" class="button button-primary" disabled>
			<?php esc_html_e( 'Generate 0 Posts', 'automated-blog-content-creator' ); ?>
		</button>
	</div>

	<div id="abcc-bulk-progress" class="abcc-bulk-progress" style="display:none;">
		<h3><?php esc_html_e( 'Progress', 'automated-blog-content-creator' ); ?></h3>
		<table class="widefat abcc-bulk-log">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Keyword', 'automated-blog-content-creator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'automated-blog-content-creator' ); ?></th>
					<th><?php esc_html_e( 'Result', 'automated-blog-content-creator' ); ?></th>
				</tr>
			</thead>
			<tbody id="abcc-bulk-log-body"></tbody>
		</table>
	</div>
</div>
