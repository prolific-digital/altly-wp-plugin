# Altly for WordPress

## Description

Altly is a cutting-edge plugin that seamlessly integrates Altly’s AI-powered alt text generation into your WordPress website, transforming your media library into an accessibility and SEO asset. By automatically creating detailed, optimized alt text without overwriting your existing content—and allowing for manual adjustments when needed—Altly saves you time, ensures compliance with accessibility standards, and boosts your site’s search engine performance.

## Documentation

- [Developer Documentation](https://prolificdigital.notion.site/Developer-Documentation-1965efcd8c5f80479165da95acd57d6e "Official Developer Documentation")
- [User Documentation](https://prolificdigital.notion.site/Altly-User-Documention-19b5efcd8c5f807cbd9bdfd14bfe2c52 "Official User Documentation")

| | |
|---|---|
| Requires | WordPress 6.5+, PHP 8.4+ |
| Text domain | `altly` |
| License | GPL-2.0-or-later |

## Installation

Download `altly.zip` from a [GitHub release](https://github.com/prolific-digital/altly-wp-plugin/releases) and upload it through the WordPress backend.

1.  In your WordPress admin panel, go to **Plugins > Add New > Upload Plugin**.
2.  Select and upload the **altly.zip** file, then activate the plugin.
3.  Enter your Altly API key under **Media > Generate Alt Text**.

### Updates

Updates are served by the Prolific Digital license server. Altly is a free-tier entry there (customers authenticate to the Altly API with their Altly API key), so no Prolific license key is needed. The standard `PROLIFIC_LICENSE_KEY_ALTLY` constant and `prolific_license_key` filter are still honored if set. See `PLUGIN-UPDATES.md`.

## Stored data

| Key | Type | Notes |
|---|---|---|
| `altly_license_key` | option | Altly API key |
| `altly_default_mode` | option | Instant / Relaxed default |
| `altly_sync_results_cron` | cron event | hourly pull backstop |
| `_altly_queued`, `_altly_managed` | attachment meta | queue / scope flags |

Uninstalling deletes the two options and the cron event. Alt text and attachment meta are kept.

## Developer Guide

### Getting Started

1.  **Clone the Repository:**

    ```
    git clone <repository-url>
    cd altly-wp-plugin
    ```

2.  **Install Dependencies:**
    Run `composer install` (plugin-update-checker and the phpcs toolchain), then install the JS packages with npm or yarn:

    ```
    composer install
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

2.  **Check the PHP:** `composer lint` (php -l) and `composer phpcs` (WordPress-Extra + PHPCompatibilityWP).

### Releasing

1.  Bump the version in the plugin header, `ALTLY_VERSION`, `readme.txt` `Stable tag` and `package.json`.
2.  Add a `## [x.y.z] - YYYY-MM-DD` section to `CHANGELOG.md` and a `= x.y.z =` entry to `readme.txt`.
3.  Merge to `main`, then push a `vx.y.z` tag. The release workflow builds `altly.zip` (with `composer install --no-dev` and the webpack build, honoring `.distignore`) and attaches it to a GitHub Release.

CI and release logic live in the shared workflows in [`prolific-digital/.github`](https://github.com/prolific-digital/.github).

## Important Note on Local API Development

If you're running the Altly API locally, you can queue and process images and see those changes reflected in your local WordPress environment. To process images locally, use a tool like Bruno or HTTPie to send a GET request to:

```
http://localhost:3000/v2/queue/process/
```

Include an `Authorization` header with your API key. Delivery is pull-only: the plugin pulls finished alt text from the API (via "Sync results" or the hourly cron) and writes it locally — the API never pushes changes to your site. If you're not running the API locally, your local site has nothing to pull from; this setup is provided purely for development convenience.

## Changelog

See `CHANGELOG.md` (Keep a Changelog format; the license server and GitHub release notes read it). `readme.txt` carries a short summary per version.
