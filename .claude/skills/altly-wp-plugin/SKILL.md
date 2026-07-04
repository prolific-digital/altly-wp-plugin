---
name: altly-wp-plugin
description: Conventions and gotchas for the altly-wp-plugin codebase (the WordPress plugin — React admin UI + PHP REST endpoints for the Altly alt-text product). Use when working in the altly-wp-plugin repo — enqueue path, the pull-sync write path, the build, API host, or mode tiers.
---

# altly-wp-plugin quick reference

WordPress plugin: React admin app (`src/` → `build/`) + PHP REST endpoints (`altly.php`).
Pushes images missing alt text to the Altly API, receives alt text back.

## Never break these

- **JS path is live; the old PHP enqueue path is gone.** Functional enqueue =
  `src/components/Shell.js` `handleBulkGenerate()`: multipart POST to
  `REACT_APP_API_QUEUE_URL` (`api.altly.io/v2/queue`), `Authorization: Bearer <apiKey>`,
  FormData `file`, `platform_id` (server-derived `AltlySettings.siteHost`), `api_key`,
  `image_id` (WP attachment id — matched against pull results), `mode`; no `platform_url`
  field (its absence means pull-only); then `altly/v1/mark-queued`. A prior PHP
  `altly_bulk_generate()` (no `image_id`/`api_key`/`mode`) has been removed entirely.
- **Delivery is pull-only — no inbound webhook.** `altly_sync_results()` polls the API
  and writes each row through the shared `altly_write_alt_text($attachment_id,
  $alt_text)`, which enforces the persistent `_altly_managed` scope gate (set once at
  enqueue by `mark-queued`) before writing `_wp_attachment_image_alt` and clearing the
  transient `_altly_queued` flag. Don't remove the `_altly_managed` gate or change the
  pull result-row shape without a coordinated altly-api change.
- **Rebuild after `src/` edits:** WP runs `build/index.js` (enqueued on
  `upload.php?page=altly`), not `src/`. `yarn build` (reads `.env`) / `yarn dev`
  (webpack-dev-server, reads `.env.local`). `build/` is git-ignored.
- **API host `api.altly.io`** — keep `altly.php` constants
  (`ALTLY_API_VALIDATE_URL`/`ALTLY_API_QUEUE_URL`) and `.env` `REACT_APP_*` in sync.
  Never `.ai` in an API URL (marketing links may still be `.ai`).
- **`mode`:** Instant=3 credits, Relaxed=1 (math on the API). Default `"instant"` (PHP
  `altly_default_mode` → `AltlySettings.defaultMode`, JS fallback `|| "instant"`).
  Backward compat: old builds send no `mode` → API treats missing as Instant; never
  make it required.

## Also

- Keep `altly.php` header version = `package.json` version (now `1.0.0`).
- REST endpoints gate on `manage_options` + `wp_rest` nonce (except GET `images`); there
  is no API-facing inbound endpoint.
- Only `image/jpeg`/`image/png` count as "missing alt" (empty string = missing).
- `yarn zip` → `altly.zip`; verify it actually contains `altly.php` (the `zip` script has
  historically referenced a wrong main file / missing `languages` dir).

## Local E2E harness

`@wordpress/env` (`.wp-env.json`) + committed `@playwright/test` suite. Build the plugin
with a `.env.local` pointing `REACT_APP_API_*` at the local altly-api (`localhost:3000`),
then `yarn build`. Seed WP via wp-cli in global-setup (`altly_license_key`,
`altly_default_mode`, upload jpg/png without alt). `yarn e2e` orchestrates the run.
