# CLAUDE.md — Altly WordPress plugin

Altly is an AI alt-text product. This repo is the WordPress plugin that uploads a site's
images (missing alt text) to the Altly API, then **pulls** the generated alt text back from
the API and writes it locally (the API never POSTs to customer sites). The admin UI is a
React app; the plugin also exposes a small set of WordPress REST endpoints. Read this
before editing — the parts that are easy to get wrong are front-loaded.

## Delivery is pull-only: `altly_write_alt_text` + `altly_sync_results`

There is no inbound webhook. The plugin polls the Altly API for finished alt text and
writes it locally. The pull job `altly_sync_results()` (in `altly.php`) fetches
undelivered rows from `ALTLY_API_RESULTS_URL` (`/v2/results`), writes each one through
the shared `altly_write_alt_text($attachment_id, $alt_text)`, then acks the drained ids
against `ALTLY_API_RESULTS_ACK_URL` (`/v2/results/ack`) so a re-pull doesn't refetch them.
It runs on an admin-triggered "Sync results" REST endpoint (`altly/v1/sync-results`) and
on an hourly wp-cron backstop (`ALTLY_SYNC_CRON_HOOK`) for low-traffic sites.

`altly_write_alt_text()` is the single write path and enforces the persistent
`_altly_managed` scope gate (H-8): the target attachment must carry `_altly_managed` meta
(set once at enqueue time by `altly/v1/mark-queued`, never deleted) or the write is
rejected 403 (`not_managed`) — this stops the pull job from rewriting alt on arbitrary
media if a row's id were ever wrong. On success it writes
`update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text)` (falling back to
`add_post_meta`), then `delete_post_meta($attachment_id, '_altly_queued')` to clear the
**transient** in-flight flag. `_wp_attachment_image_alt` is WordPress's standard alt-text
meta key — that's why generated alt shows up in the Media Library. Note the two markers
differ: `_altly_queued` is transient (cleared once the pull job lands the alt text; drives
the admin UI's "queued" badge) while `_altly_managed` is permanent (the H-8 scope gate) —
a redelivery of an already-processed row therefore still succeeds idempotently.

## The real request path: JS is functional, PHP is legacy/dead

Two code paths send images to the Altly queue. **Only the JavaScript path is functional.**

**Functional (JS):** `src/components/Shell.js` → `handleBulkGenerate()`. It fetches each image
blob and POSTs `multipart/form-data` directly to the queue endpoint (`REACT_APP_API_QUEUE_URL`,
i.e. `https://api.altly.io/v2/queue`) with `Authorization: Bearer <apiKey>` plus these FormData
fields:

- `file` — the image blob
- `platform_id` — `AltlySettings.siteHost` (server-derived; must be byte-identical to
  `altly_site_host()`, NOT `window.location.host`, since the API scopes queued rows by
  `(user, platform_id)` and the pull/ack path uses the server-derived value)
- `api_key` — the license key
- `image_id` — the WP attachment ID
- `mode` — `"instant"` or `"relaxed"` (see below)

There is deliberately no `platform_url` field — there is no push delivery to skip. The API
never POSTs to customer sites; the push path and its `platform_url` plumbing were removed
API-side (Stage 4, 2026-07-05). This plugin only PULLS finished alt text from the API (see
"Delivery is pull-only" above), so the queued row is simply left for the pull job to claim.
`image_id` is the load-bearing field:
it's what the pull job (`altly_sync_results`) matches against when the API's `/v2/results`
row comes back, so the alt text lands on the right attachment. After a successful queue
POST, the JS calls `altly/v1/mark-queued` to set the `_altly_queued` meta.

**Legacy/dead (PHP):** a server-side `altly_bulk_generate()` (`altly/v1/bulk-generate`
route) previously existed and built its own multipart POST, but it sent no `image_id`, no
`api_key`, no `mode`, so it could never route results back — it has since been removed
from `altly.php` entirely. If you ever see it referenced, treat it as gone; put all
enqueue logic in `Shell.js`.

## `mode`: Instant vs Relaxed (two credit tiers)

`mode` selects the speed/credit tier. Per the API: **Instant costs 3 credits per image,
Relaxed costs 1.** The plugin only sends the string; credit math lives on the API.

- Default is `"instant"`. Set in two places: the PHP option `altly_default_mode` (defaults to
  `"instant"`, only `"instant"`/`"relaxed"` accepted) surfaced to JS as `AltlySettings.defaultMode`,
  and the JS fallback `AltlySettings.defaultMode || "instant"`.
- Account default is saved via `altly/v1/save-mode`; the Dashboard has a per-run Speed dropdown
  (Shell.js) that overrides the default for that batch.
- **Backward compatibility:** older plugin builds send no `mode` field at all. The API must keep
  treating a missing `mode` as Instant. Don't make `mode` required on the API.

## API host is `api.altly.io` (not `.ai`)

The API host was recently corrected from `api.altly.ai` to **`api.altly.io`**. Keep these in sync:

- `altly.php`: `ALTLY_API_VALIDATE_URL = https://api.altly.io/v2/validate` and
  `ALTLY_API_QUEUE_URL = https://api.altly.io/v2/queue`.
- `.env` (read by the webpack build): `REACT_APP_API_VALIDATE_URL` / `REACT_APP_API_QUEUE_URL`,
  both on `api.altly.io`.

Note: some cosmetic links still point at the old `altly.ai` domain (the plugin header
`Author URI`, the logo anchor in Shell.js). Those are marketing links, not API calls — leave
them unless doing a deliberate rebrand pass, but don't copy `.ai` into any API URL.

## Build & local flow — you MUST rebuild after editing `src/`

WordPress loads `build/index.js` (and `build/index.css`), enqueued only on the Media → Generate
Alt Text page (`upload.php?page=altly`, hook `media_page_altly`). **The `src/` tree is not what
WordPress runs.** Editing `Shell.js` (or any component) has no effect until you rebuild.

```
yarn install
yarn build      # webpack --mode production → writes build/, reads .env
```

- `build/` is git-ignored and not committed — expect an empty repo to have no `build/` until you
  run the build.
- `yarn dev` runs webpack-dev-server (writeToDisk: true) and reads **`.env.local`** instead of
  `.env`. Create `.env.local` pointing at your local API (e.g. `http://localhost:3000/v2/...`)
  for local development; it is git-ignored and not present by default.
- `yarn zip` packages a release. It now references the real main file (`altly.php`), stages a
  single top-level `altly/` dir, and includes the `languages/` dir (which exists) — so the
  produced zip contains the plugin bootstrap. Still sanity-check the zip contents before shipping.

Stack: React 18 + Tailwind, bundled by webpack 5 via Babel; `dotenv-webpack` injects the
`REACT_APP_*` vars. Release automation: `.github/workflows/release.yml` runs `yarn zip` on
`v*.*.*` tags and uploads `altly.zip` to a GitHub Release.

## E2E / local WP harness — present

The harness is built: `.wp-env.json`, `scripts/e2e.mjs` (the `yarn e2e` orchestrator), and a
committed `@playwright/test` suite under `e2e/` (`instant.spec.js`, `relaxed.spec.js`,
`poison.spec.js`) all exist. It brings up a `@wordpress/env` local WordPress, drives the admin
Bulk Generate for both Instant and Relaxed, and asserts alt text is pulled in and credits are
deducted — at zero provider spend (mock Anthropic). `yarn e2e` requires the sibling backend
(local Supabase + altly-api + mock Anthropic) already running; see `e2e/README.md` for the
prerequisites and run steps.

## Other things worth knowing

- **Version:** the `altly.php` header (the one WordPress reads) and `package.json`
  are now aligned at `1.1.0` (the Phase B baseline with PUC + pull-model delivery).
  Keep them in sync on every release; confirm the intended number before shipping.
- **`yarn zip`** bundles `altly.php` (the real main file). It previously referenced
  a non-existent `altly-ai-text-generator.php`, so the zip shipped without the
  plugin bootstrap — fixed.
- All REST endpoints (`images`, `validate-key`, `save-key`, `save-mode`,
  `mark-queued`, `clear-alt-text`, `clear-queue`, `sync-results`) gate on `manage_options`
  and, except the GET `images`, verify the `wp_rest` nonce via `altly_verify_rest_nonce()`.
  There is no API-facing inbound endpoint — delivery is pull-only (see above), so every
  route on this plugin is admin-only.
- Only `image/jpeg` and `image/png` attachments are queried as "missing alt"; the images list
  treats empty-string alt as missing too.
- Active development happens on feature branches (current: `feat/vision-claude-migration`).

## Delegate to the repo subagent

**Non-trivial work in this repo MUST be dispatched to the `altly-wp-plugin-expert`
subagent** (via the Task tool) rather than edited directly, so the load-bearing rules
above (only the JS `Shell.js handleBulkGenerate` path is live — the PHP `bulk-generate`
is dead; delivery is pull-only via `altly_write_alt_text` + `altly_sync_results`; rebuild
with `yarn build` after `src/` edits; keep `altly.php` constants and `.env` `REACT_APP_*`
on `api.altly.io`; `mode` defaults to Instant for backward-compat) are always applied.
Only trivial one-line or doc edits may bypass it. The subagent and a matching
`altly-wp-plugin` skill live in `.claude/`.
