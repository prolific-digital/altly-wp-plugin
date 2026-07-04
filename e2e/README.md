# Altly plugin — end-to-end harness

Local WordPress (via `@wordpress/env`) + Playwright suite that drives the real
plugin admin UI end to end and proves the queue → worker → webhook flow, at
**zero provider spend**.

## What it proves

- **`instant.spec.js`** — Bulk Generate in Instant mode enqueues to the API, the
  instant worker (`/v2/queue/process`) generates alt text and writes it to
  Supabase, the plugin **pulls** the result (`altly_sync_results_cron`, driven by
  the `syncResults()` helper) into the WordPress attachment, and exactly **3**
  credits are spent.
- **`relaxed.spec.js`** — same in Relaxed mode via the Claude Batch path
  (`/v2/batch/submit` + `/v2/batch/poll`), spending exactly **1** credit; the
  WordPress alt text likewise lands via the pull-sync cron.

Note: the plugin no longer relies on the API's push webhook. The enqueue omits
`platform_url`, so the API leaves finished rows for the plugin to pull from
`/v2/results` and ack via `/v2/results/ack`.
- **`poison.spec.js`** — two bad instant messages (an undeliverable `*.local`
  `platform_url`, and one past the `read_ct` cap) are both **archived** with
  **zero** model calls and **zero** credits spent.

## Prerequisites — the backend must already be running

This harness does **not** start the backend. Bring these up first (they live in
the sibling repos):

- Supabase (dedicated 545xx ports): REST `:54521`, Postgres `:54522`.
- altly-api (`next dev`) on `:3000`, configured via its `.env.e2e` to point at
  local Supabase + a **mock Anthropic** server, with `CRON_SECRET=e2e-cron-secret`
  and `ALTLY_DELIVERABLE_HOST_ALLOWLIST=localhost`.
- Mock Anthropic server on `:4010` (zero-spend), exposing `GET /__stats` and
  `POST /__reset`.

The suite health-checks all three and fails fast if any is down.

Docker is also required (for `@wordpress/env`).

## Run

```bash
yarn install          # once, to pull @wordpress/env, @playwright/test, pg
yarn e2e              # health-check -> build (dev, embeds .env.local) ->
                      # install chromium -> wp-env start -> playwright test
```

`yarn e2e` is idempotent/re-runnable. To run just the specs against an already
built plugin + started wp-env: `yarn e2e:test`.

## How it stays zero-spend

The API's real Anthropic SDK path runs, but `ANTHROPIC_BASE_URL` points at the
mock on `:4010`, so no real model call is ever made. The poison spec asserts
`mock /__stats { messages: 0 }` to prove the guards archive bad messages before
any model call.

## Notes / assumptions

- Specs run **serially, single worker** — they share one Supabase account and
  one WordPress install and assert on credit deltas and queue depth.
- Credit assertions are **deltas** (read-before / read-after), so the suite is
  robust to whatever balance the account currently has.
- `global-setup.js` generates fresh PNG fixtures, seeds the license key + default
  mode, imports one attachment per spec (no alt text), and records their ids in
  `.artifacts/ids.json`.
- The build is done in **development mode** on purpose: `dotenv-webpack` reads
  `.env.local` (local API URLs) in dev and the committed `.env` (api.altly.io) in
  production, so the harness never touches the committed `.env`.
