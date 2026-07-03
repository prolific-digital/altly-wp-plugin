// Poll `fn` until it returns a truthy value or the deadline passes.
async function pollUntil(fn, { timeout = 30_000, interval = 1000, label = "condition" } = {}) {
  const deadline = Date.now() + timeout;
  let last;
  while (Date.now() < deadline) {
    last = await fn();
    if (last) return last;
    await new Promise((r) => setTimeout(r, interval));
  }
  throw new Error(`Timed out after ${timeout}ms waiting for ${label}`);
}

module.exports = { pollUntil };
