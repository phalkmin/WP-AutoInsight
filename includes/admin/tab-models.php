<?php
/**
 * Tab: AI Models
 *
 * @package WP-AutoInsight
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="tab-pane active">
	<form method="post" action="">
				<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<p class="description"><?php esc_html_e( 'Select the AI model for content generation.', 'automated-blog-content-creator' ); ?><?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Higher-tier models produce richer, more accurate content but cost more per API call. Economy models are great for drafts; Premium for final-quality output.', 'automated-blog-content-creator' ) ) ); ?></p>
		<div class="model-selector">
				<?php
				$model_options          = abcc_get_ai_model_options();
				$current_selected_model = abcc_get_setting( 'prompt_select', 'gpt-4.1-mini-2025-04-14' );
				foreach ( $model_options as $provider => $provider_data ) :
					?>
				<div class="model-provider-section">
					<h3><?php echo esc_html( $provider_data['group'] ); ?></h3>
					<div class="model-cards">
						<?php foreach ( $provider_data['options'] as $model_id => $model_data ) : ?>
							<div class="model-card cost-tier-<?php echo esc_attr( $model_data['cost_tier'] ); ?>">
								<input type="radio" name="selected_model" id="model_<?php echo esc_attr( $model_id ); ?>" value="<?php echo esc_attr( $model_id ); ?>" <?php checked( $current_selected_model, $model_id ); ?>>
								<label for="model_<?php echo esc_attr( $model_id ); ?>">
									<h4><?php echo esc_html( $model_data['name'] ); ?></h4>
									<p><?php echo esc_html( $model_data['description'] ); ?></p>
									<div class="cost-indicator">
										<?php
										$cost_levels = array(
											'1' => 'Economy',
											'2' => 'Standard',
											'3' => 'Premium',
										);
										echo esc_html( $cost_levels[ $model_data['cost_tier'] ] );

										if ( ! empty( $model_data['cost_per_post'] ) ) {
											printf( ' &bull; ~$%s per post', esc_html( number_format( $model_data['cost_per_post'], 4 ) ) );
										}
										?>
									</div>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
		</div>
				<?php submit_button( __( 'Save Model Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
