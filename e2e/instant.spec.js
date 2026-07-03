const fs = require("fs");
const { test, expect } = require("@playwright/test");
const db = require("./helpers/db");
const { pollUntil } = require("./helpers/poll");
const {
  login,
  openAltly,
  isolateSingleMissing,
  getAltMeta,
  runCron,
  resetMock,
  mockStats,
} = require("./helpers/wp");
const {
  ACCOUNT_ID,
  INSTANT_QUEUE,
  INSTANT_CREDIT_COST,
  IDS_FILE,
} = require("./helpers/config");

test("instant tier: bulk generate -> process -> alt text written, 3 credits spent", async ({
  page,
}) => {
  const ids = JSON.parse(fs.readFileSync(IDS_FILE, "utf8"));
  const target = ids.instant;

  // Clean, deterministic state: our target is the only missing/unqueued image,
  // and the instant queue starts empty so exactly one message gets processed.
  await resetMock();
  await db.purgeQueue(INSTANT_QUEUE);
  isolateSingleMissing(target.id);

  // Drive the real admin UI.
  await login(page);
  await openAltly(page);

  await page.selectOption("#altly-mode", "instant");
  await expect(page.locator("#altly-mode")).toHaveValue("instant");

  const bulkBtn = page.getByRole("button", { name: "Bulk Generate" });
  await expect(bulkBtn).toBeEnabled();

  // The queue POST the JS makes for our image.
  const queuePromise = page.waitForResponse(
    (r) =>
      r.url().includes("/v2/queue") &&
      !r.url().includes("/v2/queue/process") &&
      r.request().method() === "POST"
  );

  await bulkBtn.click();

  const queueRes = await queuePromise;
  expect(queueRes.status()).toBe(200);

  await expect(page.getByText(/Bulk generation completed:/)).toContainText(
    "1 images queued, 0 failed."
  );

  // Credits are only deducted when the worker runs, so measure around the cron.
  const creditsBefore = await db.creditsFor(ACCOUNT_ID);

  const cron = await runCron("/v2/queue/process");
  expect(cron.status).toBe(200);

  // Alt text lands in Supabase, credits drop by exactly 3, and the webhook
  // writes the WordPress attachment meta.
  const imageRow = await pollUntil(
    async () => {
      const row = await db.latestImageByUrl(target.urlMatch);
      return row && row.alt_text ? row : null;
    },
    { timeout: 45_000, label: "images.alt_text for instant target" }
  );
  expect(imageRow.alt_text.length).toBeGreaterThan(0);

  await pollUntil(
    async () => (await db.creditsFor(ACCOUNT_ID)) === creditsBefore - INSTANT_CREDIT_COST,
    { timeout: 20_000, label: `credits to drop by ${INSTANT_CREDIT_COST}` }
  );
  const creditsAfter = await db.creditsFor(ACCOUNT_ID);
  expect(creditsBefore - creditsAfter).toBe(INSTANT_CREDIT_COST);

  const altMeta = await pollUntil(
    async () => {
      const v = getAltMeta(target.id);
      return v && v.length > 0 ? v : null;
    },
    { timeout: 20_000, label: "_wp_attachment_image_alt on target" }
  );
  expect(altMeta.length).toBeGreaterThan(0);

  // Instant path uses the single-message endpoint at least once (real SDK path,
  // zero cost via the mock).
  const stats = await mockStats();
  expect(stats.messages).toBeGreaterThanOrEqual(1);

  console.log(
    `[instant] credits ${creditsBefore} -> ${creditsAfter} (delta ${creditsBefore - creditsAfter}); alt="${imageRow.alt_text}"; wpMeta="${altMeta}"; mock.messages=${stats.messages}`
  );
});
