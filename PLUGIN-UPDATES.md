# Plugin auto-updates

This documents how `altly` auto-updates in the field and how to cut a release.

## 1. Overview

Since 1.2.0 the plugin updates from the **Prolific Digital license server**, like every other
Prolific plugin. Plugin Update Checker (PUC) ^5.7 is installed with Composer (`vendor/` is not
committed; release zips include it). `includes/update-checker.php`, loaded from `altly.php`
after `vendor/autoload.php`, calls:

```php
PucFactory::buildUpdateChecker(
	'https://api.prolificdigital.io/api/update?plugin=altly',
	ALTLY_PLUGIN_FILE,
	'altly'
);
```

and adds a `license_key` query arg when a key is configured, either with the
`PROLIFIC_LICENSE_KEY_ALTLY` constant in `wp-config.php` or the `prolific_license_key`
filter (`( $key, 'altly' )`). Altly is registered as a **free** plugin on the license
server (customers authenticate to the Altly API with their Altly key, not a Prolific
license), so updates are served without a key. If `vendor/` is missing (a git checkout without
`composer install`), the updater is skipped and the rest of the plugin still boots.

The `Update URI` header points at the same license-server URL, so WordPress core never
checks wordpress.org for an `altly` slug.

### Sites on 1.1.x

1.1.0 and 1.1.1 poll **GitHub Releases** on `prolific-digital/altly-wp-plugin` for an
attached `altly.zip`. The shared release workflow still attaches `altly.zip` to each GitHub
Release, so those sites can pick up 1.2.0 from GitHub while the repo stays public. From 1.2.0
on, sites poll the license server only. The old `ALTLY_UPDATE_REPO_URL` constant and
`altly_update_repo_url` filter no longer do anything.

## 2. Cutting a release

1. Bump the version in lockstep: `altly.php`'s `Version:` header and `ALTLY_VERSION`,
   `package.json`'s `version`, and `readme.txt`'s `Stable tag`.
2. Add a `## [x.y.z] - YYYY-MM-DD` section to `CHANGELOG.md` and a `= x.y.z =` entry to
   `readme.txt`.
3. Merge to `main`, then tag the commit `vX.Y.Z` and push the tag.
4. `.github/workflows/release.yml` calls the shared
   `prolific-digital/.github` release workflow: it checks the tag equals the header
   Version, runs `composer install --no-dev` and the webpack build, zips the plugin into
   `altly.zip` (top-level `altly/`, honoring `.distignore`) and attaches it to a GitHub
   Release whose notes come from `CHANGELOG.md`.
5. The license server serves the new version to every site (free tier).

## 3. Security notes

- No GitHub token or other credential ships with the plugin.
- Keep the `Update URI` header; it stops a same-named wordpress.org plugin from hijacking
  the update flow.
- Do not commit `vendor/`; it is built into the release zip by Composer.

## Notes

- **Migration gate (historic):** the 100%-migration gate that fronted removal of the legacy
  push delivery path was moot when push removal shipped to prod (2026-07-05) because there
  were no live customer sites. It stays the binding rule for any future change that removes
  a server-side delivery path: confirm every site is on a capable build first.
