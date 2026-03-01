=== WP-AutoInsight ===
Contributors: phalkmin
Tags: openai, anthropic, google-ai, generator, ai-content
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 3.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Short Description: Automatically create blog posts, rewrite content, and generate infographics using OpenAI, Anthropic Claude, and Google AI APIs.


= Important Notice for Version 3.0.0 =

**BREAKING CHANGES**: This major update includes significant changes and new features that may affect your current configuration. Please review and reconfigure (if needed) your settings after updating:

1. **Backup your settings** before updating (note down your API keys, keywords, and preferences)
3. **Reconfigure your AI model selection** using the new visual interface
4. **Review Content Settings** for any missing configurations
5. **Test content generation** before relying on scheduled posts

We recommend updating on a staging site first. Some custom integrations may require updates due to function signature changes.

== Description ==

WP-AutoInsight revolutionizes content creation by harnessing multiple AI platforms including OpenAI, Anthropic's Claude, and Google's Gemini. Create SEO-optimized blog posts automatically with advanced AI models while maintaining full control over tone, style, and scheduling.

= Key Features =

* **Multi-Platform AI Integration**
  - OpenAI Models (GPT-4.1, GPT-4.1 Mini, o4-mini reasoning model)
  - Claude 4.5 Models (Haiku 4.5, Sonnet 4.5, Opus 4.5)
  - Gemini 2.5 Models (Flash, Flash-Lite, Pro)
  - Image generation capabilities with DALL-E 3, Stability AI, and Gemini Nano Banana
  - Cost-aware model selection with clear pricing tiers

* **Advanced Image Generation**
  - DALL-E 3 integration for high-quality featured images
  - Stability AI support for reliable image generation
  - NEW: Gemini Nano Banana image generation (2.5 Flash and 3 Pro models)
  - Smart service selection based on your chosen text model
  - Configurable image quality (1K, 2K, 4K for Gemini)
  - Customizable image generation preferences

* **Content Customization**
  - Multiple writing tones: Business, Academic, Funny, Epic, Personal, or Custom
  - Category-aware content generation
  - SEO-optimized output with proper HTML structure
  - Adjustable token limits for content length control

* **Flexible Post Management**
  - Automated scheduling (hourly, daily, or weekly)
  - Manual post generation with live preview
  - Email notifications for new content

* **Enhanced Security**
  - Support for wp-config.php API key storage
  - Secure endpoint handling
  - Input sanitization and validation

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
* Stability AI key (optional, for alternative image generation)

