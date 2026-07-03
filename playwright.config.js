// @ts-check
const { defineConfig, devices } = require("@playwright/test");

/**
 * Playwright config for the Altly plugin E2E harness.
 *
 * The suite drives the real WordPress admin (wp-env on :8890), enqueues images
 * through the live React UI to the local altly-api (:3000), triggers the crons,
 * and asserts against Supabase + the mock Anthropic server. ZERO provider spend:
 * the mock at :4010 is what stands in for Anthropic.
 *
 * Serial + single worker on purpose: the specs share one Supabase account and
 * one WordPress install, and assert on credit deltas / queue depth, so they must
 * not race each other.
 */
module.exports = defineConfig({
  testDir: "./e2e",
  globalSetup: require.resolve("./e2e/global-setup.js"),
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [["list"]],
  use: {
    baseURL: "http://localhost:8890",
    headless: true,
    trace: "retain-on-failure",
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
