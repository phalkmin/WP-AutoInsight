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
$abcc_char_limit  = (int) abcc_get_setting( 'openai_char_limit', 200 );
$abcc_cost_legend = abcc_get_length_cost_text( $abcc_char_limit, $current_model );

/**
 * Status span + Validate button for one provider.
 *
 * @param string     $provider_id Provider ID.
 * @param array|null $last_v      Last validation record.
 */
$abcc_render_validate_control = static function ( $provider_id, $last_v ) {
	?>
	<p class="abcc-validate-row">
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
	</p>
	<?php
};


// Build provider lists: text providers first, then image-only. The custom
// endpoint has its own card.
$text_providers  = array_filter(
	abcc_get_provider_ids(),
	function ( $p ) {
		$config = abcc_get_provider( $p );
		return abcc_provider_supports_text_generation( $p ) && empty( $config['custom_endpoint'] );
	}
);
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
					<?php esc_html_e( 'Managing keys through Connectors is more secure — they\'re stored by WordPress, not the plugin. Connectors needs the matching provider plugin installed (Settings → Connectors → Install).', 'automated-blog-content-creator' ); ?>
					<a href="<?php echo esc_url( abcc_get_connectors_url() ); ?>">
						<?php esc_html_e( 'Manage at Settings → Connectors →', 'automated-blog-content-creator' ); ?>
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

		<p class="description">
			<?php
			printf(
				/* translators: %s: word count */
				esc_html__( 'Costs next to each model are estimates for one post at your current length (about %s words).', 'automated-blog-content-creator' ),
				esc_html( number_format_i18n( $abcc_cost_legend['words'] ) )
			);
			echo ' ' . esc_html( $abcc_cost_legend['detail'] );
			?>
		</p>

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
			$badge_kind = 'validation';
			if ( 'wp_connector' === $source ) {
				$badge_class = 'abcc-provider-badge--connector';
				$badge_text  = __( 'Via WP Connectors', 'automated-blog-content-creator' );
				$badge_kind  = 'source';
			} elseif ( 'constant' === $source ) {
				$badge_class = 'abcc-provider-badge--manual';
				$badge_text  = __( 'Via wp-config.php', 'automated-blog-content-creator' );
				$badge_kind  = 'source';
			} elseif ( ! empty( $saved_key ) ) {
				if ( $last_v && 'verified' === $last_v['status'] ) {
					$badge_class = 'abcc-provider-badge--verified';
					$badge_text  = '✓ ' . __( 'Verified', 'automated-blog-content-creator' );
				} elseif ( $last_v && 'verified' !== $last_v['status'] ) {
					$badge_class = 'abcc-provider-badge--failed';
					$badge_text  = '✗ ' . __( 'Validation failed', 'automated-blog-content-creator' );
				} else {
					$badge_class = 'abcc-provider-badge--manual';
					$badge_text  = __( 'Manual key', 'automated-blog-content-creator' );
				}
			} else {
				$badge_class = 'abcc-provider-badge--none';
				$badge_text  = __( '○ No connection', 'automated-blog-content-creator' );
			}

			// Models for this provider.
			$provider_models = isset( $model_options[ $provider_id ]['options'] ) ? (array) $model_options[ $provider_id ]['options'] : array();
			$const           = abcc_get_provider_constant_name( $provider_id );
			?>
			<div class="abcc-provider-card">
				<div class="abcc-provider-card__header">
					<strong class="abcc-provider-name"><?php echo esc_html( $provider['name'] ); ?></strong>
					<span class="abcc-provider-badge <?php echo esc_attr( $badge_class ); ?>" data-provider="<?php echo esc_attr( $provider_id ); ?>" data-badge-kind="<?php echo esc_attr( $badge_kind ); ?>"><?php echo esc_html( $badge_text ); ?></span>
				</div>

				<?php if ( $has_connector ) : ?>
					<p class="description">
						<?php
						if ( $last_v && 'verified' === $last_v['status'] ) {
							esc_html_e( 'Connection validated recently. Managed by WordPress.', 'automated-blog-content-creator' );
						} else {
							esc_html_e( 'Managed by WordPress. Use Validate to test this connection.', 'automated-blog-content-creator' );
						}
						if ( ! empty( $const ) && defined( $const ) ) {
							echo ' ';
							printf(
								/* translators: %s: wp-config constant name */
								esc_html__( 'The %s constant in wp-config.php is ignored while this Connectors key exists.', 'automated-blog-content-creator' ),
								'<code>' . esc_html( $const ) . '</code>'
							);
						}
						?>
					</p>
					<?php $abcc_render_validate_control( $provider_id, $last_v ); ?>
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
					<?php $abcc_render_validate_control( $provider_id, $last_v ); ?>
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

				<?php if ( abcc_provider_supports_citations( $provider_id ) && '' !== (string) abcc_get_provider_api_key( $provider_id ) ) : ?>
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
									<input type="radio" name="selected_model" value="<?php echo esc_attr( abcc_build_model_selection( $provider_id, $mid ) ); ?>"
										<?php checked( abcc_get_live_model_selection( $current_model ), abcc_build_model_selection( $provider_id, $mid ) ); ?>>
									<span class="abcc-model-radio-name"><?php echo esc_html( abcc_format_model_option_label( $mid, $mdata ) ); ?></span>
									<?php
									if ( ! empty( $mdata['cost_warning'] ) ) :
										?>
										<span class="abcc-cost-warning description"><?php esc_html_e( 'Cost varies significantly per query — this model can be expensive.', 'automated-blog-content-creator' ); ?></span><?php endif; ?>
									<span class="abcc-model-radio-cost">
										<?php
										$abcc_model_cost = abcc_get_length_cost_text( $abcc_char_limit, abcc_build_model_selection( $provider_id, $mid ) );
										if ( isset( $mdata['cost_tier'], $tier_labels[ $mdata['cost_tier'] ] ) ) {
											echo esc_html( $tier_labels[ $mdata['cost_tier'] ] ) . ' &bull; ';
										}
										if ( null === $abcc_model_cost['cost'] ) {
											esc_html_e( 'cost unknown', 'automated-blog-content-creator' );
										} elseif ( 0.0 === (float) $abcc_model_cost['cost'] ) {
											esc_html_e( 'free', 'automated-blog-content-creator' );
										} else {
											printf(
												/* translators: %s: estimated cost of one post in US dollars */
												esc_html__( '≈$%s per post', 'automated-blog-content-creator' ),
												esc_html( abcc_format_cost_amount( $abcc_model_cost['cost'] ) )
											);
										}
										?>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== (string) abcc_get_provider_api_key( $provider_id ) && '' !== abcc_get_provider_transport( $provider_id ) && empty( $provider['single_model'] ) ) : ?>
					<?php
					$abcc_is_gateway    = ! empty( $provider['gateway'] );
					$abcc_custom_models = abcc_get_custom_models( $provider_id );
					?>
					<details class="abcc-custom-models" data-provider="<?php echo esc_attr( $provider_id ); ?>" <?php echo ! empty( $abcc_custom_models ) ? 'open' : ''; ?>>
						<summary>
							<?php
							if ( $abcc_is_gateway ) {
								esc_html_e( 'Find another model in the catalog', 'automated-blog-content-creator' );
							} else {
								esc_html_e( 'Advanced: add a model by ID', 'automated-blog-content-creator' );
							}
							?>
						</summary>

						<?php if ( $abcc_is_gateway ) : ?>
							<p class="description"><?php echo esc_html( abcc_get_openrouter_free_note() ); ?></p>
							<p>
								<label class="screen-reader-text" for="abcc-openrouter-search"><?php esc_html_e( 'Search OpenRouter models', 'automated-blog-content-creator' ); ?></label>
								<input type="search" id="abcc-openrouter-search" class="regular-text abcc-openrouter-search" autocomplete="off"
									placeholder="<?php esc_attr_e( 'Search by name or ID, e.g. llama or deepseek', 'automated-blog-content-creator' ); ?>">
							</p>
							<ul class="abcc-openrouter-results" aria-live="polite"></ul>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'For a model the provider released before this plugin listed it. The ID must match the provider\'s API exactly; a wrong ID fails with a "model not found" error. No price is known for models added here, so their cost shows as unknown.', 'automated-blog-content-creator' ); ?></p>
							<p class="abcc-custom-model-fields">
								<label><?php esc_html_e( 'Model ID', 'automated-blog-content-creator' ); ?>
									<input type="text" class="regular-text abcc-custom-model-id" autocomplete="off" spellcheck="false"></label>
								<label><?php esc_html_e( 'Display name', 'automated-blog-content-creator' ); ?>
									<input type="text" class="abcc-custom-model-name" autocomplete="off"></label>
								<label><?php esc_html_e( 'Context window (tokens)', 'automated-blog-content-creator' ); ?>
									<input type="number" class="small-text abcc-custom-model-context" min="1024" step="1024" value="128000"></label>
								<label><?php esc_html_e( 'Max output (tokens)', 'automated-blog-content-creator' ); ?>
									<input type="number" class="small-text abcc-custom-model-output" min="256" step="256" value="8192"></label>
								<button type="button" class="button abcc-custom-model-add"><?php esc_html_e( 'Add model', 'automated-blog-content-creator' ); ?></button>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $abcc_custom_models ) ) : ?>
							<ul class="abcc-custom-model-list">
								<?php foreach ( $abcc_custom_models as $abcc_custom_id => $abcc_custom_def ) : ?>
									<li>
										<strong><?php echo esc_html( abcc_format_model_option_label( $abcc_custom_id, $abcc_custom_def ) ); ?></strong>
										<code><?php echo esc_html( $abcc_custom_id ); ?></code>
										<button type="button" class="button-link button-link-delete abcc-custom-model-remove" data-model-id="<?php echo esc_attr( $abcc_custom_id ); ?>">
											<?php esc_html_e( 'Remove', 'automated-blog-content-creator' ); ?>
										</button>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<p class="abcc-custom-model-feedback" aria-live="polite"></p>
					</details>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php
		$abcc_custom          = abcc_get_custom_endpoint_settings();
		$abcc_custom_provider = abcc_get_provider( 'custom' );
		$abcc_custom_can_edit = abcc_current_user_can_manage_custom_endpoint();
		$abcc_custom_ready    = abcc_provider_supports_text_generation( 'custom' );
		$abcc_custom_snapshot = abcc_get_provider_health_snapshot( 'custom', $health_rows );
		$abcc_custom_has_key  = '' !== (string) abcc_get_provider_saved_api_key( 'custom' );
		$abcc_custom_constant = defined( 'CUSTOM_API' ) && '' !== (string) constant( 'CUSTOM_API' );
		?>
		<div class="abcc-provider-card abcc-custom-endpoint-card">
			<div class="abcc-provider-card__header">
				<strong class="abcc-provider-name"><?php esc_html_e( 'Custom OpenAI-compatible endpoint', 'automated-blog-content-creator' ); ?></strong>
				<span class="description"><?php esc_html_e( '(advanced)', 'automated-blog-content-creator' ); ?></span>
			</div>
			<p class="description">
				<?php esc_html_e( 'For Ollama, Groq, DeepSeek, Mistral, Together and other services that speak the OpenAI chat API. You are responsible for this provider\'s terms and for how it handles the prompts and posts sent to it.', 'automated-blog-content-creator' ); ?>
			</p>

			<?php if ( ! $abcc_custom_can_edit ) : ?>
				<p><em><?php esc_html_e( 'Only a network administrator can change the custom endpoint.', 'automated-blog-content-creator' ); ?></em></p>
				<?php if ( $abcc_custom_ready ) : ?>
					<p><?php esc_html_e( 'Endpoint:', 'automated-blog-content-creator' ); ?> <code><?php echo esc_html( $abcc_custom['base_url'] ); ?></code></p>
				<?php endif; ?>
			<?php else : ?>
				<table class="form-table abcc-provider-card__form">
					<tr>
						<th scope="row"><label for="abcc_custom_endpoint_base_url"><?php esc_html_e( 'Base URL', 'automated-blog-content-creator' ); ?></label></th>
						<td>
							<input type="url" id="abcc_custom_endpoint_base_url" name="abcc_custom_endpoint[base_url]" class="regular-text code"
								value="<?php echo esc_attr( $abcc_custom['base_url'] ); ?>" autocomplete="off" spellcheck="false"
								placeholder="https://api.example.com/v1">
							<p class="description"><?php esc_html_e( 'The address that ends before /chat/completions. It must start with https:// unless it is a local endpoint. WordPress only allows public endpoints on ports 443 and 8080. Leave empty to turn the custom endpoint off.', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Local endpoint', 'automated-blog-content-creator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="abcc_custom_endpoint[allow_local]" value="1" <?php checked( $abcc_custom['allow_local'] ); ?>>
								<?php esc_html_e( 'Allow an address on this server or its private network', 'automated-blog-content-creator' ); ?>
							</label>
							<p class="description"><?php esc_html_e( '"localhost" means the server WordPress runs on, not your own computer. A local endpoint may use http:// and may run without a key.', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="abcc_custom_endpoint_model"><?php esc_html_e( 'Model ID', 'automated-blog-content-creator' ); ?></label></th>
						<td>
							<input type="text" id="abcc_custom_endpoint_model" name="abcc_custom_endpoint[model]" class="regular-text code"
								value="<?php echo esc_attr( $abcc_custom['model'] ); ?>" autocomplete="off" spellcheck="false" placeholder="llama3.1">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="abcc_custom_endpoint_context"><?php esc_html_e( 'Context window (tokens)', 'automated-blog-content-creator' ); ?></label></th>
						<td>
							<input type="number" id="abcc_custom_endpoint_context" name="abcc_custom_endpoint[context_window]" class="small-text"
								min="1024" step="1024" value="<?php echo esc_attr( $abcc_custom['context_window'] ); ?>">
							<label for="abcc_custom_endpoint_output" class="abcc-inline-label"><?php esc_html_e( 'Max output (tokens)', 'automated-blog-content-creator' ); ?></label>
							<input type="number" id="abcc_custom_endpoint_output" name="abcc_custom_endpoint[max_output]" class="small-text"
								min="256" step="256" value="<?php echo esc_attr( $abcc_custom['max_output'] ); ?>">
							<p class="description"><?php esc_html_e( 'Check the model\'s documentation. Numbers that are too high make long posts fail; numbers that are too low cut them short.', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="custom_api_key"><?php esc_html_e( 'API key', 'automated-blog-content-creator' ); ?></label></th>
						<td>
							<?php if ( $abcc_custom_constant ) : ?>
								<strong><?php esc_html_e( 'API key set in wp-config.php.', 'automated-blog-content-creator' ); ?></strong>
							<?php else : ?>
								<input type="password" id="custom_api_key" name="custom_api_key" value="" class="regular-text" autocomplete="off"
									placeholder="<?php echo esc_attr( $abcc_custom_has_key ? __( 'Saved — enter a new key to replace it', 'automated-blog-content-creator' ) : __( 'API key (optional for a local endpoint)', 'automated-blog-content-creator' ) ); ?>">
								<?php if ( $abcc_custom_has_key ) : ?>
									<button type="submit" name="abcc_remove_key" value="custom" class="button-link-delete abcc-remove-key">
										<?php esc_html_e( 'Remove saved key', 'automated-blog-content-creator' ); ?>
									</button>
								<?php endif; ?>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'The key is sent only to the address above. Redirects are not followed, so it cannot be passed on to another host.', 'automated-blog-content-creator' ); ?></p>
						</td>
					</tr>
				</table>
			<?php endif; ?>

			<?php if ( $abcc_custom_ready ) : ?>
				<?php $abcc_render_validate_control( 'custom', $abcc_custom_snapshot['last_check'] ); ?>
				<p class="description"><?php esc_html_e( 'Validate checks three things in order: the endpoint answers, it accepts the key, and the model returns text.', 'automated-blog-content-creator' ); ?></p>
				<?php if ( abcc_provider_is_ready( 'custom' ) ) : ?>
					<div class="abcc-provider-models">
						<strong><?php esc_html_e( 'Models:', 'automated-blog-content-creator' ); ?></strong>
						<div class="abcc-model-radio-group">
							<label class="abcc-model-radio-label">
								<input type="radio" name="selected_model" value="<?php echo esc_attr( abcc_build_model_selection( 'custom', $abcc_custom['model'] ) ); ?>"
									<?php checked( abcc_get_live_model_selection( $current_model ), abcc_build_model_selection( 'custom', $abcc_custom['model'] ) ); ?>>
								<span class="abcc-model-radio-name"><?php echo esc_html( $abcc_custom['model'] ); ?></span>
								<span class="abcc-model-radio-cost"><?php esc_html_e( 'cost unknown', 'automated-blog-content-creator' ); ?></span>
							</label>
						</div>
					</div>
				<?php elseif ( ! empty( $abcc_custom_provider['endpoint'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'Add the API key to use this endpoint. Only a local endpoint may run without one.', 'automated-blog-content-creator' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php
		// Fallback provider: one secondary slot, chosen from providers with a key
		// other than the primary's. Nothing is preselected; a suggestion sits beside the field.
		$fallback_chain   = abcc_get_fallback_chain();
		$fallback_saved   = ! empty( $fallback_chain[0] ) ? $fallback_chain[0] : array(
			'provider' => '',
			'model'    => '',
		);
		$fallback_primary = abcc_get_provider_for_model( $current_model );
		$fallback_choices = array();

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

		$fallback_suggestion = abcc_get_fallback_suggestion( $fallback_primary, $fallback_choices );

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
				<?php if ( ! empty( $fallback_suggestion ) ) : ?>
					<p class="description abcc-fallback-suggestion">
						<?php
						printf(
							/* translators: 1: provider name, 2: model name */
							esc_html__( 'Suggested from your saved keys: %1$s, %2$s.', 'automated-blog-content-creator' ),
							esc_html( $fallback_choices[ $fallback_suggestion['provider'] ]['name'] ),
							esc_html( $fallback_choices[ $fallback_suggestion['provider'] ]['models'][ $fallback_suggestion['model'] ] )
						);
						?>
						<button type="button" class="button-link abcc-fallback-use-suggestion"
							data-provider="<?php echo esc_attr( $fallback_suggestion['provider'] ); ?>"
							data-model="<?php echo esc_attr( $fallback_suggestion['model'] ); ?>">
							<?php esc_html_e( 'Use this', 'automated-blog-content-creator' ); ?>
						</button>
						<span class="abcc-fallback-suggestion-status" aria-live="polite"></span>
					</p>
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
