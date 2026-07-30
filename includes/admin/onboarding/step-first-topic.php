<?php
/**
 * Onboarding step 3: create your first Topic (optional).
 *
 * Markup partial included by abcc_show_onboarding_page(). Extracted in 4.3.0.
 * Filling in the fields and clicking Continue will create a Topic via the
 * abcc_onboarding_first_topic AJAX action before advancing to step 4.
 * The step is fully skippable — clicking Continue without input advances
 * without creating anything.
 *
 * @package WP-AutoInsight
 * @since 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<div class="abcc-onboarding-step abcc-step-3" data-step="3" style="display:none;">
		<div class="abcc-step-content">
			<h2><?php esc_html_e( 'Create your first Topic (optional)', 'automated-blog-content-creator' ); ?></h2>
			<p><?php esc_html_e( 'Topics let WP-AutoInsight generate posts on a recurring schedule. You can add more anytime in the Topic Library.', 'automated-blog-content-creator' ); ?></p>

			<div class="abcc-onboarding-first-topic-fields">
				<p>
					<label for="abcc-first-topic-title"><strong><?php esc_html_e( 'Topic name', 'automated-blog-content-creator' ); ?></strong></label><br>
					<input
						type="text"
						id="abcc-first-topic-title"
						name="abcc_first_topic_title"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g. Weekly industry roundup', 'automated-blog-content-creator' ); ?>"
					>
				</p>

				<p>
					<label for="abcc-first-topic-prompt"><strong><?php esc_html_e( 'Prompt', 'automated-blog-content-creator' ); ?></strong></label><br>
					<textarea
						id="abcc-first-topic-prompt"
						name="abcc_first_topic_prompt"
						class="large-text"
						rows="3"
						placeholder="<?php esc_attr_e( 'e.g. Write a weekly roundup about {keyword}.', 'automated-blog-content-creator' ); ?>"
					></textarea>
				</p>

				<p>
					<label for="abcc-first-topic-frequency"><strong><?php esc_html_e( 'Frequency', 'automated-blog-content-creator' ); ?></strong></label><br>
					<select id="abcc-first-topic-frequency" name="abcc_first_topic_frequency">
						<option value="hourly"><?php esc_html_e( 'Hourly', 'automated-blog-content-creator' ); ?></option>
						<option value="every_2h"><?php esc_html_e( 'Every 2 hours', 'automated-blog-content-creator' ); ?></option>
						<option value="every_6h"><?php esc_html_e( 'Every 6 hours', 'automated-blog-content-creator' ); ?></option>
						<option value="daily"><?php esc_html_e( 'Daily', 'automated-blog-content-creator' ); ?></option>
						<option value="weekly" selected><?php esc_html_e( 'Weekly', 'automated-blog-content-creator' ); ?></option>
					</select>
				</p>
			</div>

			<div class="abcc-step-actions">
				<button class="button button-link abcc-skip-onboarding" id="abcc-skip-onboarding-3"><?php esc_html_e( 'Skip to settings', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-secondary abcc-prev-step" data-goto="2"><?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?></button>
				<button class="button button-primary abcc-next-step" data-step="3" data-goto="4"><?php esc_html_e( 'Continue', 'automated-blog-content-creator' ); ?></button>
			</div>
		</div>
	</div>
