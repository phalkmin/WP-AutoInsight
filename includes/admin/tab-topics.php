<?php
/**
 * Tab: Topics — Topic Library list table and add/edit form.
 *
 * @package WP-AutoInsight
 * @since 4.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../class-abcc-topics-list-table.php';

$abcc_topics_table = new ABCC_Topics_List_Table();
$abcc_topics_table->prepare_items();

$abcc_model_options = abcc_get_available_text_model_options();
?>

<div class="abcc-topics-tab">
	<h2><?php esc_html_e( 'Topic Library', 'automated-blog-content-creator' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Topics are reusable prompts that generate posts on their own schedule. Each topic runs independently — pause, resume, or trigger them manually at any time.', 'automated-blog-content-creator' ); ?>
	</p>

	<div class="abcc-topic-form-wrap" id="abcc-topic-form-wrap">
		<h3 id="abcc-topic-form-title"><?php esc_html_e( 'Add Topic', 'automated-blog-content-creator' ); ?></h3>
		<form id="abcc-topic-form">
			<input type="hidden" id="abcc-topic-id" value="">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="abcc-topic-title"><?php esc_html_e( 'Topic name', 'automated-blog-content-creator' ); ?></label></th>
					<td><input type="text" id="abcc-topic-title" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-prompt"><?php esc_html_e( 'Prompt', 'automated-blog-content-creator' ); ?></label></th>
					<td><textarea id="abcc-topic-prompt" class="large-text" rows="4" required></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-keywords"><?php esc_html_e( 'Focus keywords', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<textarea id="abcc-topic-keywords" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'One per line. Leave empty to use the topic name.', 'automated-blog-content-creator' ); ?>"></textarea>
						<p class="description"><?php esc_html_e( 'Used for {keyword} and the SEO focus keyword. The topic name is only a label when keywords are set.', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-frequency"><?php esc_html_e( 'Frequency', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-frequency">
							<option value="hourly"><?php esc_html_e( 'Hourly', 'automated-blog-content-creator' ); ?></option>
							<option value="every_2h"><?php esc_html_e( 'Every 2 hours', 'automated-blog-content-creator' ); ?></option>
							<option value="every_6h"><?php esc_html_e( 'Every 6 hours', 'automated-blog-content-creator' ); ?></option>
							<option value="daily" selected><?php esc_html_e( 'Daily', 'automated-blog-content-creator' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Weekly', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-status-override"><?php esc_html_e( 'Post status', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-status-override">
							<option value=""><?php echo esc_html( sprintf( /* translators: %s: Draft or Publish */ __( 'Use global setting (currently: %s)', 'automated-blog-content-creator' ), 'publish' === abcc_get_setting( 'abcc_default_post_status', 'draft' ) ? __( 'Publish', 'automated-blog-content-creator' ) : __( 'Draft', 'automated-blog-content-creator' ) ) ); ?></option>
							<option value="draft"><?php esc_html_e( 'Always draft', 'automated-blog-content-creator' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Always publish', 'automated-blog-content-creator' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-provider-override"><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-provider-override">
							<option value=""><?php esc_html_e( 'Use global model', 'automated-blog-content-creator' ); ?></option>
							<?php foreach ( $abcc_model_options as $abcc_provider_data ) : ?>
								<optgroup label="<?php echo esc_attr( $abcc_provider_data['group'] ); ?>">
									<?php foreach ( $abcc_provider_data['options'] as $abcc_model_id => $abcc_model_data ) : ?>
										<option value="<?php echo esc_attr( $abcc_model_id ); ?>"><?php echo esc_html( abcc_format_model_option_label( $abcc_model_id, $abcc_model_data ) ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary" id="abcc-topic-save"><?php esc_html_e( 'Save Topic', 'automated-blog-content-creator' ); ?></button>
				<button type="button" class="button" id="abcc-topic-cancel" style="display:none;"><?php esc_html_e( 'Cancel', 'automated-blog-content-creator' ); ?></button>
				<span class="abcc-topic-form-feedback" id="abcc-topic-form-feedback" aria-live="polite"></span>
			</p>
		</form>
	</div>

	<?php $abcc_topics_table->display(); ?>
</div>
