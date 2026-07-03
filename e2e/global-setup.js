// Playwright global setup:
//   1. Health-check the already-running backend (API :3000, mock :4010,
//      Supabase storage :54521). Fail fast with a clear message if any is down.
//   2. Generate tiny valid PNG fixtures (one per spec, unique filenames).
//   3. Seed WordPress: license key + default mode, import the fixtures as media
//      with NO alt text, and record their attachment ids for the specs.
const fs = require("fs");
const path = require("path");
const { makePng } = require("./helpers/png");
const { wpCli } = require("./helpers/wp");
const {
  LICENSE_KEY,
  API_BASE,
  MOCK_BASE,
  STORAGE_STATUS,
  ARTIFACTS_DIR,
  IDS_FILE,
  FIXTURES_DIR,
} = require("./helpers/config");

const FIXTURES = [
  { key: "instant", file: "altly-instant.png", rgb: [200, 60, 60] },
  { key: "relaxed", file: "altly-relaxed.png", rgb: [60, 160, 90] },
];

async function fetchWithTimeout(url, opts = {}, ms = 5000) {
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

  // altly-api /v2/validate (POST) must return credits.
  try {
    const res = await fetchWithTimeout(`${API_BASE}/v2/validate`, {
      method: "POST",
      headers: { Authorization: `Bearer ${LICENSE_KEY}` },
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok || !body.data || body.data.credits == null) {
      problems.push(
        `altly-api /v2/validate did not return credits (status ${res.status})`
      );
    }
  } catch (e) {
    problems.push(`altly-api unreachable at ${API_BASE}: ${e.message}`);
  }

  // Mock Anthropic server.
  try {
    const res = await fetchWithTimeout(`${MOCK_BASE}/__stats`);
    if (!res.ok) problems.push(`mock Anthropic /__stats status ${res.status}`);
  } catch (e) {
    problems.push(`mock Anthropic unreachable at ${MOCK_BASE}: ${e.message}`);
  }

  // Supabase storage.
  try {
    const res = await fetchWithTimeout(STORAGE_STATUS);
    if (!res.ok) problems.push(`Supabase storage status ${res.status}`);
  } catch (e) {
    problems.push(`Supabase storage unreachable at ${STORAGE_STATUS}: ${e.message}`);
  }

  if (problems.length) {
    throw new Error(
      "Backend health check FAILED. The E2E suite assumes the sibling backend " +
        "(Supabase + altly-api :3000 + mock :4010) is already running.\n  - " +
        problems.join("\n  - ")
    );
  }
}

/** Resolve the real container path of a mounted fixture (slug-agnostic). */
function resolveContainerPath(file) {
  const out = wpCli([
    "eval",
    `$m = glob(WP_PLUGIN_DIR . '/*/e2e/fixtures/${file}'); echo $m ? $m[0] : '';`,
  ]);
  if (!out) throw new Error(`Could not locate fixture ${file} inside wp-env`);
  return out;
}

module.exports = async () => {
  console.log("[global-setup] health-checking backend...");
  await healthCheck();

  // Fixtures on disk (live-mounted into wp-env).
  fs.mkdirSync(FIXTURES_DIR, { recursive: true });
  for (const f of FIXTURES) {
    fs.writeFileSync(path.join(FIXTURES_DIR, f.file), makePng(32, f.rgb));
  }
  console.log("[global-setup] wrote fixtures:", FIXTURES.map((f) => f.file).join(", "));

  // Pretty permalinks are REQUIRED: the React app builds REST URLs as
  // `restUrl + "images?page=..."`. With default "plain" permalinks rest_url()
  // returns `...?rest_route=/altly/v1/`, so appending `images?...` yields a
  // malformed double-`?` URL that 404s and crashes the app. Pretty permalinks
  // make rest_url() end in `/wp-json/altly/v1/`, which appends cleanly.
  wpCli(["rewrite", "structure", "/%postname%/"]);
  wpCli(["rewrite", "flush", "--hard"]);

  // Seed plugin options.
  wpCli(["option", "update", "altly_license_key", LICENSE_KEY]);
  wpCli(["option", "update", "altly_default_mode", "instant"]);

  // Import each fixture as an attachment with no alt text; record its id.
  const ids = {};
  for (const f of FIXTURES) {
    const containerPath = resolveContainerPath(f.file);
    const id = wpCli(["media", "import", containerPath, "--porcelain"]);
    const attachmentId = parseInt(id.split("\n").pop().trim(), 10);
    if (!Number.isInteger(attachmentId)) {
      throw new Error(`media import for ${f.file} returned "${id}"`);
    }
    // Ensure no alt text is present on the freshly imported attachment.
    wpCli(
      ["post", "meta", "delete", String(attachmentId), "_wp_attachment_image_alt"],
      { allowFail: true }
    );
    // WordPress may suffix the stored filename (e.g. altly-instant-1.png) when a
    // prior run left the same name in uploads. The Supabase `images.url` embeds
    // this real basename, so capture it for the specs' url matcher.
    const storedBase = wpCli([
      "eval",
      `echo basename(get_attached_file(${attachmentId}));`,
    ]).trim();
    ids[f.key] = {
      id: attachmentId,
      file: f.file,
      urlMatch: storedBase || f.file,
    };
    console.log(
      `[global-setup] imported ${f.file} -> attachment ${attachmentId} (stored ${storedBase})`
    );
  }

  fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  fs.writeFileSync(IDS_FILE, JSON.stringify(ids, null, 2));
  console.log("[global-setup] wrote", IDS_FILE);
};
