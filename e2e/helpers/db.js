// Supabase-side assertions via a direct Postgres connection (pg). The local
// stack runs on the dedicated 545xx port block; we talk straight to Postgres so
// we can read the `images` table, credits, and poke pgmq directly.
const { Client } = require("pg");
const { PG_CONNECTION } = require("./config");

function client() {
  return new Client({ connectionString: PG_CONNECTION });
}

async function withClient(fn) {
  const c = client();
  await c.connect();
  try {
    return await fn(c);
  } finally {
    await c.end();
  }
}

/** Current credit balance for an account. */
async function creditsFor(accountId) {
  return withClient(async (c) => {
    const { rows } = await c.query(
      "select credits from accounts where id = $1",
      [accountId]
    );
    if (!rows.length) throw new Error(`No account row for ${accountId}`);
    return Number(rows[0].credits);
  });
}

/** Newest images row whose url contains the given substring (a filename). */
async function latestImageByUrl(urlSubstr) {
  return withClient(async (c) => {
    const { rows } = await c.query(
      "select id, url, alt_text, delivered, status from images where url like $1 order by created_at desc limit 1",
      [`%${urlSubstr}%`]
    );
    return rows[0] || null;
  });
}

/** Live queue depth: pgmq.q_<name>. */
async function queueDepth(name) {
  return withClient(async (c) => {
    const { rows } = await c.query(
      `select count(*)::int as n from pgmq.q_${name}`
    );
    return rows[0].n;
  });
}

/** Archived count: pgmq.a_<name>. */
async function archivedCount(name) {
  return withClient(async (c) => {
    const { rows } = await c.query(
      `select count(*)::int as n from pgmq.a_${name}`
    );
    return rows[0].n;
  });
}

/** Remove every message from a queue (test isolation, not a product path). */
async function purgeQueue(name) {
  return withClient(async (c) => {
    await c.query("select pgmq.purge_queue($1)", [name]);
  });
}

/** Enqueue a raw message. Returns the msg_id. */
async function sendMessage(name, msgObj) {
  return withClient(async (c) => {
    const { rows } = await c.query("select pgmq.send($1, $2::jsonb) as msg_id", [
      name,
      JSON.stringify(msgObj),
    ]);
    return Number(rows[0].msg_id);
  });
}

/**
 * Force a message past the read_ct cap so the worker archives it at step 1
 * without ever calling the model. Also makes it immediately visible.
 */
async function bumpReadCt(name, msgId, readCt = 4) {
  return withClient(async (c) => {
    await c.query(
      `update pgmq.q_${name} set read_ct = $1, vt = now() - interval '1 minute' where msg_id = $2`,
      [readCt, msgId]
    );
  });
}

module.exports = {
  creditsFor,
  latestImageByUrl,
  queueDepth,
  archivedCount,
  purgeQueue,
  sendMessage,
  bumpReadCt,
};
