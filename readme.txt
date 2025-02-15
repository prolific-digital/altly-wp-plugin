=== Altly - AI Text Generator ===
Contributors: prolificdigital
Donate link: https://altly.ai/donate
Tags: accessibility, alt text, AI, images, seo
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Altly - AI Text Generator automatically generates detailed, descriptive alt text for your images using artificial intelligence.

== Description ==
Altly - AI Text Generator automatically generates detailed, descriptive alt text for your images using artificial intelligence. 
Improve your site's accessibility and SEO without the hassle of manually writing alt attributes. Once configured, the plugin 
scans your media library for images missing alt text and generates optimized descriptions using a cutting-edge AI API.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/altly-ai-text-generator` directory, or install the plugin through 
   the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Tools > Altly - AI Text Generator** to enter your API license key.
4. Use the dashboard to view images missing alt text, bulk generate alt text, or clear alt text as needed.

== Frequently Asked Questions ==
= How does this plugin generate alt text? =
Altly sends images from your media library to an external AI service which analyzes them and returns descriptive alt text. 
This text is then saved as the image's alt attribute.

= Is my API key secure? =
Yes. The plugin uses WordPress nonces and capability checks to ensure that API operations are performed only by authorized 
users. However, ensure you only use API keys that are meant for public exposure.

= Can I revert changes if needed? =
Yes, you can clear all alt text via the settings page. It is recommended to back up your site before performing bulk actions.

== Screenshots ==
1. **Dashboard View:** Displays images missing alt text along with overall stats.
2. **Settings Page:** Allows you to configure your API key and manage alt text generation.

== Changelog ==
= 1.0.0 =
* Initial release.
* Generates alt text for images using an external AI API.
* Includes bulk generation and clearing of alt text features.
* Provides an intuitive admin interface for API key configuration.

== Upgrade Notice ==
= 1.0.0 =
Initial release. Enjoy your new AI-powered alt text generator!
