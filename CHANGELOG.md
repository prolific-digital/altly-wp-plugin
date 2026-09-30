# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-09-29

### Changed
- Updates now come from the Prolific Digital license server (`api.prolificdigital.io`) instead of GitHub Releases. Altly is free-tier on the license server, so no license key is needed (`PROLIFIC_LICENSE_KEY_ALTLY` / the `prolific_license_key` filter are honored if set). The release workflow still attaches `altly.zip` to GitHub Releases, so 1.1.x sites can update to 1.2.0 from GitHub.
- plugin-update-checker is now installed with Composer (^5.7) instead of a copy bundled in the repository.
- Requires WordPress 6.5+ and PHP 8.4+.
- Plugin header follows the Prolific plugin standard; `ALTLY_VERSION` and `ALTLY_PLUGIN_FILE` constants added.
- Dev dependencies bumped to clear Dependabot alerts.

### Added
- Translations load from `languages/`.
- `uninstall.php` removes `altly_license_key`, `altly_default_mode` and the `altly_sync_results_cron` event. Alt text and attachment meta are kept.
- GPL-2.0 `LICENSE`, this changelog, coding-standards config and CI/release workflows that call the shared Prolific workflows.

### Removed
- The `ALTLY_UPDATE_REPO_URL` constant and `altly_update_repo_url` filter (GitHub update source).
- The `yarn zip` script and the old release workflow.

## [1.1.1] - 2026-07-05

### Fixed
- Saving a new API key takes effect immediately; Bulk Generate and the credits check use the fresh key without a page reload.
- The low-credit notice no longer shows the "credits are exhausted" message when credits remain.

## [1.1.0] - 2026-07-05

### Added
- Self-updates via GitHub Releases (vendored Plugin Update Checker).

### Changed
- Delivery is pull-only: the plugin polls the Altly API for finished alt text ("Sync results" plus an hourly cron) instead of receiving pushes.

## [1.0.0]

### Added
- Initial release: AI alt text generation, bulk generate and clear, API key settings screen.

[Unreleased]: https://github.com/prolific-digital/altly-wp-plugin/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/prolific-digital/altly-wp-plugin/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/prolific-digital/altly-wp-plugin/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/prolific-digital/altly-wp-plugin/releases/tag/v1.1.0
