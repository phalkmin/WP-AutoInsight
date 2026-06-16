<?php
/**
 * Onboarding step 1: goal selection.
 *
 * Markup partial included by abcc_show_onboarding_page(). Extracted verbatim
 * in 4.2.0 — bytes must stay identical to the pre-split render.
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<!-- Step 1: Goal Selection -->
			<div class="abcc-onboarding-step abcc-step-1 active">
				<div class="abcc-step-content">
					<h2><?php esc_html_e( 'What\'s your primary goal?', 'automated-blog-content-creator' ); ?></h2>
					<p><?php esc_html_e( 'This helps us configure the perfect settings for your content.', 'automated-blog-content-creator' ); ?></p>
					
					<div class="abcc-goal-grid">
						<?php
						$goals = abcc_get_onboarding_goals();
						foreach ( $goals as $goal_key => $goal_data ) :
							?>
							<div class="abcc-goal-card" data-goal="<?php echo esc_attr( $goal_key ); ?>">
								<div class="abcc-goal-icon">
									<?php echo wp_kses_post( abcc_get_goal_icon( $goal_key ) ); ?>
								</div>
								<h3><?php echo esc_html( $goal_data['title'] ); ?></h3>
								<p><?php echo esc_html( $goal_data['description'] ); ?></p>
								<div class="abcc-goal-features">
									<small>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: tone, 2: content length */
												__( 'Tone: %1$s • Length: %2$s tokens', 'automated-blog-content-creator' ),
												ucfirst( $goal_data['settings']['openai_tone'] ),
												$goal_data['settings']['openai_char_limit']
											)
										);
										?>
									</small>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					
					<div class="abcc-step-actions">
						<button class="button button-primary" id="abcc-next-step-1" disabled>
							<?php esc_html_e( 'Continue', 'automated-blog-content-creator' ); ?>
						</button>
						<button class="button button-link" id="abcc-skip-onboarding">
							<?php esc_html_e( 'Skip setup (for advanced users)', 'automated-blog-content-creator' ); ?>
						</button>
					</div>
				</div>
			</div>
