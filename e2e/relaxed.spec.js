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
} = require("./helpers/wp");
const {
  ACCOUNT_ID,
  BATCH_QUEUE,
  RELAXED_CREDIT_COST,
  IDS_FILE,
} = require("./helpers/config");

test("relaxed tier: bulk generate -> submit + poll -> alt text written, 1 credit spent", async ({
  page,
}) => {
  const ids = JSON.parse(fs.readFileSync(IDS_FILE, "utf8"));
  const target = ids.relaxed;

  await resetMock();
  await db.purgeQueue(BATCH_QUEUE);
  isolateSingleMissing(target.id);

  await login(page);
  await openAltly(page);

  await page.selectOption("#altly-mode", "relaxed");
  await expect(page.locator("#altly-mode")).toHaveValue("relaxed");

  const bulkBtn = page.getByRole("button", { name: "Bulk Generate" });
  await expect(bulkBtn).toBeEnabled();

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

  const creditsBefore = await db.creditsFor(ACCOUNT_ID);

  // Relaxed is the Claude Batch path: submit the pending batch, then poll it.
  const submit = await runCron("/v2/batch/submit");
  expect(submit.status).toBe(200);

  // Poll a few times; the mock returns batch results promptly but not always on
  // the first poll.
  const imageRow = await pollUntil(
    async () => {
      await runCron("/v2/batch/poll");
      const row = await db.latestImageByUrl(target.urlMatch);
      return row && row.alt_text ? row : null;
    },
    { timeout: 60_000, interval: 2000, label: "images.alt_text for relaxed target" }
  );
  expect(imageRow.alt_text.length).toBeGreaterThan(0);

  await pollUntil(
    async () => (await db.creditsFor(ACCOUNT_ID)) === creditsBefore - RELAXED_CREDIT_COST,
    { timeout: 20_000, label: `credits to drop by ${RELAXED_CREDIT_COST}` }
  );
  const creditsAfter = await db.creditsFor(ACCOUNT_ID);
  expect(creditsBefore - creditsAfter).toBe(RELAXED_CREDIT_COST);

  const altMeta = await pollUntil(
    async () => {
      const v = getAltMeta(target.id);
      return v && v.length > 0 ? v : null;
    },
    { timeout: 20_000, label: "_wp_attachment_image_alt on relaxed target" }
  );
  expect(altMeta.length).toBeGreaterThan(0);

  console.log(
    `[relaxed] credits ${creditsBefore} -> ${creditsAfter} (delta ${creditsBefore - creditsAfter}); alt="${imageRow.alt_text}"; wpMeta="${altMeta}"`
  );
});
