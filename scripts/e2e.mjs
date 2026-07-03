#!/usr/bin/env node
// Orchestrates the Altly plugin E2E run. Idempotent / re-runnable.
//
// ASSUMES the sibling backend is already running (Supabase on the 545xx block,
// altly-api on :3000, mock Anthropic on :4010). It health-checks them and fails
// fast with a clear message rather than trying to start them.
//
// Steps: health-check -> write .env.local -> yarn build -> install chromium ->
// wp-env start -> playwright test.
import { spawnSync } from "node:child_process";
import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");

const API_BASE = "http://localhost:3000";
const MOCK_BASE = "http://127.0.0.1:4010";
const STORAGE_STATUS = "http://127.0.0.1:54521/storage/v1/status";
const LICENSE_KEY = "22222222-2222-2222-2222-222222222222";

function run(cmd, args, opts = {}) {
  console.log(`\n$ ${cmd} ${args.join(" ")}`);
  const res = spawnSync(cmd, args, { stdio: "inherit", cwd: ROOT, ...opts });
  if (res.status !== 0) {
    throw new Error(`\`${cmd} ${args.join(" ")}\` exited with ${res.status}`);
  }
}

async function fetchTimeout(url, opts = {}, ms = 5000) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), ms);
  try {
    return await fetch(url, { ...opts, signal: ctrl.signal });
  } finally {
    clearTimeout(t);
  }
}

async function healthCheck() {
  const problems = [];
  try {
    const res = await fetchTimeout(`${API_BASE}/v2/validate`, {
      method: "POST",
      headers: { Authorization: `Bearer ${LICENSE_KEY}` },
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok || !body?.data || body.data.credits == null) {
      problems.push(`altly-api /v2/validate returned no credits (status ${res.status})`);
    } else {
      console.log(`  altly-api OK (credits=${body.data.credits})`);
    }
  } catch (e) {
    problems.push(`altly-api unreachable at ${API_BASE}: ${e.message}`);
  }
  try {
    const res = await fetchTimeout(`${MOCK_BASE}/__stats`);
    if (!res.ok) problems.push(`mock Anthropic /__stats status ${res.status}`);
    else console.log("  mock Anthropic OK");
  } catch (e) {
    problems.push(`mock Anthropic unreachable at ${MOCK_BASE}: ${e.message}`);
  }
  try {
    const res = await fetchTimeout(STORAGE_STATUS);
    if (!res.ok) problems.push(`Supabase storage status ${res.status}`);
    else console.log("  Supabase storage OK");
  } catch (e) {
    problems.push(`Supabase storage unreachable: ${e.message}`);
  }
  if (problems.length) {
    console.error(
      "\nBackend NOT ready. This harness does not start Supabase/altly-api/mock — " +
        "bring them up first, then re-run.\n  - " +
        problems.join("\n  - ")
    );
    process.exit(1);
  }
}

(async () => {
  console.log("== E2E: health-checking backend ==");
  await healthCheck();

  console.log("\n== Writing .env.local (points the build at local altly-api) ==");
  writeFileSync(
    join(ROOT, ".env.local"),
    [
      "REACT_APP_API_VALIDATE_URL=http://localhost:3000/v2/validate",
      "REACT_APP_API_QUEUE_URL=http://localhost:3000/v2/queue",
      "",
    ].join("\n")
  );

  // dotenv-webpack is wired so a DEVELOPMENT build reads .env.local and a
  // PRODUCTION build reads the committed .env (api.altly.io). We build in dev
  // mode so the local API URLs get embedded without touching the committed .env.
  console.log("\n== Building plugin (dev mode -> embeds .env.local) ==");
  run("npx", ["webpack", "--mode", "development"]);

  console.log("\n== Ensuring Playwright chromium is installed ==");
  run("npx", ["playwright", "install", "chromium"]);

  console.log("\n== Starting wp-env (idempotent) ==");
  run("npx", ["wp-env", "start"]);

  console.log("\n== Running Playwright ==");
  run("npx", ["playwright", "test"]);

  console.log("\n== E2E complete ==");
})().catch((e) => {
  console.error("\nE2E orchestration failed:", e.message);
  process.exit(1);
});
