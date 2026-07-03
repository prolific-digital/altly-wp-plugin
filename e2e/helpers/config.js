// Shared constants for the E2E harness. Everything here is local-only and
// matches the already-running backend described in the harness plan.
const path = require("path");

module.exports = {
  // Seeded fixtures (supabase/seed.sql on the altly-platform side).
  ACCOUNT_ID: "11111111-1111-1111-1111-111111111111",
  LICENSE_KEY: "22222222-2222-2222-2222-222222222222",

  // Backend endpoints (all local, all zero-spend).
  API_BASE: "http://localhost:3000",
  MOCK_BASE: "http://127.0.0.1:4010",
  STORAGE_STATUS: "http://127.0.0.1:54521/storage/v1/status",
  PG_CONNECTION: "postgresql://postgres:postgres@127.0.0.1:54522/postgres",

  // Cron auth (matches altly-api .env.e2e CRON_SECRET).
  CRON_SECRET: "e2e-cron-secret",

  // WordPress admin (wp-env dev env; 8888 is occupied by an unrelated env here).
  WP_BASE: "http://localhost:8890",
  WP_ADMIN_USER: "admin",
  WP_ADMIN_PASS: "password",

  // Credit costs (helpers/vision/config.js on the API side).
  INSTANT_CREDIT_COST: 3,
  RELAXED_CREDIT_COST: 1,

  // Queue names (pgmq).
  INSTANT_QUEUE: "images",
  BATCH_QUEUE: "images_batch",

  // Where global-setup records the imported attachment ids for the specs.
  ARTIFACTS_DIR: path.join(__dirname, "..", ".artifacts"),
  IDS_FILE: path.join(__dirname, "..", ".artifacts", "ids.json"),
  FIXTURES_DIR: path.join(__dirname, "..", "fixtures"),
};
