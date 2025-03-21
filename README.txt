=== WP-AutoInsight ===
Contributors: phalkmin
Tags: openai, anthropic, google-ai, generator, gpt-4, blog
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

WP-AutoInsight revolutionizes content creation by harnessing multiple AI platforms including OpenAI, Anthropic's Claude, and Google's Gemini. Create SEO-optimized blog posts automatically with advanced AI models while maintaining full control over tone, style, and scheduling.

= Key Features =

* **Multi-Platform AI Integration**
  - OpenAI Models (GPT-3.5, GPT-4, GPT-4o, GPT-4.5 Preview)
  - Claude 3, 3.5, and 3.7 Models (Haiku, Sonnet, Opus)
  - Gemini 1.5 and 2.0 Models (Flash, Flash-Lite, Pro)
  - Image generation capabilities with DALL-E 3 and Stability AI
  - Cost-aware model selection with clear pricing tiers

* **Advanced Image Generation**
  - Primary: DALL-E 3 integration for high-quality featured images
  - Fallback: Stability AI support for reliable image generation
  - Smart service selection based on your chosen text model
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
* Claude API key (for Claude 3 models)
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

To use the OpenAI API, you need to sign up for an API key on the OpenAI website. Sign up/log in to OpenAI, go to https://platform.openai.com/api-keys, and click "Create new API key".

= How do I get an GeminiAI API key? =

To use the GeminiAI API, you'll need an API key. You can create one in Google AI Studio https://support.gemini.com/hc/en-us/articles/360031080191-How-do-I-create-an-API-key.

= How do I select between different GPT models and Gemini API? =

In the plugin settings, you can select your preferred AI model from GPT-3.5, GPT-4, and GPT-4o. Gemini API is also available as an alternative or supplementary option.

= How can I customize the generated content? =

The plugin settings allow you to specify keywords, select AI models, set the character limit, and choose content generation frequency. This customization ensures the generated posts align with your blog's theme and audience.

= Can I customize the maximum character limit for generated posts? =

Yes, you can specify the maximum character limit for the generated posts in the plugin settings. The default limit is 200 characters.

= Is it possible to schedule content generation? =

Yes, WP-AutoInsight offers flexible scheduling options. You can automate content creation on an hourly, daily, or weekly basis.

= How can I manually generate a blog post? =

You can manually generate a blog post by clicking the "Create post manually" button on the plugin settings page. This utilizes AJAX to generate a new post without reloading the page..

== Screenshots ==

1. Plugin settings page - Configure API key, keywords, and other options.
2. Example generated blog post using Gutenberg blocks.

== Changelog ==


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