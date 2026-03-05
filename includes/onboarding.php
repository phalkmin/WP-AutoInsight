<?php
/**
 * Onboarding functionality for WP-AutoInsight
 *
 * @package WP-AutoInsight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Show the onboarding page for new users.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_show_onboarding_page() {
	wp_enqueue_style( 'abcc-onboarding-styles', plugins_url( '/css/onboarding.css', __DIR__ ), array(), ABCC_VERSION );
	wp_enqueue_script( 'abcc-onboarding-scripts', plugins_url( '/js/onboarding.js', __DIR__ ), array( 'jquery' ), ABCC_VERSION, true );

	wp_localize_script(
		'abcc-onboarding-scripts',
		'abccOnboarding',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'abcc_onboarding' ),
			'i18n'    => array(
				'testing'    => __( 'Testing connection...', 'automated-blog-content-creator' ),
				'success'    => __( 'Connection successful!', 'automated-blog-content-creator' ),
				'error'      => __( 'Connection failed. Please check your API key.', 'automated-blog-content-creator' ),
				'generating' => __( 'Generating your first post...', 'automated-blog-content-creator' ),
				'welcome'    => __( 'Welcome to WP-AutoInsight!', 'automated-blog-content-creator' ),
			),
		)
	);

	?>
	<div class="wrap abcc-onboarding-wrap">
		<div class="abcc-onboarding-container">
			<!-- Header -->
			<div class="abcc-onboarding-header">
				<h1><?php esc_html_e( 'Welcome to WP-AutoInsight!', 'automated-blog-content-creator' ); ?></h1>
				<p><?php esc_html_e( 'Let\'s get you set up and creating content in just a few minutes.', 'automated-blog-content-creator' ); ?></p>
				<div class="abcc-progress-bar">
					<div class="abcc-progress-fill" data-step="1"></div>
				</div>
				<div class="abcc-step-indicators">
					<span class="abcc-step active" data-step="1">1</span>
					<span class="abcc-step" data-step="2">2</span>
					<span class="abcc-step" data-step="3">3</span>
				</div>
			</div>

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

			<!-- Step 2: API Configuration -->
	<div class="abcc-onboarding-step abcc-step-2">
		<div class="abcc-step-content">
			<h2><?php esc_html_e( 'Connect an AI Provider', 'automated-blog-content-creator' ); ?></h2>
			<p><?php esc_html_e( 'Choose one AI service to power your content generation.', 'automated-blog-content-creator' ); ?></p>

			<div class="abcc-api-providers">
				<!-- OpenAI -->
				<div class="abcc-api-provider" data-provider="openai">
					<div class="abcc-provider-header">
						<div class="abcc-provider-logo">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
								<path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.981 4.18a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 13.26 24a6.056 6.056 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM13.26 22.43a4.476 4.476 0 0 1-2.876-1.04l.141-.081 4.779-2.758a.795.795 0 0 0 .392-.681v-6.737l2.02 1.168a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.494 4.494zM3.6 18.304a4.47 4.47 0 0 1-.535-3.014l.142.085 4.783 2.759a.771.771 0 0 0 .78 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.74 19.95a4.5 4.5 0 0 1-6.14-1.646zM2.34 7.896a4.508 4.508 0 0 1 2.366-1.973V11.6a.766.766 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.34 7.872zm16.597 3.855l-5.833-3.387L15.119 7.2a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.678a.79.79 0 0 0-.407-.667zm2.01-3.023l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L9.409 9.23V6.897a.066.066 0 0 1 .028-.061l4.83-2.787a4.5 4.5 0 0 1 6.68 4.66zm-12.64 4.135l-2.02-1.164a.08.08 0 0 1-.038-.057V6.075a4.5 4.5 0 0 1 7.375-3.453l-.142.08L8.704 5.46a.795.795 0 0 0-.393.681zm1.097-2.365l2.602-1.5 2.607 1.5v2.999l-2.597 1.5-2.607-1.5z"/>
							</svg>
						</div>
						<div class="abcc-provider-info">
							<h3><?php esc_html_e( 'OpenAI', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'GPT-4.1, o4-mini • Best for creative content', 'automated-blog-content-creator' ); ?></p>
						</div>
						<div class="abcc-provider-status openai-status"></div>
					</div>
					<div class="abcc-provider-content">
						<?php if ( defined( 'OPENAI_API' ) && ! empty( OPENAI_API ) ) : ?>
							<div class="abcc-wp-config-notice">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'API key configured in wp-config.php - excellent security! 🔒', 'automated-blog-content-creator' ); ?>
							</div>
							<div class="abcc-api-input">
								<button class="button button-secondary abcc-test-api" data-provider="openai" data-wp-config="true">
									<?php esc_html_e( 'Test Connection', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="abcc-api-input">
								<input type="password" id="openai-api-key" placeholder="<?php esc_attr_e( 'sk-...', 'automated-blog-content-creator' ); ?>" />
								<button class="button button-secondary abcc-test-api" data-provider="openai">
									<?php esc_html_e( 'Test', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
							<div class="abcc-api-help">
								<button type="button" class="abcc-help-toggle" data-provider="openai">
									<span class="dashicons dashicons-book-alt"></span>
									<?php esc_html_e( 'How to get your OpenAI API key', 'automated-blog-content-creator' ); ?>
									<span class="abcc-help-arrow">▼</span>
								</button>
								<div class="abcc-help-content" data-provider="openai" style="display: none;">
									<ol>
										<li><?php esc_html_e( 'Visit', 'automated-blog-content-creator' ); ?> <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></li>
										<li><?php esc_html_e( 'Sign up for an account or log in to your existing account', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Click the "Create new secret key" button', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Give your key a name (e.g., "WP-AutoInsight")', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Copy the API key (starts with "sk-...")', 'automated-blog-content-creator' ); ?></li>
									</ol>
									<div class="abcc-help-note">
										<span class="dashicons dashicons-warning"></span>
										<strong><?php esc_html_e( 'Important:', 'automated-blog-content-creator' ); ?></strong>
										<?php esc_html_e( 'You\'ll need to add billing information to your OpenAI account. New accounts usually get $5 in free credits.', 'automated-blog-content-creator' ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Claude -->
				<div class="abcc-api-provider" data-provider="claude">
					<div class="abcc-provider-header">
						<div class="abcc-provider-logo">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
								<path d="M7.307 2.5h9.386c2.65 0 4.307 1.657 4.307 4.307v10.386c0 2.65-1.657 4.307-4.307 4.307H7.307C4.657 21.5 3 19.843 3 17.193V6.807C3 4.157 4.657 2.5 7.307 2.5zm4.693 4.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm-3 4.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm6 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>
							</svg>
						</div>
						<div class="abcc-provider-info">
							<h3><?php esc_html_e( 'Claude', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'Claude 4.5 Sonnet • Great for analytical content', 'automated-blog-content-creator' ); ?></p>
						</div>
						<div class="abcc-provider-status claude-status"></div>
					</div>
					<div class="abcc-provider-content">
						<?php if ( defined( 'CLAUDE_API' ) && ! empty( CLAUDE_API ) ) : ?>
							<div class="abcc-wp-config-notice">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'API key configured in wp-config.php - excellent security! 🔒', 'automated-blog-content-creator' ); ?>
							</div>
							<div class="abcc-api-input">
								<button class="button button-secondary abcc-test-api" data-provider="claude" data-wp-config="true">
									<?php esc_html_e( 'Test Connection', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="abcc-api-input">
								<input type="password" id="claude-api-key" placeholder="<?php esc_attr_e( 'sk-ant-api...', 'automated-blog-content-creator' ); ?>" />
								<button class="button button-secondary abcc-test-api" data-provider="claude">
									<?php esc_html_e( 'Test', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
							<div class="abcc-api-help">
								<button type="button" class="abcc-help-toggle" data-provider="claude">
									<span class="dashicons dashicons-book-alt"></span>
									<?php esc_html_e( 'How to get your Claude API key', 'automated-blog-content-creator' ); ?>
									<span class="abcc-help-arrow">▼</span>
								</button>
								<div class="abcc-help-content" data-provider="claude" style="display: none;">
									<ol>
										<li><?php esc_html_e( 'Go to', 'automated-blog-content-creator' ); ?> <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></li>
										<li><?php esc_html_e( 'Create an account or sign in', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Navigate to "API Keys" in the left sidebar', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Click "Create Key" button', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Copy the API key (starts with "sk-ant-api...")', 'automated-blog-content-creator' ); ?></li>
									</ol>
									<div class="abcc-help-note">
										<span class="dashicons dashicons-info"></span>
										<strong><?php esc_html_e( 'Note:', 'automated-blog-content-creator' ); ?></strong>
										<?php esc_html_e( 'Claude requires a paid account. New users get $5 in free credits after adding a payment method.', 'automated-blog-content-creator' ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Gemini -->
				<div class="abcc-api-provider" data-provider="gemini">
					<div class="abcc-provider-header">
						<div class="abcc-provider-logo">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
							</svg>
						</div>
						<div class="abcc-provider-info">
							<h3><?php esc_html_e( 'Google Gemini', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'Gemini 2.5 Flash • Excellent for factual content', 'automated-blog-content-creator' ); ?></p>
						</div>
						<div class="abcc-provider-status gemini-status"></div>
					</div>
					<div class="abcc-provider-content">
						<?php if ( defined( 'GEMINI_API' ) && ! empty( GEMINI_API ) ) : ?>
							<div class="abcc-wp-config-notice">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'API key configured in wp-config.php - excellent security! 🔒', 'automated-blog-content-creator' ); ?>
							</div>
							<div class="abcc-api-input">
								<button class="button button-secondary abcc-test-api" data-provider="gemini" data-wp-config="true">
									<?php esc_html_e( 'Test Connection', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="abcc-api-input">
								<input type="password" id="gemini-api-key" placeholder="<?php esc_attr_e( 'AIza...', 'automated-blog-content-creator' ); ?>" />
								<button class="button button-secondary abcc-test-api" data-provider="gemini">
									<?php esc_html_e( 'Test', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
							<div class="abcc-api-help">
								<button type="button" class="abcc-help-toggle" data-provider="gemini">
									<span class="dashicons dashicons-book-alt"></span>
									<?php esc_html_e( 'How to get your Gemini API key', 'automated-blog-content-creator' ); ?>
									<span class="abcc-help-arrow">▼</span>
								</button>
								<div class="abcc-help-content" data-provider="gemini" style="display: none;">
									<ol>
										<li><?php esc_html_e( 'Visit', 'automated-blog-content-creator' ); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com/app/apikey</a></li>
										<li><?php esc_html_e( 'Sign in with your Google account', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Click "Create API key" button', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Choose "Create API key in new project" (recommended)', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Copy the API key (starts with "AIza...")', 'automated-blog-content-creator' ); ?></li>
									</ol>
									<div class="abcc-help-note success">
										<span class="dashicons dashicons-yes-alt"></span>
										<strong><?php esc_html_e( 'Great news:', 'automated-blog-content-creator' ); ?></strong>
										<?php esc_html_e( 'Gemini offers generous free usage limits - perfect for getting started!', 'automated-blog-content-creator' ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Perplexity -->
				<div class="abcc-api-provider" data-provider="perplexity">
					<div class="abcc-provider-header">
						<div class="abcc-provider-logo">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
							</svg>
						</div>
						<div class="abcc-provider-info">
							<h3><?php esc_html_e( 'Perplexity', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'Sonar Pro - Web-grounded content with citations', 'automated-blog-content-creator' ); ?></p>
						</div>
						<div class="abcc-provider-status perplexity-status"></div>
					</div>
					<div class="abcc-provider-content">
						<?php if ( defined( 'PERPLEXITY_API' ) && ! empty( PERPLEXITY_API ) ) : ?>
							<div class="abcc-wp-config-notice">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'API key configured in wp-config.php - excellent security!', 'automated-blog-content-creator' ); ?>
							</div>
							<div class="abcc-api-input">
								<button class="button button-secondary abcc-test-api" data-provider="perplexity" data-wp-config="true">
									<?php esc_html_e( 'Test Connection', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
						<?php else : ?>
							<div class="abcc-api-input">
								<input type="password" id="perplexity-api-key" placeholder="<?php esc_attr_e( 'pplx-...', 'automated-blog-content-creator' ); ?>" />
								<button class="button button-secondary abcc-test-api" data-provider="perplexity">
									<?php esc_html_e( 'Test', 'automated-blog-content-creator' ); ?>
								</button>
							</div>
							<div class="abcc-api-help">
								<button type="button" class="abcc-help-toggle" data-provider="perplexity">
									<span class="dashicons dashicons-book-alt"></span>
									<?php esc_html_e( 'How to get your Perplexity API key', 'automated-blog-content-creator' ); ?>
									<span class="abcc-help-arrow">▼</span>
								</button>
								<div class="abcc-help-content" data-provider="perplexity" style="display: none;">
									<ol>
										<li><?php esc_html_e( 'Visit', 'automated-blog-content-creator' ); ?> <a href="https://www.perplexity.ai/settings/api" target="_blank">perplexity.ai/settings/api</a></li>
										<li><?php esc_html_e( 'Sign up for an account or log in', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Navigate to the API section in Settings', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Generate a new API key', 'automated-blog-content-creator' ); ?></li>
										<li><?php esc_html_e( 'Copy the API key (starts with "pplx-...")', 'automated-blog-content-creator' ); ?></li>
									</ol>
									<div class="abcc-help-note">
										<span class="dashicons dashicons-info"></span>
										<strong><?php esc_html_e( 'Note:', 'automated-blog-content-creator' ); ?></strong>
										<?php esc_html_e( 'Perplexity API requires a paid plan. Content includes web citations automatically.', 'automated-blog-content-creator' ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="abcc-step-actions">
				<button class="button button-secondary" id="abcc-prev-step-2">
					<?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?>
				</button>
				<button class="button button-primary" id="abcc-next-step-2" disabled>
					<?php esc_html_e( 'Continue', 'automated-blog-content-creator' ); ?>
				</button>
			</div>
		</div>
	</div>

			<!-- Step 3: First Post -->
			<div class="abcc-onboarding-step abcc-step-3">
				<div class="abcc-step-content">
					<h2><?php esc_html_e( 'Generate Your First Post', 'automated-blog-content-creator' ); ?></h2>
					<p><?php esc_html_e( 'Let\'s create your first AI-generated post to make sure everything is working perfectly.', 'automated-blog-content-creator' ); ?></p>

					<div class="abcc-first-post-preview">
						<div class="abcc-post-preview-card">
							<div class="abcc-post-icon">
								<span class="dashicons dashicons-welcome-write-blog"></span>
							</div>
							<h3><?php esc_html_e( 'Welcome Post', 'automated-blog-content-creator' ); ?></h3>
							<p><?php esc_html_e( 'We\'ll create a "Hello World" style post to test your setup and show you how the plugin works.', 'automated-blog-content-creator' ); ?></p>
							<div class="abcc-post-features">
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'AI-generated content', 'automated-blog-content-creator' ); ?>
								</span>
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'SEO-optimized', 'automated-blog-content-creator' ); ?>
								</span>
								<span class="abcc-feature">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Featured image', 'automated-blog-content-creator' ); ?>
								</span>
							</div>
						</div>
					</div>

					<div class="abcc-generation-status" id="abcc-generation-status" style="display: none;">
						<div class="abcc-loading-spinner"></div>
						<p id="abcc-generation-text"><?php esc_html_e( 'Generating your first post...', 'automated-blog-content-creator' ); ?></p>
					</div>

					<div class="abcc-step-actions">
						<button class="button button-secondary" id="abcc-prev-step-3">
							<?php esc_html_e( 'Back', 'automated-blog-content-creator' ); ?>
						</button>
						<button class="button button-primary button-hero" id="abcc-generate-first-post">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Generate First Post', 'automated-blog-content-creator' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Success Step -->
			<div class="abcc-onboarding-step abcc-step-success" style="display: none;">
				<div class="abcc-step-content abcc-success-content">
					<div class="abcc-success-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<h2><?php esc_html_e( 'Congratulations! 🎉', 'automated-blog-content-creator' ); ?></h2>
					<p><?php esc_html_e( 'WP-AutoInsight is now set up and ready to help you create amazing content.', 'automated-blog-content-creator' ); ?></p>
					
					<div class="abcc-success-actions">
						<a href="#" class="button button-primary button-hero" id="abcc-view-first-post">
							<?php esc_html_e( 'Edit Your First Post', 'automated-blog-content-creator' ); ?>
						</a>
						<a href="?page=automated-blog-content-creator-post" class="button button-secondary">
							<?php esc_html_e( 'Go to Settings', 'automated-blog-content-creator' ); ?>
						</a>
					</div>

					<div class="abcc-next-steps">
						<h3><?php esc_html_e( 'What\'s Next?', 'automated-blog-content-creator' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Customize your keywords and categories', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Set up automated scheduling', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Try the audio transcription feature', 'automated-blog-content-creator' ); ?></li>
							<li><?php esc_html_e( 'Generate infographics for existing posts', 'automated-blog-content-creator' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Get onboarding goals configuration.
 *
 * @since 3.1.0
 * @return array Array of goal configurations.
 */
