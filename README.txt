=== WP-AutoInsight ===
Contributors: phalkmin
Tags: openai, anthropic, google-ai, perplexity, ai-content
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 4.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Short Description: Publish AI-written content directly from WordPress, using your own OpenAI, Claude, Gemini, or Perplexity keys. No subscriptions. No surprises. You pay for exactly what you get.


== Description ==

WP-AutoInsight brings AI content generation into your WordPress dashboard without a platform subscription attached. It isn't a SaaS or another subscription service. You pay for what you use. Connect your OpenAI, Anthropic, Google, or Perplexity accounts to the plugin, and your site will generate content at low cost, for a fraction of what most SaaS tools charge $50-100 per month.

Whether you're a small business keeping a blog active, an agency managing content for clients, or a blogger who'd rather talk through ideas than type them, WP-AutoInsight creates, you review, and *you* publish.

= Key Features =

* **Generate content in more ways than you'd expect**
  - Write full blog posts from a keyword list, automatically or on demand
  - Build a Topic Library: reusable topics that generate posts on their own schedule, from hourly to weekly, each with its own prompt and model
  - Turn voice notes or meeting recordings into draft posts. Upload audio, get a structured article
  - Create infographics from any existing post, saved directly to your Media Library
  - Pull research-backed content through Perplexity Sonar, complete with clickable source citations

* **Choose the AI. Pay the AI directly.**
  - Supports OpenAI, Anthropic Claude, Google Gemini, and Perplexity models. Switch models anytime you want
  - Each model shows an estimated cost per post before you choose it
  - Your API keys, their actual rates. No markup, no lock-in

* **Nothing publishes without your approval**
  - Content saves as a draft by default. Review before anything goes live
  - Content History tracks every generated post: which model, which status, when
  - Set tone, keywords, categories, and length once. The plugin will follow your rules

* **Works with everything already on your site**
  - Native Gutenberg block output. Not an HTML blob in a classic editor
  - Yoast SEO and RankMath: focus keywords, meta descriptions, and social excerpts generated automatically
  - Featured images via OpenAI GPT Image, Stability AI, or Gemini image generation

* **For developers**
  - Store API keys in wp-config.php for maximum security, or use WordPress 7.0's native Connectors API
  - Configurable per post type, clean option names, no proprietary lock-in

== Installation ==

1. Upload `wp-autoinsight` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'WP-AutoInsight' in your admin menu
4. Configure your preferred AI service API keys
5. Set up your content preferences and posting schedule

== Configuration ==