For enhanced security, add your API keys to wp-config.php:
```php
define('OPENAI_API', 'your-key-here');
define('CLAUDE_API', 'your-key-here');
define('GEMINI_API', 'your-key-here');
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

WP-AutoInsight 3.0 features a visual model selection interface:
1. Go to WP-AutoInsight > AI Models
2. Browse model cards organized by provider (OpenAI, Claude, Gemini)
3. Click on your preferred model card to select it
4. Each model shows cost tier (Economy/Standard/Premium) and capabilities
5. Save your selection

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
1. Go to Advanced Settings
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
- **Cost Tiers**: Economy (fast/cheap), Standard (balanced), Premium (highest quality)

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
- **WordPress Forum**: https://wordpress.org/support/plugin/wp-autoinsight/
- **GitHub Issues**: https://github.com/phalkmin/wp-autoinsight
- **Direct Contact**: phalkmin@protonmail.com
- **Documentation**: Check the About tab for tutorials and guides

== Screenshots ==

1. Plugin settings page - Configure API key, keywords, and other options.
2. Example generated blog post using Gutenberg blocks.

== Changelog ==

= 3.2.0 =
* Added:
  - **Gemini Nano Banana Image Generation**: Full support for Google's native image generation
    - Nano Banana (Gemini 2.5 Flash Image) for fast, efficient image generation
    - Nano Banana Pro (Gemini 3 Pro Image Preview) for premium quality with text rendering
    - Configurable image sizes: 1K, 2K (default), and 4K resolution
    - Smart fallback chain: matches text model provider or falls back to available services
  - **OpenAI o-series Reasoning Models**: Support for advanced reasoning models
    - o4-mini for complex reasoning tasks
    - Proper routing for models that don't follow the "gpt-" naming convention

* Updated:
  - **AI Models Updated to Latest Versions**:
    - OpenAI: GPT-4.1, GPT-4.1 Mini, GPT-4.1 Nano, o4-mini (replaced GPT-4o/GPT-5)
    - Claude: Claude Haiku 4.5, Claude Sonnet 4.5, Claude Opus 4.5 (replaced 3.x models)
    - Gemini: Gemini 2.5 Flash, Gemini 2.5 Flash-Lite, Gemini 2.5 Pro (replaced 2.0 models)
  - **Token Context Windows**: Added 1M token support for GPT-4.1 models
  - **Model Mappings**: Updated backward compatibility mappings for all providers
  - **Onboarding**: Updated to reference latest model names and capabilities
  - **About Page**: Reflects current model offerings

* Fixed:
  - Image service detection now properly handles o-series model names
  - Content generation routing now supports o-series reasoning models
  - Gemini model was ignored during content generation — the selected model was never passed to the API call, causing it to always fall back to the deprecated `gemini-1.5-flash` default
  - Gemini 2.5 models now correctly use the v1beta API endpoint (previously only 2.0 models were routed there, causing 404 errors for all 2.5 requests)
  - Removed all deprecated model references (GPT-3.5/4/4.5, Claude 3.x, Gemini 1.5/2.0) from token limits, model mappings, and default fallbacks; default model is now GPT-4.1 Mini across the plugin

* Technical Improvements:
  - Enhanced model routing with regex pattern matching for OpenAI o-series
  - Improved image service fallback logic with Gemini as additional option
  - Better error handling and logging for Gemini image generation
  - Added proper MIME type detection for Gemini-generated images

= 3.1.0 =
* Added:
  - **Complete Onboarding Experience**: New user onboarding with 3-step setup process
    - Goal-based configuration (Personal Blog, Business, News, Creative content types)
    - Visual AI model selection with cost indicators
    - Interactive API key testing with detailed error messages
    - Automated first post generation to verify setup
    - Step-by-step documentation for obtaining API keys from all providers
    - Better error handling with specific failure reasons
  - **Smart Configuration**: 
    - Automatic plugin configuration based on user goals
    - Default model selection per provider (GPT-3.5, Claude Haiku, Gemini Flash)
    - Backwards compatibility ensuring existing users never see onboarding

* Fixed:
  - On rewriting, the token count was wrong, causing the returned post to be smaller than the current post

* Technical Improvements:
  - **WordPress Standards**: Enhanced adherence to WordPress coding standards


= 3.0.0 =
* Added:
 - Complete Admin Interface Redesign: New tabbed interface with dedicated sections for Content Settings, Advanced Settings, AI Models, Audio Transcription, and About
 - Audio Transcription Feature: Full OpenAI Whisper integration for converting audio files to blog posts
   - Support for multiple audio formats (MP3, WAV, M4A, WebM, FLAC, MP4)
   - Language selection for transcription
   - Two-step process: transcribe-only or transcribe-and-create-post
   - Audio player embedded in generated posts
 - AI Infographic Generation: Create visual infographics from blog post content using AI
   - Analyzes post content to generate visual descriptions
   - Creates professional infographics using DALL-E or Stability AI
   - Saves directly to WordPress Media Library
 - Enhanced Model Selection Interface: Visual model cards with cost indicators
   - Interactive card-based selection instead of dropdown
   - Clear cost tier indicators (Economy/Standard/Premium)
 - Smart Post Type Selection: Configure which post types show AI content tools
 - Advanced Admin Buttons: 
   - "Create AI Post" button on post list screens
   - "Rewrite with AI" button in post editor
   - "Create Infographic" button for existing posts
 - Enhanced Error Handling: More detailed error messages and logging throughout

* Updated:
 - Model Offerings Updated: 
   - OpenAI: Added GPT-4 Turbo Preview, updated model descriptions
   - Claude: Updated to Claude 3.5 Haiku, Claude 3.7 Sonnet, and Claude Sonnet 4
   - Gemini: Added Gemini 2.0 Flash, Gemini 1.5 Pro Latest, and Gemini 2.5 Pro Preview
 - Content Generation Flow: Improved prompt engineering for better content structure
 - Token Handling: Enhanced token calculation and management across all AI models
 - Admin Scripts: Completely refactored JavaScript for better user experience
 - CSS Styling: Modern, responsive design with improved visual hierarchy

* Fixed:
 - Model Compatibility: Better backward compatibility for existing model selections
 - Content Formatting: Better HTML structure and block generation
 - SEO Integration: Enhanced metadata generation and validation
 - Performance: Optimized AJAX calls and reduced redundant API requests

* Technical Improvements:
 - Code Organization: Better file structure and separation of concerns
 - WordPress Standards: Improved adherence to WordPress coding standards
 - Documentation: Enhanced PHPDoc comments and inline documentation
 - Accessibility: Better keyboard navigation and screen reader support
 - Internationalization: Improved translation readiness throughout the plugin

= 2.1 =
* Added:
  - Advanced Token Management: Better control over content length with intelligent token allocation across different AI models
  - SEO Integration: Automatic generation of SEO metadata with direct Yoast SEO plugin support
  - Improved Content Structure: Enhanced HTML output with proper heading hierarchy
  - Real-time Token Feedback: See estimated token usage before generating content
  - More Reliable AI Integration: Better error handling and model compatibility

* Changed:
  - Refactored content generation for better HTML structure
  - Improved model context window handling for each AI service
  - Enhanced error handling and logging system
  - Better prompt management for consistent output
  - Updated admin interface with token usage indicators

* Fixed:
  - Content formatting issues with HTML tags
  - Token limit calculation accuracy
  - Model selection and API handling edge cases
  - SEO metadata integration reliability
  - HTML structure validation and sanitization


= 2.0 =
* Added:
  - Claude 3 AI model integration (Haiku, Sonnet, and Opus)
  - Enhanced image generation with DALL-E 3
  - Stability AI integration as fallback
  - Cost-aware model selection interface
  - Better security features

* Changed:
  - Refactored code for better maintainability
  - Improved API handling architecture
  - Enhanced content generation quality

* Fixed:
  - Various bug fixes and improvements
  - Better error handling
  - Enhanced stability

= 1.9 =
- Added:
    - Added support for selecting GPT-3.5, GPT-4 and GPT-4o models for content generation;
    - Users of the old GPT-3.5 won't be affected by the change as there is backwards compability;
    - Enhanced plugin settings UI for better user experience and clearer model selection;

- Fixed:
    - Code refinements for better modularity and maintainability.
    - Comprehensive error handling and logging.
    - Updated documentation to guide users through new features and configurations.

= 1.6 =
- Fixed
    - Select2;
	- Tone Select keeping custom value saved;
- Added
    - Minor description of some options;
	- Ko-fi button

= 1.5 =
- Changed
    - Code linting and cleanup;
    - Better form usability;
    - Minor quality of life changes;

= 1.4 =
- Added
	- Gemini API - Now you can use Google AI to create your posts!
    - A tone selector so you can define how the post should be written.
- Changed
    - Now it's possible to use wp-config variables to store the OpenAI and GeminiAI API keys. It's more secure than storing them in the database;
    - The admin menu and form was remade, so it's easier to work and don't get lost on so many options;
    - Minor changes for better usage;

= 1.0 =
- Added
	- Implemented image generation using OpenAI's DALL-E model based on provided prompts and keywords.
	- Introduced the option to select categories for posts, allowing users to specify relevant categories for generated content.
	- Provided an option to receive email notifications when a new post is created automatically.

- Changed
	- Refactored code for better readability, modularity, and maintainability.
	- Updated UI/UX for the settings page to improve user experience.
	- Better scheduled post generation feature, allowing users to schedule automatic post creation at hourly, daily, or weekly intervals.
	- Revised error handling and logging mechanisms for smoother operation.
	

- Fixed
	- Resolved issues related to API key validation and error handling.
	- Fixed bugs and glitches reported by users in the previous version.

= 0.9 =

- Bug Fixes, Quality of life updates - Expect 1.0 in a few days

= 0.8 =

- WordPress Review Guidelines fixes

= 0.5 =

- Initial release.


== Support ==

For support, feature requests, or to contribute to development:
* Visit the [plugin homepage](https://wordpress.org/plugins/automated-blog-content-creator/)
* Submit issues on [GitHub](https://github.com/phalkmin/openai-blog)
* Support development: [![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/U7U1LM8AP)