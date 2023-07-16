# Alt Text Generator for WordPress

This WordPress plugin automatically generates alt text for images without alt text in the media library. It uses the Google Vision API and the OpenAI API to generate a grammatically correct description of the image.

## Features

- Scans all images in the WordPress media library.
- Identifies images that do not have alt text.
- Sends these images to the Google Vision API to identify objects in the image.
- Sends the results from the Google Vision API to the OpenAI API to generate a grammatically correct description.
- Updates the alt text of the image with the generated description.
- Provides a settings screen with a button to start the alt text generation process and a progress bar to track the progress.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/alt-text-generator` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage

1. Go to the settings screen for the plugin.
2. Click on the "Generate Alt Text" button.
3. The plugin will start processing the images and the progress bar will show the progress.

## License

This plugin is proprietary software. Unauthorized copying, modification, distribution, or use of this software, via any medium, is strictly prohibited. For inquiries about the usage of this software, please contact the owner.

## Support

If you need help with this plugin, please contact support at support@prolificdigital.com.

## Contributing

As this is a proprietary plugin, we are not accepting contributions at this time.

## Note

Please note that the Google Vision API and OpenAI API are not free and using them will incur charges. The plugin is provided as-is, without warranty of any kind.
