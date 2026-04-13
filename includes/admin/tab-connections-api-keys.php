<?php
/**
 * Connections sub-tab: API Keys & Connections
 *
 * @package WP-AutoInsight
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_model    = abcc_get_setting( 'prompt_select', '' );
$model_options    = abcc_get_ai_model_options();
$wp_connectors_on = abcc_wp_ai_client_available();

// Build provider lists: text providers first, then image-only.
$text_providers  = array_filter( abcc_get_provider_ids(), 'abcc_provider_supports_text_generation' );
$image_providers = array_filter(
	abcc_get_provider_ids(),
	function ( $p ) {
		return abcc_provider_supports_image_generation( $p ) && ! abcc_provider_supports_text_generation( $p );
	}
);
?>
<div class="tab-pane active">
	<form method="post" action="">
		<?php wp_nonce_field( 'abcc_openai_generate_post', 'abcc_openai_nonce' ); ?>
		<input type="hidden" name="abcc_subtab" value="api-keys">

		<?php if ( $wp_connectors_on ) : ?>
			<div class="notice notice-info abcc-connectors-banner">
				<p>
					<strong><?php esc_html_e( '★ WordPress AI Connectors is available.', 'automated-blog-content-creator' ); ?></strong>
					<?php esc_html_e( 'Managing keys through Connectors is more secure — they\'re stored by WordPress, not the plugin.', 'automated-blog-content-creator' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-connectors' ) ); ?>">
						<?php esc_html_e( 'Manage at Settings → AI Connectors →', 'automated-blog-content-creator' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Text Generation Providers', 'automated-blog-content-creator' ); ?></h2>

		<?php
		foreach ( $text_providers as $provider_id ) :
			$provider      = abcc_get_provider( $provider_id );
			$connector_key = $wp_connectors_on ? abcc_get_wp_ai_credential( $provider_id ) : null;
			$saved_key     = abcc_get_provider_saved_api_key( $provider_id );
			$has_connector = ! empty( $connector_key );
			$snapshot      = abcc_get_provider_health_snapshot( $provider_id );
			$last_v        = $snapshot['last_check'];
			$source        = $snapshot['source'];

			// Determine status badge.
			if ( 'wp_connector' === $source ) {
				$badge_class = 'abcc-provider-badge--connector';
				$badge_text  = __( 'Via WP Connectors', 'automated-blog-content-creator' );
			} elseif ( 'constant' === $source ) {
				$badge_class = 'abcc-provider-badge--manual';
				$badge_text  = __( 'Via wp-config.php', 'automated-blog-content-creator' );
			} elseif ( ! empty( $saved_key ) ) {
				if ( $last_v && 'verified' === $last_v['status'] ) {
					$badge_class = 'abcc-provider-badge--verified';
					$badge_text  = '✓ ' . $last_v['message'];
				} elseif ( $last_v && 'verified' !== $last_v['status'] ) {
					$badge_class = 'abcc-provider-badge--failed';
					$badge_text  = '✗ ' . $last_v['message'];
				} else {
					$badge_class = 'abcc-provider-badge--manual';
					$badge_text  = __( 'Manual key', 'automated-blog-content-creator' );
				}
			} else {
				$badge_class = 'abcc-provider-badge--none';
				$badge_text  = __( '○ No connection', 'automated-blog-content-creator' );
			}

			// Models for this provider.
			$provider_models = array();
			foreach ( $model_options as $grp ) {
				foreach ( $grp['options'] as $mid => $mdata ) {
					if ( abcc_get_provider_for_model( $mid ) === $provider_id ) {
						$provider_models[ $mid ] = $mdata;
					}
				}
			}
			$const = abcc_get_provider_constant_name( $provider_id );
			?>
			<div class="abcc-provider-card">
				<div class="abcc-provider-card__header">
					<strong class="abcc-provider-name"><?php echo esc_html( $provider['name'] ); ?></strong>
					<span class="abcc-provider-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span>
				</div>

				<?php if ( $has_connector ) : ?>
					<p class="description">
						<?php
						if ( $last_v && 'verified' === $last_v['status'] ) {
							esc_html_e( 'Connection validated recently. Managed by WordPress.', 'automated-blog-content-creator' );
						} else {
							esc_html_e( 'Managed by WordPress. Use Validate to test this connection.', 'automated-blog-content-creator' );
						}
						?>
					</p>
					<details class="abcc-manual-override">
						<summary>
							<?php esc_html_e( 'Use a manual key instead', 'automated-blog-content-creator' ); ?>
							<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'The Connector key always takes priority. This manual key is only used if the Connector is later removed.', 'automated-blog-content-creator' ) ) ); ?>
						</summary>
						<p class="description abcc-override-warning"><?php esc_html_e( 'Not recommended — Connector key takes priority.', 'automated-blog-content-creator' ); ?></p>
						<input type="password" name="<?php echo esc_attr( $provider_id ); ?>_api_key"
							value="<?php echo esc_attr( $saved_key ); ?>" class="regular-text"
							placeholder="<?php esc_attr_e( 'Override key (optional)', 'automated-blog-content-creator' ); ?>">
					</details>
				<?php elseif ( defined( $const ) ) : ?>
					<p><strong><?php esc_html_e( 'API key set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong></p>
				<?php else : ?>
					<table class="form-table abcc-provider-card__form">
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_id ); ?>_api_key">
									<?php
									printf(
										/* translators: %s: provider name */
										esc_html__( '%s API Key', 'automated-blog-content-creator' ),
										esc_html( $provider['name'] )
									);
									?>
									<?php
									if ( ! empty( $provider['help_url'] ) ) :
										echo wp_kses_post(
											abcc_get_tooltip_html(
												sprintf(
												/* translators: %s: URL */
													__( 'Get your key at %s', 'automated-blog-content-creator' ),
													$provider['help_url']
												)
											)
										);
									endif;
									?>
								</label>
							</th>
							<td>
								<input type="password" id="<?php echo esc_attr( $provider_id ); ?>_api_key"
									name="<?php echo esc_attr( $provider_id ); ?>_api_key"
									value="<?php echo esc_attr( $saved_key ); ?>" class="regular-text">
								<?php if ( $last_v ) : ?>
									<span class="api-validation-status <?php echo esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ); ?>" data-provider="<?php echo esc_attr( $provider_id ); ?>">
										<?php echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); ?>
									</span>
								<?php else : ?>
									<span class="api-validation-status" data-provider="<?php echo esc_attr( $provider_id ); ?>"></span>
								<?php endif; ?>
								<button type="button" class="button abcc-validate-key" data-provider="<?php echo esc_attr( $provider_id ); ?>">
									<?php esc_html_e( 'Validate', 'automated-blog-content-creator' ); ?>
								</button>
								<p class="description">
									<?php
									printf(
										/* translators: %s: PHP constant name */
										esc_html__( 'For extra security, add to wp-config.php: define(\'%s\', \'your-key\');', 'automated-blog-content-creator' ),
										esc_html( $const )
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ( abcc_provider_supports_citations( $provider_id ) ) : ?>
					<table class="form-table abcc-provider-card__form">
						<tr>
							<th scope="row">
								<label for="abcc_perplexity_citation_style"><?php esc_html_e( 'Citation Style', 'automated-blog-content-creator' ); ?></label>
								<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'How source citations from Perplexity appear in generated posts.', 'automated-blog-content-creator' ) ) ); ?>
							</th>
							<td>
								<select id="abcc_perplexity_citation_style" name="abcc_perplexity_citation_style" data-autosave-key="abcc_perplexity_citation_style">
									<option value="inline" <?php selected( abcc_get_setting( 'abcc_perplexity_citation_style', 'inline' ), 'inline' ); ?>><?php esc_html_e( 'Inline hyperlinks', 'automated-blog-content-creator' ); ?></option>
									<option value="references" <?php selected( abcc_get_setting( 'abcc_perplexity_citation_style', 'inline' ), 'references' ); ?>><?php esc_html_e( 'References section at bottom', 'automated-blog-content-creator' ); ?></option>
									<option value="both" <?php selected( abcc_get_setting( 'abcc_perplexity_citation_style', 'inline' ), 'both' ); ?>><?php esc_html_e( 'Both inline + references section', 'automated-blog-content-creator' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="abcc_perplexity_recency_filter"><?php esc_html_e( 'Source Recency Filter', 'automated-blog-content-creator' ); ?></label>
								<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Limit Perplexity sources to recent content only.', 'automated-blog-content-creator' ) ) ); ?>
							</th>
							<td>
								<select id="abcc_perplexity_recency_filter" name="abcc_perplexity_recency_filter" data-autosave-key="abcc_perplexity_recency_filter">
									<option value="" <?php selected( abcc_get_setting( 'abcc_perplexity_recency_filter', '' ), '' ); ?>><?php esc_html_e( 'No filter (all time)', 'automated-blog-content-creator' ); ?></option>
									<option value="day" <?php selected( abcc_get_setting( 'abcc_perplexity_recency_filter', '' ), 'day' ); ?>><?php esc_html_e( 'Last 24 hours', 'automated-blog-content-creator' ); ?></option>
									<option value="week" <?php selected( abcc_get_setting( 'abcc_perplexity_recency_filter', '' ), 'week' ); ?>><?php esc_html_e( 'Last week', 'automated-blog-content-creator' ); ?></option>
									<option value="month" <?php selected( abcc_get_setting( 'abcc_perplexity_recency_filter', '' ), 'month' ); ?>><?php esc_html_e( 'Last month', 'automated-blog-content-creator' ); ?></option>
									<option value="year" <?php selected( abcc_get_setting( 'abcc_perplexity_recency_filter', '' ), 'year' ); ?>><?php esc_html_e( 'Last year', 'automated-blog-content-creator' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $provider_models ) ) : ?>
					<div class="abcc-provider-models">
						<strong><?php esc_html_e( 'Models:', 'automated-blog-content-creator' ); ?></strong>
						<div class="abcc-model-radio-group">
							<?php
							foreach ( $provider_models as $mid => $mdata ) :
								$tier_labels = array(
									'1' => 'Economy',
									'2' => 'Standard',
									'3' => 'Premium',
								);
								?>
								<label class="abcc-model-radio-label">
									<input type="radio" name="selected_model" value="<?php echo esc_attr( $mid ); ?>"
										<?php checked( $current_model, $mid ); ?>>
									<span class="abcc-model-radio-name"><?php echo esc_html( $mdata['name'] ); ?></span>
									<span class="abcc-model-radio-cost">
										<?php echo esc_html( $tier_labels[ $mdata['cost_tier'] ] ?? '' ); ?>
										<?php if ( ! empty( $mdata['cost_per_post'] ) ) : ?>
											&bull; ~$<?php echo esc_html( number_format( $mdata['cost_per_post'], 4 ) ); ?>/post
										<?php endif; ?>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

			<?php if ( ! empty( $image_providers ) ) : ?>
				<h2><?php esc_html_e( 'Image-only Providers', 'automated-blog-content-creator' ); ?></h2>
				<?php
				foreach ( $image_providers as $provider_id ) :
					$provider  = abcc_get_provider( $provider_id );
					$saved_key = abcc_get_provider_saved_api_key( $provider_id );
					$snapshot  = abcc_get_provider_health_snapshot( $provider_id );
					$last_v    = $snapshot['last_check'];
					$source    = $snapshot['source'];
					$const     = abcc_get_provider_constant_name( $provider_id );
					?>
					<div class="abcc-provider-card">
					<div class="abcc-provider-card__header">
						<strong class="abcc-provider-name"><?php echo esc_html( $provider['name'] ); ?></strong>
						<span class="description">
							<?php esc_html_e( '(Image generation only)', 'automated-blog-content-creator' ); ?>
							<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Stability AI is used only for featured images and infographics, not text generation.', 'automated-blog-content-creator' ) ) ); ?>
						</span>
					</div>
						<?php if ( 'constant' === $source ) : ?>
							<p><strong><?php esc_html_e( 'API key set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong></p>
						<?php else : ?>
						<input type="password" id="<?php echo esc_attr( $provider_id ); ?>_api_key"
							name="<?php echo esc_attr( $provider_id ); ?>_api_key"
							value="<?php echo esc_attr( $saved_key ); ?>" class="regular-text"
							placeholder="<?php esc_attr_e( 'API Key', 'automated-blog-content-creator' ); ?>">
							<?php if ( $last_v ) : ?>
							<span class="api-validation-status <?php echo esc_attr( 'verified' === $last_v['status'] ? 'verified' : 'failed' ); ?>" data-provider="<?php echo esc_attr( $provider_id ); ?>">
								<?php echo esc_html( ( 'verified' === $last_v['status'] ? '✓ ' : '✗ ' ) . $last_v['message'] ); ?>
							</span>
						<?php else : ?>
							<span class="api-validation-status" data-provider="<?php echo esc_attr( $provider_id ); ?>"></span>
						<?php endif; ?>
						<button type="button" class="button abcc-validate-key" data-provider="<?php echo esc_attr( $provider_id ); ?>">
							<?php esc_html_e( 'Validate', 'automated-blog-content-creator' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php submit_button( __( 'Save Connection Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
