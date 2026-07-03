# CLAUDE.md — Altly WordPress plugin

Altly is an AI alt-text product. This repo is the WordPress plugin that pushes a site's
images (missing alt text) to the Altly API and receives generated alt text back. The admin
UI is a React app; the plugin also exposes a small set of WordPress REST endpoints. Read
this before editing — the parts that are easy to get wrong are front-loaded.

## The one invariant: the `receive-alt` webhook contract

The Altly API calls back into WordPress at `POST /wp-json/altly/v1/receive-alt` to deliver
finished alt text. **This contract is invariant — the API depends on it exactly. Do not
rename fields, change types, or alter the auth check without coordinating a matching change
on the API side.** (Defined in `altly.php`, route registered ~line 320.)

Request body (JSON):

- `image_id` — Number. Cast with `intval()`; must be an existing `attachment` post.
- `alt_text` — string. Run through `sanitize_text_field()`.
- `api_key` — string. Compared for **exact equality** (`===`) against the stored
  `altly_license_key` option. This is the entire auth — there is no nonce, no signature.

On success the handler writes `update_post_meta($image_id, '_wp_attachment_image_alt', $alt_text)`
(falling back to `add_post_meta`), then `delete_post_meta($image_id, '_altly_queued')` to clear
the queued flag. `_wp_attachment_image_alt` is WordPress's standard alt-text meta key — that's
why generated alt shows up in the Media Library.

## The real request path: JS is functional, PHP is legacy/dead

Two code paths send images to the Altly queue. **Only the JavaScript path is functional.**

**Functional (JS):** `src/components/Shell.js` → `handleBulkGenerate()`. It fetches each image
blob and POSTs `multipart/form-data` directly to the queue endpoint (`REACT_APP_API_QUEUE_URL`,
i.e. `https://api.altly.io/v2/queue`) with `Authorization: Bearer <apiKey>` plus these FormData
fields:

- `file` — the image blob
- `platform_id` — `window.location.host`
- `platform_url` — `window.location.origin`
- `api_key` — the license key
- `image_id` — the WP attachment ID
- `mode` — `"instant"` or `"relaxed"` (see below)

`image_id` is the load-bearing field: it's what the API echoes back to `receive-alt` so the
result maps to the right attachment. After a successful queue POST, the JS calls
`altly/v1/mark-queued` to set the `_altly_queued` meta.

**Legacy/dead (PHP):** `altly_bulk_generate()` in `altly.php` (the `altly/v1/bulk-generate`
route) builds its own multipart POST server-side, but it only sends `file`, `platform_id`
(`get_bloginfo('name')`), and `platform_url` (`home_url()`) — **no `image_id`, no `api_key`
field, no `mode`.** Without `image_id` the API cannot route results back through `receive-alt`,
so this path is effectively broken and unused. The React UI never calls `bulk-generate`. Treat
it as dead code; don't extend it — put new enqueue logic in `Shell.js`.

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
- `yarn zip` packages a release. Heads-up: the current `zip` script references
  `altly-ai-text-generator.php` and a `languages` dir that don't exist in this repo (the main
  file is `altly.php`) — the produced zip is likely missing the main plugin file. Verify/fix the
  zip contents before shipping.

Stack: React 18 + Tailwind, bundled by webpack 5 via Babel; `dotenv-webpack` injects the
`REACT_APP_*` vars. Release automation: `.github/workflows/release.yml` runs `yarn zip` on
`v*.*.*` tags and uploads `altly.zip` to a GitHub Release.

## E2E / local WP harness — planned, NOT present

There is currently **no** Playwright suite, no `yarn e2e` script, and no `.wp-env.json` in this
repo. A `@wordpress/env` local WordPress + a committed `@playwright/test` suite (drive the admin
bulk-generate for both Instant and Relaxed, assert alt text is written and credits deducted) are
planned but not yet built. If you're asked to "run the E2E tests," they don't exist yet — scaffold
them rather than assuming.

## Other things worth knowing

- **Version:** the `altly.php` header (the one WordPress reads) and `package.json`
  are now aligned at `1.0.0` (the header was bumped from `0.1.0` to match). Keep
  them in sync on every release; confirm the intended number before shipping.
- **`yarn zip`** bundles `altly.php` (the real main file). It previously referenced
  a non-existent `altly-ai-text-generator.php`, so the zip shipped without the
  plugin bootstrap — fixed.
- The admin REST endpoints (`images`, `validate-key`, `save-key`, `save-mode`, `bulk-generate`,
  `mark-queued`, `clear-alt-text`, `clear-queue`) all gate on `manage_options` and, except the
  GET `images`, verify the `wp_rest` nonce via `altly_verify_rest_nonce()`. `receive-alt` is the
  exception — it's API-facing and authed by the `api_key` equality check described above.
- Only `image/jpeg` and `image/png` attachments are queried as "missing alt"; the images list
  treats empty-string alt as missing too.
- Active development happens on feature branches (current: `feat/vision-claude-migration`).

## Delegate to the repo subagent

**Non-trivial work in this repo MUST be dispatched to the `altly-wp-plugin-expert`
subagent** (via the Task tool) rather than edited directly, so the load-bearing rules
above (only the JS `Shell.js handleBulkGenerate` path is live — the PHP `bulk-generate`
is dead; the invariant `receive-alt` contract; rebuild with `yarn build` after `src/`
edits; keep `altly.php` constants and `.env` `REACT_APP_*` on `api.altly.io`; `mode`
defaults to Instant for backward-compat) are always applied. Only trivial one-line or doc
edits may bypass it. The subagent and a matching `altly-wp-plugin` skill live in
`.claude/`.