= API Keys =
You'll need at least one of the following API keys:
* OpenAI API key (for GPT models and GPT Image)
* Claude API key (for Claude 4.5 models)
* Gemini API key (for Google's AI)
* Perplexity API key (for web-grounded content with citations)
* Stability AI key (optional, for alternative image generation)

For enhanced security, add your API keys to wp-config.php:
```php
define('OPENAI_API', 'your-key-here');
define('CLAUDE_API', 'your-key-here');
define('GEMINI_API', 'your-key-here');
define('PERPLEXITY_API', 'your-key-here');
define('STABILITY_API', 'your-key-here');
```


= Content Settings =
1. Select your preferred AI model
2. Set your desired content tone
3. Configure keywords and categories
4. Adjust token limits and scheduling
5. Enable/disable image generation
6. Set up email notifications

== Frequently Asked Questions ==

= How do I get an OpenAI API key? =

To use OpenAI models (GPT-4.1, o4-mini), sign up at OpenAI and get your API key:
1. Go to https://platform.openai.com/api-keys
2. Sign up or log in to your account
3. Click "Create new secret key"
4. Copy and paste the key into WP-AutoInsight's Advanced Settings

= How do I get a Claude API key? =

To use Claude models (Haiku, Sonnet, Opus), you need an Anthropic API key:
1. Visit https://console.anthropic.com/
2. Create an account or sign in
3. Go to API Keys section
4. Generate a new API key
5. Add it to WP-AutoInsight's Advanced Settings

= How do I get a Gemini API key? =

To use Google's Gemini models, get your API key from Google AI Studio:
1. Go to https://aistudio.google.com/app/apikey
2. Sign in with your Google account
3. Click "Create API key"
4. Copy the key to WP-AutoInsight's Advanced Settings

= How do I get a Stability AI API key? =

For image generation fallback with Stability AI:
1. Visit https://platform.stability.ai/
2. Create an account and sign in
3. Go to API Keys in your account settings
4. Generate a new API key
5. Add it to Advanced Settings for image generation

= How do I select AI models? =

WP-AutoInsight 4.0 features a visual model selection interface inside the Connections tab:
1. Go to WP-AutoInsight > Connections > API Keys
2. Browse model cards organized by provider (OpenAI, Claude, Gemini)
3. Click on your preferred model to select it
4. Each model shows cost tier (Economy/Standard/Premium) and estimated cost per post
5. Your selection saves automatically

= How can I customize the generated content? =

You have extensive customization options:
- **Keywords**: Set topics and focus areas in Content Settings
- **Tone**: Choose from Professional, Casual, Friendly, or Custom tone
- **Categories**: Select WordPress categories for posts
- **Token Limits**: Control content length in Advanced Settings
- **SEO**: Enable automatic SEO metadata generation
- **Images**: Toggle featured image generation on/off

= Can I use audio files to create blog posts? =

Yes! WP-AutoInsight 3.0 includes audio transcription:
1. Enable Audio Transcription in settings
2. Upload an audio file to your Media Library
3. Edit the audio file and click "Transcribe & Create Post"
4. The AI will convert speech to text and create a formatted blog post
5. Supports MP3, WAV, M4A, WebM, FLAC formats up to 25MB

= How do I create infographics from my posts? =

The infographic feature analyzes your content and creates visuals:
1. Open any existing post for editing
2. Look for the "AI Infographic Tools" meta box
3. Click "Create Infographic"
4. The AI analyzes your content and generates a visual infographic
5. The image is saved to your Media Library automatically

= Can I rewrite existing posts with AI? =

Yes, use the AI rewrite feature:
1. Edit any existing post
2. Find the "AI Content Tools" meta box in the sidebar
3. Click "Rewrite with AI"
4. The AI will improve and restructure your content while maintaining the core message
5. Review and publish the updated content

= How do I manually create posts? =

Multiple ways to create posts manually:
- **Settings Page**: Click "Create post manually" in Content Settings
- **Post List**: Use the "Create AI Post" button on post list screens
- **Quick Creation**: Generate posts from the main dashboard

= Is it possible to schedule automatic content generation? =

Yes, WP-AutoInsight offers flexible automation:
1. Go to Connections > Scheduling
2. Set "Schedule post creation" to Hourly, Daily, or Weekly
3. Configure your keywords and preferences
4. Posts will be automatically generated and saved as drafts
5. Optional email notifications when new posts are created

= Which post types are supported? =

You can configure which post types show AI tools:
1. Go to Content Settings
2. Select from available post types (Posts, Pages, Custom Post Types)
3. AI buttons and tools will appear for selected post types
4. Default is set to standard WordPress Posts

= How secure are my API keys? =

WP-AutoInsight prioritizes security:
- Store API keys in wp-config.php for maximum security
- Database storage is encrypted
- Keys are never logged or transmitted unnecessarily
- Use secure HTTPS connections for all API calls

= What's the difference between the AI models? =

Each provider offers different strengths:
- **OpenAI**: Excellent for creative and versatile content
- **Claude**: Great for analytical and structured content
- **Gemini**: Strong at factual and research-based content
- **Perplexity**: Generates web-grounded content with real source citations, ideal for research-heavy or news-adjacent posts
- **Cost Tiers**: Economy (fast/cheap), Standard (balanced), Premium (highest quality)

= How does Perplexity work differently from the other providers? =

Perplexity searches the web in real time before generating content, then includes source citations alongside the text. Instead of generating from training data alone, it pulls from current sources and references them in the post. You can choose how citations appear: as inline hyperlinks, a references section at the bottom, or both. You can also set a recency filter to limit sources to the last day, week, month, or year. A Perplexity API key with an active paid plan is required.

= Can I use multiple AI services together? =

Yes, you can configure multiple API keys and switch between models:
- Set up keys for different providers in Advanced Settings
- Choose different models for different types of content
- The plugin automatically uses the appropriate service based on your selection

= Does the plugin work with SEO plugins? =

Yes, WP-AutoInsight integrates with popular SEO plugins:
- **Yoast SEO**: Automatic meta descriptions, focus keywords, and social previews
- **RankMath**: Compatible with meta field generation
- Enable "Generate SEO Metadata" in Content Settings for automatic optimization

= What happens if content generation fails? =

WP-AutoInsight includes robust error handling:
- Detailed error messages help identify issues
- Automatic fallbacks between different AI services
- Content is saved as drafts to prevent data loss
- Error logging helps with troubleshooting
- Email notifications for scheduled generation failures

= How do I get support? =

Multiple support channels available:
- **Documentation**: https://wpautoinsight.phalkmin.me/
- **WordPress Forum**: https://wordpress.org/support/plugin/automated-blog-content-creator/
- **GitHub Issues**: https://github.com/phalkmin/wp-autoinsight
- **Direct Contact**: phalkmin@protonmail.com

== Screenshots ==

1. Plugin settings page - Configure API key, keywords, and other options.
2. Example generated blog post using Gutenberg blocks.

== Changelog ==

= 4.4.0 =
* Content language: posts, titles, SEO metadata, and audio intros are written in your site's language automatically. Override it under Content → Keywords → Writing Style. Old custom templates without a {language} placeholder still come out right.
* Truncation repair: posts that hit the model's token limit are trimmed to the last complete section and given a proper closing paragraph instead of stopping mid-sentence. The generation log notes when this happened.
* Generation errors now say what went wrong and what to do — a rejected key, a rate limit, an oversized prompt, and a provider outage each get their own actionable message.
* Featured image failures are visible: the post edit screen shows why the image failed, with a Retry button.
* New "Restart setup wizard" button in Settings → Advanced (keeps your settings and API keys).
* Saved API keys are no longer displayed in settings pages; leave the field blank to keep your existing key.
* Security: onboarding connection test requires administrator capability; rewrite/regenerate check per-post edit permission; Gemini keys moved from the request URL to a header; admin status areas no longer inject server-supplied HTML.
* Fixed: featured images failing to attach on local and firewalled sites; Perplexity citations attaching to the wrong post under concurrent schedules; prompts being HTML-mangled before sending; bulk SEO firing one request per post; cron schedules queried on every page load; SEO parsing failing on braces in prose; the dashboard SEO Refresh tile linking nowhere; the wizard redirecting away from its completion screen; hover-only error messages; misleading "Settings saved" notices after destructive actions; endless job polling; the settings JSON export producing a broken page instead of a download; Enter breaking multi-line wizard fields.
* Post length now reads in approximate words; model names show in readable form; tooltips work with keyboard focus; confirm dialogs are translatable.

= 4.3.0 =
* Dashboard reframed into a Command Center: the new "Composer" card shows exactly what your next post will use — source, template, model, and draft/publish — and lets you change any of it in place before generating. Per-post choices stick as the new default without touching your global settings.
* New capability grid on the dashboard surfaces everything the plugin can do (Topics, Bulk Generate, Post from Audio, SEO Refresh, Featured Images, Infographics, Schedule, Providers) at a glance.
* Post from Audio: upload a recording and choose how it becomes a post — keep your words with an AI-written intro and title, or have AI rewrite the recording into a full article. Output honors your draft/publish default; if a rewrite fails, your transcription is never lost.
* Refresh: new bulk "Regenerate SEO" action on the Posts list — select posts and queue background jobs to regenerate titles, meta descriptions, and focus keywords with your chosen model. Works with Yoast and Rank Math.
* Rewrote first-run onboarding into a focused flow: connect a provider, choose your draft/publish default, optionally create your first Topic, and discover Post from Audio. "Skip to settings" is available on every step.
* Updated the AI model lineup: Claude Opus 4.8, OpenAI GPT-5.4 / GPT-5.4 mini / GPT-5.5, Gemini 3.5 Flash, and Perplexity Sonar Deep Research. GPT-4.1 models remain available, now labeled "(legacy)". New installs default to GPT-5.4 mini. Models with highly variable cost show a warning in the selector.
* Fixed: Post from Audio now uses your selected text model's API key (it previously reused the OpenAI transcription key, silently failing for Claude/Gemini/Perplexity users), embeds the audio player, splits the transcript into paragraphs, and cleans Markdown out of the title.
* Fixed: onboarding now saves your Publish/Draft choice correctly and adds a "Skip to settings" link to the first step.
* Fixed: Bulk SEO Regeneration uses each job's chosen model key and skips posts you can't edit.
* Fixed: GPT-5.x models failed with "Unsupported parameter: 'max_tokens'" — the OpenAI client now sends the parameters these models require and omits the ones they reject.
* Fixed: GPT-5.x content generation returned empty results (a silent "Content generation failed") because the models spent the whole token budget on internal reasoning. Reasoning is now disabled for GPT-5.x requests so every token goes to your post, and empty completions from any provider are logged with details instead of failing silently.
* Fixed: posts came out as one long paragraph when the model returned the article on a single line; content is now split into proper heading/paragraph blocks for all providers.
* Fixed: post titles could end up as the model's preamble ("Here are some catchy blog post titles…") when the AI replied with a list of title options (seen with Gemini). The plugin now asks for exactly one title and picks a clean title out of list-style replies.
* Fixed: the WordPress admin footer overlapped the dashboard's "At a glance" section due to a stray closing tag in the dashboard template.
* Fixed: new models (GPT-5.x, Gemini 3.5 Flash, Claude Opus 4.8, Sonar Deep Research) were silently limited to a 4,096-token budget, producing much shorter posts than configured.
* Fixed: a PHP deprecation notice from the job log when a generated post had been deleted.

= 4.2.0 =
* New Topic Library tab: reusable topics with per-topic schedule (hourly to weekly), draft/publish override, and model override. Pause, resume, edit, or run any topic on demand; topics that fail repeatedly pause themselves and tell you why.
* Generated posts now save as drafts by default, with a new Draft/Publish setting. Existing installs keep their current behavior automatically and see a one-time notice.
* Fixed: duplicate posts from one generation job, provider health false "connected" status, Claude API error handling, stray `<title>`/`[SEO]` lines in content, Gemini image API key encoding.
* Fixed: OpenAI image generation failing after DALL-E 3's deprecation. Now uses the GPT Image models (gpt-image-1, Mini, 1.5), selectable on the Images tab; existing installs recover automatically.
* Internal: unified API call layer for all text providers with consistent error handling and truncation detection; onboarding wizard split into step partials.

= 4.1.1 =
* Keyword Groups now pick **one** keyword at random per generated post instead of mashing every keyword into a single article. A "Chemistry" group with ten keywords becomes a queue of ten focused topics rather than one over-broad post.
* New `{keyword}` template placeholder holds the keyword chosen for the current post; `{keywords}` still renders the full comma-separated group for templates that want it. The default template now uses `{keyword}` so existing setups get the focused behavior automatically.
* Generation tracking meta records which keyword was selected; the post-editor meta box now shows the focus keyword alongside the source group.
* Fixed: the Default Template now displays its prompt body in a read-only textarea on the Content settings page instead of an empty placeholder.

= 4.1.0 =
* WP 7.0 Connectors API integration now works correctly (previously broken hook and registry calls removed)
* Admin notice shown when WordPress has AI disabled at site level (WP 7.0)
* Claude models refreshed: Sonnet 4.6 and Opus 4.7 (1M token context window each)
* Connections tab shows read-only legacy key reference when WP Connector is active for a provider
* Contextual "Learn more" links added to API Keys, Scheduling, and Bulk Generate sections
* ~1.9 MB runtime reduction: Gemini SDK replaced with direct wp_remote_post() calls, Composer vendor removed from runtime

= 4.0.1 =

- Fixes to "Validate" button for API keys
- Adding documentation URL to the dashboard

= 4.0.0 =
The biggest settings redesign since launch. Everything is easier to find, the dashboard tells you what's happening without digging, and settings that used to feel buried now live exactly where you'd look for them.

**A real dashboard, finally.**
The plugin now opens to a dashboard instead of a blank settings form. You can see your scheduling status at a glance, check the last few generation jobs without leaving the page, and get to common actions in one click. Provider health is summarized right there — green/yellow/red, with timestamps so you know if a key was last verified today or a week ago.

**Settings that make sense.**
The old flat tab layout is gone. Settings are now split into five focused sections: Content (keywords, templates, writing style), Media (images, audio, infographics), Connections (API keys and scheduling), Settings (post types, permissions, advanced), and the Dashboard. Everything that previously required hunting through a long page now has its own tab.

**Changes that save themselves.**
Toggles and sliders across the settings pages now save automatically as you adjust them, with a small "Saved" confirmation. No more scrolling to the bottom to hit a save button after every change. Fields that affect scheduling still require an explicit save (and warn you if you leave without saving).

**Per-role access control.**
You can now control which WordPress roles can access AI generation tools. Authors, editors, or just administrators — your call. Administrators are always enabled and can't be unchecked.

**Provider health checks run daily.**
The plugin now runs a background health check on your connected providers once a day and caches the result. The dashboard shows the freshness of each check so you know whether a "connected" status is from this morning or three days ago.

**Debug mode and settings export.**
The Advanced tab now has a one-click debug logging toggle (no more editing wp-config.php for basic troubleshooting) and a JSON export button so you can back up or migrate your settings.

For the full changelog of earlier versions, see CHANGELOG.txt.


== Support ==

For support, feature requests, or to contribute to development:
* Read the [Documentation](https://wpautoinsight.phalkmin.me/)
* Visit the [WordPress support forum](https://wordpress.org/support/plugin/automated-blog-content-creator/)
* Submit issues on [GitHub](https://github.com/phalkmin/wp-autoinsight)
* For custom integrations or consulting: phalkmin@protonmail.com
* Support development: [![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/U7U1LM8AP)
