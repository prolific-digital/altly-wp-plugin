# Deploying the Altly plugin update server

This is a deployment runbook, not automation — Chris runs each step by hand against the
production VPS. It stands up a self-hosted WordPress update API so `altly` can update
itself outside of wordpress.org, using
[`YahnisElsts/wp-update-server`](https://github.com/YahnisElsts/wp-update-server) (source
cited throughout; verified against the repo's `README.md` on 2026-07-04).

## 1. Overview

The plugin already vendors Plugin Update Checker (PUC) v5.7 and calls
`PucFactory::buildUpdateChecker($metadataUrl, __FILE__, 'altly')` (`altly.php`). PUC polls
a metadata URL twice daily and shows a normal WP Dashboard update notice when a newer
version is available — same UX as a wordpress.org plugin. The metadata URL is
`https://updates.altly.io/?action=get_metadata&slug=altly`, built from
`ALTLY_UPDATE_SERVER_URL` (default `https://updates.altly.io/`, `altly.php` line ~37),
overridable per-site via a `wp-config.php` constant or the `altly_update_metadata_url`
filter. `wp-update-server` is the server half: an endpoint that reads a plugin zip off
disk and serves WordPress the metadata (version, changelog, download URL) it expects.

This server is also the delivery mechanism for the baseline pull+PUC plugin itself — every
customer site needs to get `1.1.0` (the first version with PUC and the pull-model
`receive-alt` webhook) before anything can auto-update. That baseline rollout is what makes
it safe to later retire the legacy push path on the API side — see the cadence and
cross-repo gate in Section 6.

## 2. Provision `updates.altly.io`

In SpinupWP:

1. Create a new site, domain `updates.altly.io`, PHP 8.x. The README's stated minimum is
   **PHP 5.3+ with the Zip extension** for the server component; running 8.x is Prolific's
   own baseline for a new site, not a README requirement — confirm 8.x has no conflict
   before provisioning (assumption, not verified against the README).
2. Issue HTTPS (SpinupWP's Let's Encrypt integration). Since PUC's `Update URI` and
   metadata URL are both `https://`, don't allow plain HTTP for this site.
3. Web root should be the site's public directory — the server code itself is deployed
   directly at web root, not under a subpath (see Section 3), so the working endpoint ends
   up being exactly `https://updates.altly.io/?action=get_metadata&slug=altly`.

## 3. Deploy wp-update-server

Per the README's "Setting Up the Server" steps:

1. Upload the contents of the `wp-update-server` directory (from the
   [`YahnisElsts/wp-update-server`](https://github.com/YahnisElsts/wp-update-server) repo)
   to the site's web root. The README notes you can rename the directory — here it should
   be deployed so its `index.php` sits at the web root, giving the short endpoint above
   rather than a `/wp-update-server/...` path.
2. Make the `cache/` and `logs/` subdirectories writable by the PHP user (README, "Setting
   Up the Server" step 2). On SpinupWP that's typically the site's system user — confirm
   the exact user/group via `spinupwp` before `chown`/`chmod`.
3. No other config file is required by the base server — the README's steps don't call for
   editing anything beyond making those two directories writable. If Chris finds a
   `config.php` or similar in the version actually deployed, treat that as new information
   and confirm it isn't required before skipping it (assumption: base install needs no
   config file, per the README's plain "Getting Started" flow).
4. Confirm `logs/request.log` starts recording once the site is live — the README documents
   its format as `[timestamp] IP_address action slug installed_version wordpress_version
   site_url query_string`, useful later for confirming rollout percentage in Section 6.

## 4. Publish a package

The README's packaging rule: the zip's name must be `<slug>.zip`, and the plugin files must
sit inside a single top-level directory matching the slug (not at the zip root). The README
is explicit that a root-level zip breaks the install itself — "you will run into
inexplicable problems when you try to install an update because WordPress expects plugin
files to be inside a subdirectory."
`yarn zip` in this repo now produces a single top-level `altly/` directory containing the
vendored PUC (fixed in this branch's earlier commits), so it already matches this
requirement.

1. Build the release zip locally:
   ```
   yarn zip
   ```
   This produces `altly.zip` at the repo root.
2. Upload it to the server as `packages/altly.zip` (README: "Copy the Zip file to the
   `packages` subdirectory," overwriting the previous version on every release). Manual
   template:
   ```
   scp altly.zip deploy@updates.altly.io:/var/www/update-server/packages/altly.zip
   ```
   Adjust the remote user and path to match the actual SpinupWP site path — the path above
   mirrors the placeholder already sketched in `.github/workflows/release.yml`'s disabled
   CI step, not a value confirmed against the live server (assumption: confirm the real
   deploy path when the SpinupWP site exists).
3. CI automation is intentionally **not** wired up yet. `.github/workflows/release.yml` has
   a commented-out "Publish package to update server" step that would `scp` `altly.zip` to
   the server on every `v*.*.*` tag push. It's gated on an `UPDATE_SERVER_SSH_KEY` secret
   that doesn't exist yet — don't uncomment it until that secret is added and the target
   path is confirmed against the real server. Until then, every release is published
   manually per step 2.

## 5. Verify the endpoint

```
curl 'https://updates.altly.io/?action=get_metadata&slug=altly'
```

Expect a JSON document (README: "a JSON document containing various information about your
plugin — name, version, description and so on"). wp-update-server / plugin-update-checker
parse this metadata from the zip's plugin header and `readme.txt` at request time, so
there's no manual metadata file to maintain — the README explicitly documents `readme.txt`
as the source for the plugin details page, and header/readme parsing is the package's
documented behavior rather than a line quoted verbatim from the README. Confirm at minimum
`name`, `version`, and `download_url` are present and `version` matches the just-published
zip.

Then confirm the download itself resolves:

```
curl -I '<download_url from the JSON above>'
```

Expect `200` and `Content-Type: application/zip` (or `application/octet-stream`, depending
on server config — note the exact header returned rather than assuming).

## 6. Release cadence: baseline, then auto — critical sequencing

1. **Baseline (`1.1.0`) is a manual install on every site, no exceptions.** Sites running
   pre-PUC versions of `altly` have no update-checker code at all, so they cannot discover
   or install anything the update server publishes — the very first PUC-carrying release
   has to be installed by hand (or however each site's current deploy process works). This
   install also normalizes the plugin's on-disk folder name to `altly/`, which matters
   because PUC derives its slug from `__FILE__`'s parent directory — a mismatched folder
   name (e.g. `altly-ai-text-generator/`) would break the version comparison against the
   `slug=altly` metadata.
2. **Only after 100% of sites are confirmed on `1.1.0`** do later releases become fully
   passive: bump the version in lockstep across `altly.php`'s `Version` header,
   `package.json`'s `version`, and `readme.txt`'s `Stable tag`; run `yarn zip`; republish to
   `packages/altly.zip` (Section 4). Every site with PUC installed picks up the new version
   within its next poll (README: checked twice daily by default).
3. **Cross-repo gate:** removing the legacy push-based delivery path on the API side must
   **not** happen until that same 100%-migrated condition is met — that gate and its
   tracking live in `altly-platform/DEPLOY.md`. Do not treat "update server is live" as
   license to start that removal; the two are sequenced, not parallel.

Chris: confirm what mechanism will be used to track the 100% figure (site count vs. some
telemetry) — this runbook doesn't invent one; `logs/request.log`'s per-request
`installed_version` field is one option worth confirming is sufficient.

## 7. Security notes

- The update server is a separate site (`updates.altly.io`) from any customer's WordPress
  install — it never needs customer credentials or database access.
- `altly.php` sets `Update URI: https://updates.altly.io/` in the plugin header specifically
  so WordPress core skips its wordpress.org update check for this plugin — this matters
  because a same-named `altly` slug could otherwise appear on wordpress.org and hijack the
  update flow. Don't remove this header.
- HTTPS-only, per Section 2 — PUC and the metadata/download URLs are all `https://`.
- Consider restricting who can write to `packages/` (e.g. SSH key restricted to that path,
  or a deploy user scoped to the site only) since anything dropped there as `altly.zip`
  becomes the next auto-installed update for every site on this channel. This is Prolific's
  own recommendation, not a requirement documented in the wp-update-server README — the
  base project has no built-in upload authentication.

## Open items for Chris to confirm

- SpinupWP system user/group for `cache/`/`logs/` permissions (Section 3).
- Whether the base `wp-update-server` release requires any config file beyond writable
  `cache/`/`logs/` (Section 3) — assumed no, per the README's plain setup flow.
- Real deploy path/user for `packages/altly.zip` once the SpinupWP site exists (Section 4)
  — the scp template mirrors the placeholder in `release.yml`, unconfirmed against a live
  server.
- Tracking mechanism for "100% of sites migrated" before the push-removal gate in
  `altly-platform/DEPLOY.md` can be cleared (Section 6).
