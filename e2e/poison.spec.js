const { test, expect } = require("@playwright/test");
const db = require("./helpers/db");
const { runCron, resetMock, mockStats } = require("./helpers/wp");
const { ACCOUNT_ID, LICENSE_KEY, INSTANT_QUEUE } = require("./helpers/config");

test("poison messages: both archived, zero AI calls, no credits spent", async () => {
  // Known-clean starting point for the instant queue + mock counters.
  await resetMock();
  await db.purgeQueue(INSTANT_QUEUE);

  const archivedBefore = await db.archivedCount(INSTANT_QUEUE);
  const creditsBefore = await db.creditsFor(ACCOUNT_ID);

  // (a) Structurally unprocessable (missing image_url): the worker's structural
  //     guard archives it at step 2 — no image fetch, no model call, no spend.
  //     (The old platform_url deliverability guard that caught the *.local
  //     incident is gone with the push path.)
  await db.sendMessage(INSTANT_QUEUE, {
    user_id: ACCOUNT_ID,
    platform_id: "heparks-pro.local",
    api_key: LICENSE_KEY,
    image_id: 999001,
    mode: "instant",
    // no image_url -> archived: "malformed"
  });

  // (b) Well-formed platform_id but read_ct pushed above the cap. Archived by
  //     the read_ct guard at step 1, before the model is ever touched.
  const msgId = await db.sendMessage(INSTANT_QUEUE, {
    image_url:
      "http://127.0.0.1:54521/storage/v1/object/public/images/" +
      ACCOUNT_ID +
      "/poison-readct.jpg",
    user_id: ACCOUNT_ID,
    platform_id: "localhost",
    api_key: LICENSE_KEY,
    image_id: 999002,
    mode: "instant",
  });
  await db.bumpReadCt(INSTANT_QUEUE, msgId, 4);

  expect(await db.queueDepth(INSTANT_QUEUE)).toBe(2);

  const cron = await runCron("/v2/queue/process");
  expect(cron.status).toBe(200);

  // Both messages archived, queue drained, and CRUCIALLY zero model calls.
  const archivedAfter = await db.archivedCount(INSTANT_QUEUE);
  const depthAfter = await db.queueDepth(INSTANT_QUEUE);
  const stats = await mockStats();
  const creditsAfter = await db.creditsFor(ACCOUNT_ID);

  expect(archivedAfter - archivedBefore).toBe(2);
  expect(depthAfter).toBe(0);
  expect(stats.messages).toBe(0);
  expect(creditsAfter).toBe(creditsBefore);

  console.log(
    `[poison] archived +${archivedAfter - archivedBefore}; q_images=${depthAfter}; mock.messages=${stats.messages}; credits ${creditsBefore}->${creditsAfter}`
  );
});
