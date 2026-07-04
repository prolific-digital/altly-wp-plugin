// WordPress-side helpers: wp-cli (through wp-env), admin login, and the Altly
// admin page. Also small utilities to hit the API crons and the mock server.
const { spawnSync } = require("child_process");
const {
  WP_ADMIN_USER,
  WP_ADMIN_PASS,
  API_BASE,
  MOCK_BASE,
  CRON_SECRET,
} = require("./config");

/**
 * Run a wp-cli command inside the wp-env `cli` container (the dev env, :8890).
 * Args are passed as an array so we never fight shell quoting.
 */
function wpCli(args, { allowFail = false } = {}) {
  const res = spawnSync("npx", ["wp-env", "run", "cli", "wp", ...args], {
    encoding: "utf8",
    cwd: process.cwd(),
  });
  if (res.status !== 0 && !allowFail) {
    throw new Error(
      `wp-cli failed (${args.join(" ")}):\n${res.stdout || ""}\n${res.stderr || ""}`
    );
  }
  return (res.stdout || "").trim();
}

/** Read an attachment's alt-text meta. Returns "" when unset. */
function getAltMeta(attachmentId) {
  return wpCli(
    ["post", "meta", "get", String(attachmentId), "_wp_attachment_image_alt"],
    { allowFail: true }
  );
}

/**
 * Make exactly one attachment "missing alt" and un-queued: seed alt text on
 * everything, then clear it (and the queued flag) on the target. Deterministic
 * regardless of how many attachments prior runs left behind.
 */
function isolateSingleMissing(targetId) {
  const php = [
    "$ids = get_posts([",
    "  'post_type' => 'attachment',",
    "  'post_status' => 'inherit',",
    "  'posts_per_page' => -1,",
    "  'fields' => 'ids',",
    "]);",
    "foreach ($ids as $i) {",
    "  update_post_meta($i, '_wp_attachment_image_alt', 'seeded-baseline');",
    "  delete_post_meta($i, '_altly_queued');",
    "}",
    `delete_post_meta(${Number(targetId)}, '_wp_attachment_image_alt');`,
    `delete_post_meta(${Number(targetId)}, '_altly_queued');`,
    `echo 'isolated ${Number(targetId)}';`,
  ].join(" ");
  return wpCli(["eval", php]);
}

/** Log into wp-admin via the login form. */
async function login(page) {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", WP_ADMIN_USER);
  await page.fill("#user_pass", WP_ADMIN_PASS);
  await Promise.all([
    page.waitForNavigation(),
    page.click("#wp-submit"),
  ]);
}

/** Open the Altly admin page and wait for the React app to mount. */
async function openAltly(page) {
  await page.goto("/wp-admin/upload.php?page=altly");
  await page.waitForSelector("#altly-mode", { timeout: 30_000 });
}

/**
 * Run the plugin's pull-sync wp-cron event (altly_sync_results_cron) via wp-cli.
 * This is how finished alt text lands in WordPress now that the plugin pulls
 * from /v2/results instead of relying on the API's push webhook. Returns the
 * wp-cli output (best-effort; allowFail so a benign "no events" doesn't abort).
 */
function syncResults() {
  return wpCli(["cron", "event", "run", "altly_sync_results_cron"], {
    allowFail: true,
  });
}

/** Trigger an API cron route with the CRON_SECRET bearer header. */
async function runCron(pathname) {
  const res = await fetch(`${API_BASE}${pathname}`, {
    headers: { Authorization: `Bearer ${CRON_SECRET}` },
  });
  const body = await res.json().catch(() => ({}));
  return { status: res.status, body };
}

/** Reset the mock Anthropic counters + forget batches. */
async function resetMock() {
  const res = await fetch(`${MOCK_BASE}/__reset`, { method: "POST" });
  return res.json();
}

/** Current mock call counters. */
async function mockStats() {
  const res = await fetch(`${MOCK_BASE}/__stats`);
  return res.json();
}

module.exports = {
  wpCli,
  getAltMeta,
  isolateSingleMissing,
  login,
  openAltly,
  runCron,
  syncResults,
  resetMock,
  mockStats,
};
