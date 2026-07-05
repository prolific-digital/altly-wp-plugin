# Plugin auto-updates via GitHub Releases

This documents how `altly` auto-updates in the field and how to cut a release. The update
source is **GitHub Releases on the public
[`prolific-digital/altly-wp-plugin`](https://github.com/prolific-digital/altly-wp-plugin)
repo** — there is no self-hosted update server.

## 1. Overview

The plugin vendors Plugin Update Checker (PUC) v5.7 and, at `altly.php` require-time, calls
`PucFactory::buildUpdateChecker($altly_repo_url, __FILE__, 'altly')` with
`$altly_repo_url` = `ALTLY_UPDATE_REPO_URL` (default
`https://github.com/prolific-digital/altly-wp-plugin/`, overridable per-site via a
`wp-config.php` constant or the `altly_update_repo_url` filter). Because the URL points at
`github.com`, PUC's factory (`PucFactory::buildUpdateChecker`,
`vendor/plugin-update-checker/Puc/v5p7/PucFactory.php`) auto-detects the GitHub VCS provider
and returns a `Vcs\PluginUpdateChecker` wired to `Vcs\GitHubApi` — the same public
`getVcsApi()` accessor and `enableReleaseAssets()` method used by every VCS-backed PUC
checker (`vendor/plugin-update-checker/Puc/v5p7/Vcs/VcsCheckerMethods.php` and
`Vcs/ReleaseAssetSupport.php`). The bootstrap in `altly.php` calls
`$altly_update_checker->getVcsApi()->enableReleaseAssets('/altly\.zip$/i')` so PUC installs
the **attached `altly.zip` release asset**, not GitHub's auto-generated "Source code (zip)"
archive — the auto-generated archive unpacks to a `altly-wp-plugin-<tag>/` directory, which
would break WordPress's expectation that the plugin lives in a directory matching its slug
(`altly/`).

PUC polls GitHub's Releases API twice daily and shows a normal WP Dashboard update notice
when a newer tagged release is available — same UX as a wordpress.org plugin.

## 2. Cutting a release

1. Bump the version in lockstep across three files:
   - `altly.php`'s `Version:` header
   - `package.json`'s `version`
   - `readme.txt`'s `Stable tag`
2. Commit the version bump.
3. Tag the commit `vX.Y.Z` and push the tag:
   ```
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
4. `.github/workflows/release.yml` triggers on `v*.*.*` tags, runs `yarn zip` (producing
   `altly.zip` with a single top-level `altly/` directory, vendored PUC included), and uses
   `softprops/action-gh-release@v2` to create a GitHub Release with `altly.zip` attached.
   That attached zip **is** the distribution — nothing is uploaded anywhere else.
5. Every site with PUC installed and polling this repo picks up the new version within its
   next check (twice daily by default).

## 3. Baseline, then auto — critical sequencing

1. **Baseline (`1.1.0`) must be installed MANUALLY on every site, no exceptions.** Sites
   running pre-PUC versions of `altly` have no update-checker code at all, so they cannot
   discover or install anything published as a GitHub Release — the very first PUC-carrying
   release has to be installed by hand (or however each site's current deploy process
   works). This install also normalizes the plugin's on-disk folder name to `altly/`, which
   matters because PUC derives its slug from `__FILE__`'s parent directory — a mismatched
   folder name would break the version comparison against the GitHub release tag.
2. **Only after 100% of sites are confirmed on `1.1.0`** do later releases become fully
   passive, following the cadence in Section 2 above.
3. **Cross-repo gate:** removing the legacy push-based delivery path on the API side was
   gated on that same 100%-migrated condition — see `altly-platform/DEPLOY.md` Stage 4.
   As of 2026-07-05 the API-side push removal has shipped to production (the API no
   longer POSTs to customer sites; delivery is pull-only). **This repo has no record of
   how — or whether — the 100% migration precondition was actually confirmed before that
   removal shipped**; see the flagged open item below. Any site still running a pre-1.1.0
   (pre-PUC, push-only) plugin build has no way to pull results and has silently stopped
   receiving alt text.

## 4. Verify a release

```
gh release view vX.Y.Z -R prolific-digital/altly-wp-plugin
```

Confirm `altly.zip` is listed as an attached asset. Then, on a site running PUC and pointed
at this repo, check Dashboard → Updates (or Plugins) — the new version should be offered
(allow up to one poll interval, or trigger a manual check if the install exposes one).

## 5. Security notes

- The repo is **public**, so no GitHub token/credential ships with the plugin. A private
  repo would require `->setAuthentication('<token>')` on the update checker — never embed
  a token in a shipped plugin; if the repo ever goes private, the auth token must be
  supplied per-site (e.g. via `wp-config.php`), not hardcoded in `altly.php`.
- The `Update URI: https://github.com/prolific-digital/altly-wp-plugin` plugin header
  points WordPress core at the real update source, so it skips its wordpress.org update
  check for this plugin — this matters because a same-named `altly` slug could otherwise
  appear on wordpress.org and hijack the update flow. Don't remove this header.
- Do not edit the vendored PUC files under `vendor/plugin-update-checker/`.

## Open items for Chris to confirm

- **OPS RISK — unresolved as of this doc pass:** neither this repo nor
  `altly-platform/DEPLOY.md` shows evidence of a tracking mechanism that confirmed "100%
  of sites migrated to the 1.1.0 pull+PUC baseline" before API-side push removal shipped
  to prod (2026-07-05). Push removal has already shipped. If that gate was not actually
  met, any site still running a pre-1.1.0 build receives neither push (removed) nor pull
  (its plugin predates PUC and the sync-results code) and is silently no longer getting
  alt text back. Confirm what tracking (if any) was done, and if none was, audit
  installed-plugin versions across customer sites now.