function abcc_get_onboarding_goals() {
	return array(
		'blogger'  => array(
			'title'       => __( 'Personal/Business Blog', 'automated-blog-content-creator' ),
			'description' => __( 'Create engaging blog posts for your audience', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'friendly',
				'openai_char_limit'      => 300,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
		'business' => array(
			'title'       => __( 'Business/Corporate Content', 'automated-blog-content-creator' ),
			'description' => __( 'Professional content for business websites', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'professional',
				'openai_char_limit'      => 400,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
		'news'     => array(
			'title'       => __( 'News/Information Site', 'automated-blog-content-creator' ),
			'description' => __( 'Quick, informative articles and updates', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'professional',
				'openai_char_limit'      => 250,
				'openai_generate_images' => false,
				'openai_generate_seo'    => true,
			),
		),
		'creative' => array(
			'title'       => __( 'Creative/Entertainment', 'automated-blog-content-creator' ),
			'description' => __( 'Fun, engaging content with personality', 'automated-blog-content-creator' ),
			'settings'    => array(
				'openai_tone'            => 'casual',
				'openai_char_limit'      => 350,
				'openai_generate_images' => true,
				'openai_generate_seo'    => true,
			),
		),
	);
}

/**
 * Get icon for onboarding goal.
 *
 * @since 3.1.0
 * @param string $goal_key The goal identifier.
 * @return string SVG icon markup.
 */
function abcc_get_goal_icon( $goal_key ) {
	$icons = array(
		'blogger'  => '<span class="dashicons dashicons-admin-users"></span>',
		'business' => '<span class="dashicons dashicons-building"></span>',
		'news'     => '<span class="dashicons dashicons-megaphone"></span>',
		'creative' => '<span class="dashicons dashicons-art"></span>',
	);

	return $icons[ $goal_key ] ?? '<span class="dashicons dashicons-welcome-write-blog"></span>';
}

/**
 * Check if user has any API key configured.
 *
 * @since 3.1.0
 * @return bool Whether any API key is configured.
 */
function abcc_has_any_api_key() {
	$openai_key = defined( 'OPENAI_API' ) ? OPENAI_API : get_option( 'openai_api_key', '' );
	$claude_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : get_option( 'claude_api_key', '' );
	$gemini_key = defined( 'GEMINI_API' ) ? GEMINI_API : get_option( 'gemini_api_key', '' );
	$perplexity_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : get_option( 'perplexity_api_key', '' );

	return ! empty( $openai_key ) || ! empty( $claude_key ) || ! empty( $gemini_key ) || ! empty( $perplexity_key );
}

/**
 * Check if user has generated any content.
 *
 * @since 3.1.0
 * @return bool Whether any content has been generated.
 */
function abcc_has_generated_content() {
	// Check if any posts were created by the plugin.
	$posts = get_posts(
		array(
			'meta_query'     => array(
				array(
					'key'     => '_abcc_generated',
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => 1,
		)
	);

	return ! empty( $posts );
}

/**
 * Mark existing users to skip onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_check_existing_user_on_activation() {
	// If any API key exists or any content has been generated, mark as completed.
	if ( abcc_has_any_api_key() || abcc_has_generated_content() ) {
		update_option( 'abcc_onboarding_completed', true );
	}
}

/**
 * AJAX handler for goal selection.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_goal() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	$goal  = sanitize_text_field( wp_unslash( $_POST['goal'] ) );
	$goals = abcc_get_onboarding_goals();

	if ( ! isset( $goals[ $goal ] ) ) {
		wp_send_json_error( array( 'message' => 'Invalid goal selected' ) );
	}

	// Apply goal-based settings.
	foreach ( $goals[ $goal ]['settings'] as $option => $value ) {
		update_option( $option, $value );
	}

	wp_send_json_success( array( 'message' => 'Goal configured successfully' ) );
}
add_action( 'wp_ajax_abcc_onboarding_goal', 'abcc_handle_onboarding_goal' );

/**
 * AJAX handler for API key testing.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_test_api() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	$provider     = sanitize_text_field( wp_unslash( $_POST['provider'] ) );
	$api_key      = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	$is_wp_config = isset( $_POST['wp_config'] ) && $_POST['wp_config'] === 'true';

	// Get API key from wp-config if needed
	if ( $is_wp_config ) {
		switch ( $provider ) {
			case 'openai':
				$api_key = defined( 'OPENAI_API' ) ? OPENAI_API : '';
				break;
			case 'claude':
				$api_key = defined( 'CLAUDE_API' ) ? CLAUDE_API : '';
				break;
			case 'gemini':
				$api_key = defined( 'GEMINI_API' ) ? GEMINI_API : '';
				break;
			case 'perplexity':
				$api_key = defined( 'PERPLEXITY_API' ) ? PERPLEXITY_API : '';
				break;
		}
	}

	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => __( 'API key is required', 'automated-blog-content-creator' ) ) );
	}

	// Test the API key based on provider with detailed error reporting
	$test_result = array(
		'success' => false,
		'error'   => 'Unknown error',
	);

	switch ( $provider ) {
		case 'openai':
			$test_result = abcc_test_openai_connection( $api_key );
			break;
		case 'claude':
			$test_result = abcc_test_claude_connection( $api_key );
			break;
		case 'gemini':
			$test_result = abcc_test_gemini_connection( $api_key );
			break;
		case 'perplexity':
			$test_result = abcc_test_perplexity_connection( $api_key );
			break;
	}

	if ( $test_result['success'] ) {
		// Save the API key only if it's not from wp-config
		if ( ! $is_wp_config ) {
			update_option( $provider . '_api_key', $api_key );
		}

		// Get available models and select the first economy option
		$model_options = abcc_get_ai_model_options();
		if ( isset( $model_options[ $provider ]['options'] ) ) {
			$provider_models = $model_options[ $provider ]['options'];
			$first_model     = key( $provider_models );
			update_option( 'prompt_select', $first_model );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'automated-blog-content-creator' ) ) );
	} else {
		wp_send_json_error( array( 'message' => $test_result['error'] ) );
	}
}
add_action( 'wp_ajax_abcc_onboarding_test_api', 'abcc_handle_onboarding_test_api' );

/**
 * AJAX handler for first post generation.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_first_post() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	try {
		$api_key       = abcc_check_api_key();
		$keywords      = array( 'welcome', 'hello world', 'getting started' );
		$prompt_select = get_option( 'prompt_select', 'gpt-4.1-mini' );
		$tone          = get_option( 'openai_tone', 'friendly' );
		$char_limit    = get_option( 'openai_char_limit', 200 );

		$post_id = abcc_openai_generate_post(
			$api_key,
			$keywords,
			$prompt_select,
			$tone,
			false,
			$char_limit
		);

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		// Mark post as generated during onboarding.
		update_post_meta( $post_id, '_abcc_generated', true );
		update_post_meta( $post_id, '_abcc_onboarding_post', true );

		// Mark onboarding as completed.
		update_option( 'abcc_onboarding_completed', true );
		set_transient( 'abcc_onboarding_just_completed', true, 300 );

		wp_send_json_success(
			array(
				'message'  => 'First post created successfully!',
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, '' ),
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_abcc_onboarding_first_post', 'abcc_handle_onboarding_first_post' );

/**
 * AJAX handler for skipping onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_handle_onboarding_skip() {
	check_ajax_referer( 'abcc_onboarding', 'nonce' );

	update_option( 'abcc_onboarding_completed', true );
	wp_send_json_success( array( 'message' => 'Onboarding skipped' ) );
}
add_action( 'wp_ajax_abcc_onboarding_skip', 'abcc_handle_onboarding_skip' );

/**
 * Test OpenAI API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_openai_connection( $api_key ) {
	try {
		$client   = new ABCC_OpenAI_Client( $api_key );
		$response = $client->create_chat_completion(
			array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			array(
				'model'      => 'gpt-4.1-mini',
				'max_tokens' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		if ( ! isset( $response['choices'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from OpenAI', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'OpenAI connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}

/**
 * Test Claude API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_claude_connection( $api_key ) {
	try {
		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		);

		$body = array(
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 5,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message */
					__( 'Network error: %s', 'automated-blog-content-creator' ),
					$response->get_error_message()
				),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown API error';

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'Claude API error (%1$d): %2$s', 'automated-blog-content-creator' ),
					$response_code,
					$error_msg
				),
			);
		}

		// Verify response has expected structure
		$data = json_decode( $response_body, true );
		if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from Claude API', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Claude connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}

/**
 * Test Gemini API connection.
 *
 * @since 3.1.0
 * @param string $api_key The API key to test.
 * @return mixed Whether the connection test succeeded.
 */
function abcc_test_gemini_connection( $api_key ) {
	try {
		$gemini = new \GeminiAPI\Client( $api_key );

		// Use stable 2.5 model for testing.
		$response = $gemini
			->generativeModel( 'gemini-2.5-flash' )
			->generateContent( new \GeminiAPI\Resources\Parts\TextPart( 'Hello' ) );

		if ( empty( $response->text() ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Gemini API returned empty response', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( \GeminiAPI\Exceptions\InvalidApiKeyException $e ) {
		return array(
			'success' => false,
			'error'   => __( 'Invalid Gemini API key', 'automated-blog-content-creator' ),
		);
	} catch ( \GeminiAPI\Exceptions\ApiException $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Gemini API error: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Gemini connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}

/**
 * Admin notice for completed onboarding.
 *
 * @since 3.1.0
 * @return void
 */
function abcc_onboarding_completed_notice() {
	if ( get_transient( 'abcc_onboarding_just_completed' ) ) {
		delete_transient( 'abcc_onboarding_just_completed' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( '🎉 Welcome to WP-AutoInsight! Your first post has been created and you\'re ready to go!', 'automated-blog-content-creator' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'abcc_onboarding_completed_notice' );

/**
 * Test Perplexity API connection.
 *
 * @since 3.3.0
 * @param string $api_key The API key to test.
 * @return array Whether the connection test succeeded.
 */
function abcc_test_perplexity_connection( $api_key ) {
	try {
		$response = wp_remote_post(
			'https://api.perplexity.ai/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'      => 'sonar',
						'max_tokens' => 5,
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'Hello',
							),
						),
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message */
					__( 'Network error: %s', 'automated-blog-content-creator' ),
					$response->get_error_message()
				),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown API error';

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: 1: HTTP status code, 2: Error message */
					__( 'Perplexity API error (%1$d): %2$s', 'automated-blog-content-creator' ),
					$response_code,
					$error_msg
				),
			);
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Unexpected response format from Perplexity API', 'automated-blog-content-creator' ),
			);
		}

		return array( 'success' => true );

	} catch ( Exception $e ) {
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: Error message */
				__( 'Perplexity connection failed: %s', 'automated-blog-content-creator' ),
				$e->getMessage()
			),
		);
	}
}
