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
$health_rows      = get_option( 'abcc_provider_health', array() );

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

		<h2>
			<?php esc_html_e( 'Text Generation Providers', 'automated-blog-content-creator' ); ?>
			<a href="https://wpautoinsight.phalkmin.me/setup/api-keys/" target="_blank" rel="noopener" class="abcc-docs-link">
				<?php esc_html_e( 'Learn more →', 'automated-blog-content-creator' ); ?>
			</a>
		</h2>

		<?php
		foreach ( $text_providers as $provider_id ) :
			$provider      = abcc_get_provider( $provider_id );
			$connector_key = $wp_connectors_on ? abcc_get_wp_ai_credential( $provider_id ) : null;
			$saved_key     = abcc_get_provider_saved_api_key( $provider_id );
			$has_connector = ! empty( $connector_key );
			$snapshot      = abcc_get_provider_health_snapshot( $provider_id, $health_rows );
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
					<?php if ( ! empty( $saved_key ) ) : ?>
					<details class="abcc-manual-override">
						<summary>
							<?php esc_html_e( 'Use a manual key instead', 'automated-blog-content-creator' ); ?>
							<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'WordPress manages this provider via Connectors. The value stored by an earlier plugin version is shown for reference only.', 'automated-blog-content-creator' ) ) ); ?>
						</summary>
						<p class="description abcc-override-warning">
							<?php esc_html_e( 'WordPress manages this provider via Connectors. A value stored by an earlier plugin version is shown for reference only.', 'automated-blog-content-creator' ); ?>
						</p>
						<input type="password" value="" class="regular-text" disabled readonly
							aria-readonly="true"
							placeholder="<?php esc_attr_e( 'Stored by an earlier version', 'automated-blog-content-creator' ); ?>">
					</details>
					<?php endif; ?>
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
									value="" class="regular-text"
									autocomplete="off"
									placeholder="<?php echo esc_attr( ! empty( $saved_key ) ? __( 'Saved — enter a new key to replace it', 'automated-blog-content-creator' ) : __( 'API Key', 'automated-blog-content-creator' ) ); ?>">
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
								<?php if ( 'option' === $source ) : ?>
									<button type="submit" name="abcc_remove_key" value="<?php echo esc_attr( $provider_id ); ?>" class="button-link-delete abcc-remove-key">
										<?php esc_html_e( 'Remove saved key', 'automated-blog-content-creator' ); ?>
									</button>
								<?php endif; ?>
								<details class="abcc-advanced-tip">
									<summary><?php esc_html_e( 'Advanced: store your key in wp-config.php', 'automated-blog-content-creator' ); ?></summary>
									<p class="description">
										<?php
										printf(
											/* translators: %s: PHP constant name */
											esc_html__( 'For extra security, add to wp-config.php: define(\'%s\', \'your-key\');', 'automated-blog-content-creator' ),
											esc_html( $const )
										);
										?>
									</p>
								</details>
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
									<span class="abcc-model-radio-name"><?php echo esc_html( abcc_format_model_option_label( $mid, $mdata ) ); ?></span>
									<?php
									if ( ! empty( $mdata['cost_warning'] ) ) :
										?>
										<span class="abcc-cost-warning description"><?php esc_html_e( 'Cost varies significantly per query — this model can be expensive.', 'automated-blog-content-creator' ); ?></span><?php endif; ?>
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

		<?php
		// Fallback provider: one secondary slot, chosen from providers with a key
		// other than the primary's. Suggested (not saved) when nothing is set.
		$fallback_chain         = abcc_get_fallback_chain();
		$fallback_saved         = ! empty( $fallback_chain[0] ) ? $fallback_chain[0] : array(
			'provider' => '',
			'model'    => '',
		);
		$fallback_primary       = abcc_get_provider_for_model( $current_model );
		$fallback_choices       = array();
		$fallback_is_suggestion = false;

		foreach ( $model_options as $fb_pid => $fb_group ) {
			if ( $fb_pid === $fallback_primary ) {
				continue;
			}
			$fb_models = array();
			foreach ( $fb_group['options'] as $fb_mid => $fb_mdata ) {
				$fb_models[ $fb_mid ] = abcc_format_model_option_label( $fb_mid, $fb_mdata );
			}
			$fallback_choices[ $fb_pid ] = array(
				'name'   => abcc_get_provider( $fb_pid )['name'],
				'models' => $fb_models,
			);
		}

		if ( '' === $fallback_saved['provider'] && ! empty( $fallback_choices ) ) {
			$fb_first               = array_keys( $fallback_choices )[0];
			$fallback_saved         = array(
				'provider' => $fb_first,
				'model'    => abcc_get_provider_default_model( $fb_first ),
			);
			$fallback_is_suggestion = true;
		}

		$fallback_models_map = array();
		foreach ( $fallback_choices as $fb_pid => $fb_choice ) {
			$fallback_models_map[ $fb_pid ] = $fb_choice['models'];
		}
		$fallback_selected_models = isset( $fallback_choices[ $fallback_saved['provider'] ] )
			? $fallback_choices[ $fallback_saved['provider'] ]['models']
			: array();
		?>
		<div class="abcc-provider-card abcc-fallback-card">
			<div class="abcc-provider-card__header">
				<strong class="abcc-provider-name"><?php esc_html_e( 'If my primary provider fails, also try…', 'automated-blog-content-creator' ); ?></strong>
				<?php echo wp_kses_post( abcc_get_tooltip_html( __( 'Used only for text generation. Images keep their own provider settings.', 'automated-blog-content-creator' ) ) ); ?>
			</div>
			<?php if ( empty( $fallback_choices ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Add a second provider\'s key above to enable fallback. When the primary hits a rate limit, an outage, or a network error, the post is retried once on the fallback.', 'automated-blog-content-creator' ); ?>
				</p>
			<?php else : ?>
				<div class="abcc-fallback-row">
					<label for="abcc_fallback_provider"><?php esc_html_e( 'Provider', 'automated-blog-content-creator' ); ?></label>
					<select id="abcc_fallback_provider" name="abcc_fallback_provider">
						<option value="" <?php selected( '', $fallback_saved['provider'] ); ?>><?php esc_html_e( 'Nothing — fail immediately', 'automated-blog-content-creator' ); ?></option>
						<?php foreach ( $fallback_choices as $fb_pid => $fb_choice ) : ?>
							<option value="<?php echo esc_attr( $fb_pid ); ?>" <?php selected( $fb_pid, $fallback_saved['provider'] ); ?>><?php echo esc_html( $fb_choice['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="abcc-fallback-model" <?php echo empty( $fallback_selected_models ) ? 'style="display:none"' : ''; ?>>
						<label for="abcc_fallback_model"><?php esc_html_e( 'Model', 'automated-blog-content-creator' ); ?></label>
						<select id="abcc_fallback_model" name="abcc_fallback_model"
							data-models="<?php echo esc_attr( wp_json_encode( $fallback_models_map ) ); ?>"
							<?php disabled( empty( $fallback_selected_models ) ); ?>>
							<?php foreach ( $fallback_selected_models as $fb_mid => $fb_label ) : ?>
								<option value="<?php echo esc_attr( $fb_mid ); ?>" <?php selected( $fb_mid, $fallback_saved['model'] ); ?>><?php echo esc_html( $fb_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</span>
				</div>
				<?php if ( $fallback_is_suggestion ) : ?>
					<p class="description"><?php esc_html_e( 'Suggested from your saved keys — nothing is active until you save.', 'automated-blog-content-creator' ); ?></p>
				<?php endif; ?>
				<p class="description">
					<?php esc_html_e( 'Fallback kicks in for rate limits, provider outages, and network errors. It does not retry invalid keys or prompts that are too long — those need your attention.', 'automated-blog-content-creator' ); ?>
				</p>
			<?php endif; ?>
		</div>

			<?php if ( ! empty( $image_providers ) ) : ?>
				<h2><?php esc_html_e( 'Image-only Providers', 'automated-blog-content-creator' ); ?></h2>
				<?php
				foreach ( $image_providers as $provider_id ) :
					$provider  = abcc_get_provider( $provider_id );
					$saved_key = abcc_get_provider_saved_api_key( $provider_id );
					$snapshot  = abcc_get_provider_health_snapshot( $provider_id, $health_rows );
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
							value="" class="regular-text"
							autocomplete="off"
							placeholder="<?php echo esc_attr( ! empty( $saved_key ) ? __( 'Saved — enter a new key to replace it', 'automated-blog-content-creator' ) : __( 'API Key', 'automated-blog-content-creator' ) ); ?>">
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
							<?php if ( 'option' === $source ) : ?>
							<button type="submit" name="abcc_remove_key" value="<?php echo esc_attr( $provider_id ); ?>" class="button-link-delete abcc-remove-key">
								<?php esc_html_e( 'Remove saved key', 'automated-blog-content-creator' ); ?>
							</button>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php submit_button( __( 'Save Connection Settings', 'automated-blog-content-creator' ) ); ?>
	</form>
</div>
