=== WP-AutoInsight ===
Contributors: phalkmin
Tags: openai, anthropic, google-ai, perplexity, ai-content
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Short Description: Publish AI-written content directly from WordPress, using your own OpenAI, Claude, Gemini, or Perplexity keys. No subscriptions. No surprises. You pay for exactly what you get.


== Description ==

WP-AutoInsight brings AI content generation into your WordPress dashboard without a platform subscription attached. It isn't a SaaS or another subscription service. You pay for what you use. Connect your OpenAI, Anthropic, Google, or Perplexity accounts to the plugin, and your site will generate content at low cost, for a fraction of what most SaaS tools charge $50-100 per month.

Whether you're a small business keeping a blog active, an agency managing content for clients, or a blogger who'd rather talk through ideas than type them, WP-AutoInsight creates, you review, and *you* publish.

= Key Features =

* **Generate content in more ways than you'd expect**
  - Write full blog posts from a keyword list, automatically or on demand
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
  - Featured images via DALL-E 3, Stability AI, or Gemini image generation

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
* OpenAI API key (for GPT models and DALL-E)
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
- **WordPress Forum**: https://wordpress.org/support/plugin/automated-blog-content-creator/
- **GitHub Issues**: https://github.com/phalkmin/wp-autoinsight
- **Direct Contact**: phalkmin@protonmail.com
- **Documentation**: Check the Dashboard tab for tutorials and quick actions

== Screenshots ==

1. Plugin settings page - Configure API key, keywords, and other options.
2. Example generated blog post using Gutenberg blocks.

== Changelog ==

= 4.0.0 — Decade =
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

= 3.8.0 — Kiva: Upgrade Without Fear =
Upgrades used to feel like a gamble — would your API keys survive? Would your keyword groups vanish? Not anymore.

Settings now follow a versioned migration path. When you update the plugin, it checks your current settings, upgrades what needs upgrading, and shows you a confirmation notice when it's done. Your API keys, keyword groups, content templates, and selected models all carry over cleanly.

All five AI providers (OpenAI, Claude, Gemini, Perplexity, Stability AI) are now defined in a single registry. Adding or changing providers no longer requires touching five different parts of the code — everything cascades from one source of truth.

= 3.7.0 — Den-O: Always Running. Always Accountable. =
Generation used to freeze the browser while it ran. Now it queues in the background and you can watch it happen.

Every content job — manual, bulk, scheduled, or regenerated — runs through a background queue. A live log shows status, the model used, keywords, how long it took, and a direct link to the post when it's done. If something fails, you get the error message and a one-click link to report it.

= 3.6.0 — Kabuto: Built for What's Next =
Two things that SaaS tools charge extra for, now built in.

WordPress 7.0 Connectors support: if your site already has API keys set up through the new WordPress Connectors screen, the plugin finds and uses them automatically. No re-entry. On WordPress 6.x, everything works the same as before.

Bulk generation: paste a list of keywords (or upload a .txt file), pick a template and model, and the plugin generates all the posts sequentially as drafts. Progress updates live. The kind of workflow most autoblogging SaaS platforms charge $99/month for.

= 3.5.0 =
RankMath SEO integration. Keyword groups with per-group categories and templates. A content template system with placeholders (`{keywords}`, `{title}`, `{tone}`, and more). One-click regeneration from the content history. All post-editor AI tools consolidated into a single meta box.

For the full changelog of versions 3.4.0 and earlier, see CHANGELOG.txt.


== Support ==

For support, feature requests, or to contribute to development:
* Visit the [WordPress support forum](https://wordpress.org/support/plugin/automated-blog-content-creator/)
* Submit issues on [GitHub](https://github.com/phalkmin/wp-autoinsight)
* For custom integrations or consulting: phalkmin@protonmail.com
* Support development: [![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/U7U1LM8AP)
