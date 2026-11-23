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

// One-time flag set by js/topics.js after a save; WordPress strips it from the URL.
$abcc_topic_notice = isset( $_GET['abcc_topic_notice'] ) ? sanitize_key( wp_unslash( $_GET['abcc_topic_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
?>

<div class="abcc-topics-tab">
	<?php if ( 'saved' === $abcc_topic_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Topic saved.', 'automated-blog-content-creator' ); ?></p></div>
	<?php endif; ?>
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
					<td><input type="text" id="abcc-topic-title" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Weekly industry roundup', 'automated-blog-content-creator' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-prompt"><?php esc_html_e( 'Prompt', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<textarea id="abcc-topic-prompt" class="large-text" rows="4" required placeholder="<?php esc_attr_e( 'e.g. Write a practical guide to one aspect of container gardening for beginners.', 'automated-blog-content-creator' ); ?>"></textarea>
						<p class="description"><?php esc_html_e( 'What each post should be about. Every run writes a new post from this prompt, so pick a subject with room for many angles.', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-keywords"><?php esc_html_e( 'Focus keywords', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<textarea id="abcc-topic-keywords" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'One per line. Leave empty to use the topic name.', 'automated-blog-content-creator' ); ?>"></textarea>
						<p class="description"><?php esc_html_e( 'Used for {keyword} and the SEO focus keyword. The topic name is only a label when keywords are set.', 'automated-blog-content-creator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-source-notes"><?php esc_html_e( 'Facts to use', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<textarea id="abcc-topic-source-notes" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Optional. Product details, numbers, dates or links the article may rely on.', 'automated-blog-content-creator' ); ?>"></textarea>
						<p class="description"><?php esc_html_e( 'The model is told to build on these facts and to cite only links that appear here. Without them it is told not to invent statistics, quotes or prices.', 'automated-blog-content-creator' ); ?></p>
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
					<td class="abcc-cost-scope">
						<select id="abcc-topic-provider-override" data-abcc-cost-model>
							<option value=""><?php esc_html_e( 'Use global model', 'automated-blog-content-creator' ); ?></option>
							<?php foreach ( $abcc_model_options as $abcc_provider_id => $abcc_provider_data ) : ?>
								<optgroup label="<?php echo esc_attr( $abcc_provider_data['group'] ); ?>">
									<?php foreach ( $abcc_provider_data['options'] as $abcc_model_id => $abcc_model_data ) : ?>
										<option value="<?php echo esc_attr( abcc_build_model_selection( $abcc_provider_id, $abcc_model_id ) ); ?>"><?php echo esc_html( abcc_format_model_option_label( $abcc_model_id, $abcc_model_data ) ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<?php abcc_render_cost_estimate( (int) abcc_get_setting( 'openai_char_limit', 200 ) ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="abcc-topic-answer-mode"><?php esc_html_e( 'Answer-focused structure', 'automated-blog-content-creator' ); ?></label></th>
					<td>
						<select id="abcc-topic-answer-mode">
							<option value=""><?php echo esc_html( sprintf( /* translators: %s: On or Off */ __( 'Use global setting (currently: %s)', 'automated-blog-content-creator' ), abcc_resolve_answer_mode() ? __( 'On', 'automated-blog-content-creator' ) : __( 'Off', 'automated-blog-content-creator' ) ) ); ?></option>
							<option value="on"><?php esc_html_e( 'On for this topic', 'automated-blog-content-creator' ); ?></option>
							<option value="off"><?php esc_html_e( 'Off for this topic', 'automated-blog-content-creator' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Question headings answered up front, key takeaways and a short FAQ. Good for how-to and reference topics; a poor fit for opinion, news and announcements.', 'automated-blog-content-creator' ); ?></p>
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
