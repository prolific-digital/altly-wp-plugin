# Altly for WordPress

## Description

Altly is a cutting-edge plugin that seamlessly integrates Altly’s AI-powered alt text generation into your WordPress website, transforming your media library into an accessibility and SEO asset. By automatically creating detailed, optimized alt text without overwriting your existing content—and allowing for manual adjustments when needed—Altly saves you time, ensures compliance with accessibility standards, and boosts your site’s search engine performance.

## Documentation

- [Developer Documentation](https://prolificdigital.notion.site/Developer-Documentation-1965efcd8c5f80479165da95acd57d6e "Official Developer Documentation")
- [User Documentation](https://prolificdigital.notion.site/Altly-User-Documention-19b5efcd8c5f807cbd9bdfd14bfe2c52 "Official User Documentation")

## Installation

Upload the generated **altly.zip** folder through the WordPress backend.

1.  In your WordPress admin panel, go to **Plugins > Add New > Upload Plugin**.
2.  Select and upload the **altly.zip** file, then activate the plugin.

## Developer Guide

### Getting Started

1.  **Clone the Repository:**

    ```
    git clone <repository-url>
    cd altly-ai-text-generator
    ```

2.  **Install Dependencies:**
    Use either npm or yarn:

    ```
    npm install
    # or
    yarn
    ```

3.  **Start the Development Server:**
    This command watches for file changes so you can preview your updates in real time.
    **Note:** The base URL for your plugin interface depends on your local WordPress environment (for example, `http://localhost`).

    ```
    npm run dev
    # or
    yarn dev
    ```

4.  Open your browser and visit:
    `http://your-local-wordpress-site/wp-admin/upload.php?page=altly`
    For further details, refer to the official developer documentation.

### Environment Variables

The environment variables are **critical** because they define the external API endpoints that the plugin uses to connect to Altly.
When developing locally, create a `.env.local` file in the project root with:

```
REACT_APP_API_VALIDATE_URL=http://localhost:3000/v2/validate
REACT_APP_API_QUEUE_URL=http://localhost:3000/v2/queue
```

If you are not running the API locally, update these values accordingly:

```
REACT_APP_API_VALIDATE_URL=https://api.altly.io/v2/validate
REACT_APP_API_QUEUE_URL=https://api.altly.io/v2/queue
```

### Building and Packaging

1.  **Build the Assets:**

    ```
    yarn build
    # or
    npm run build
    ```

2.  **Create the ZIP Package:** Run the following command to generate a ZIP file named **altly.zip** that includes all necessary files (PHP files, the `build/` folder, `readme.txt`, etc.), while excluding development files such as source maps and environment files:

    ```
    yarn zip
    # or
    npm run zip
    ```

3.  **Test the Plugin:** Upload **altly.zip** via the WordPress plugin installer on a clean WordPress setup to ensure that all features function as expected.

## Important Note on Local API Development

If you're running the Altly API locally, you will be able to queue and process images and see those changes reflected immediately in your local WordPress environment. To process images locally, use a tool like Bruno or HTTPie to send a GET request to:

```
http://localhost:3000/v2/queue/process/
```

Include an `Authorization` header with your API key. If you're not running the API locally, external Altly services will not push changes to your local environment; this setup is provided purely for development convenience.

## FAQ

**Q1: Do I need an API key?**
A: Yes. An API key is required to use the external AI service for generating alt text.

**Q2: How do I get my API key?**
A: You can obtain your API key by logging into your account at [https://app.altly.io](https://app.altly.io/).

**Q3: Will the plugin overwrite my existing alt text?**
A: No. The plugin only generates alt text for images that are missing it, leaving any existing alt text intact.

**Q4: How does this plugin improve SEO?**
A: By automatically generating descriptive, optimized alt text, the plugin helps search engines better understand your images, thereby enhancing your site's SEO.

**Q5: Can I process images locally?**
A: Yes. If you run the Altly API locally, you can queue and process images and see those changes in your local WordPress environment. Use a GET request to `http://localhost:3000/v2/queue/process/` with the proper `Authorization` header. If you're not running the API locally, the external service won't push updates to your local setup.

## Changelog

### 0.0.1

- Initial release of Altly for WordPress.
- Automatic alt text generation using AI.
- Bulk processing and queue management.
- Integration with the WordPress Media Library.
- Support for local API development and environment configuration.
