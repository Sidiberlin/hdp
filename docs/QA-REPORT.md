# HDP Publication Readiness — QA Report

**Date:** 2026-07-29
**Scope:** BlueSpice HDP (MediaWiki/BlueSpice fork + Haystack RAG chatbot), `/root/hdp`
**Test method:** Fresh-clone, isolated Docker stack, simulating a first-time user

---

## 1. Executive Summary

**Recommendation: GO for publication.**

Six critical/blocking bugs and one minor timing issue were found during a full
fresh-clone simulation of the "clone → `docker compose up` → `setup.sh` →
ingest documents → use chatbot" user journey. All seven were fixed and
verified end-to-end, including a live browser-driven chatbot query that
returned a correct, cited answer sourced from ingested wiki content.

No further blocking issues remain. A handful of known limitations are
documented below (Section 5); none prevent a successful out-of-the-box setup
or a working RAG chatbot experience.

---

## 2. Test Methodology

To validate that a real external user could clone the repository and get a
working instance without any of the tacit knowledge accumulated during
development, testing was performed in an **isolated clone**, not the
development checkout:

- **Test clone:** `/root/hdp-freshtest` (separate directory, separate git
  clone of `/root/hdp`)
- **Test stack:** separate Docker Compose project name `hdptest`, running on
  host port `8090` (vs. the production stack's `8080`), so the two stacks
  could run side-by-side without interference
- **Test credentials:** newly generated, isolated from production (admin
  password `d3e98c2193345acc9d63e83f5357e9d9`, OpenSearch password
  `Test-fdab1245-26!` — both test-only, stored in `/root/hdp-freshtest/.env`)
- **No shortcuts:** no manually copied config, no pre-seeded database, no
  hand-fixed files — only what `git clone` + the committed `setup.sh` produce
- **Verification style:** real browser automation (login flow, main page
  render, chatbot UI interaction with SSE streaming) rather than only API/curl
  checks, to catch UI-layer regressions that API tests would miss

This methodology directly exposed every bug in Section 3 — each was invisible
in the development checkout (which had accumulated live, uncommitted fixes)
and only surfaced on a genuinely fresh clone.

---

## 3. Bugs Found and Fixed

| # | Bug | Severity | Fix Commit |
|---|-----|----------|------------|
| 1 | Docker infra never committed to git | Critical | `5d8b1e2e0` |
| 2 | `app/skins/.gitignore` excluded `HookRunner.php` | Critical | `13534c77a` |
| 3 | `composer install` fails on SSH URLs | Critical | `13534c77a` |
| 4 | MariaDB `ONLY_FULL_GROUP_BY` breaks logged-in pages | Critical | `13534c77a` |
| 5 | ChatBot config used wrong global variable prefix | Critical | `13534c77a` |
| 6 | Main page showed generic upstream boilerplate | UX | `13534c77a` |
| 7 | MariaDB startup wait too short (60s → 120s) | Minor | `a0e7adbf1` |

### Bug 1 — Docker infra never committed to git
**Symptom:** A fresh `git clone` produced a repository with zero deployment
files — no `docker-compose.yml`, no `docker/`, no docs.
**Fix:** Committed all 31 deployment files (`docker-compose.yml`, `docker/`,
`docs/`, `.env.example`, `.gitignore`, `README-DOCKER.md`) in `5d8b1e2e0`.
Verified via a subsequent fresh re-clone.

### Bug 2 — `app/skins/.gitignore` blanket-excluded the skins tree
**File:** `app/skins/Vector/includes/Hooks/HookRunner.php`
**Symptom:** A required Vector skin hook-registration fix (for the
BlueSpiceDiscovery ChatBot dock) had been applied live in an earlier session
but silently dropped by `git add -A`, because `app/skins/.gitignore` contains
a blanket `/*` exclusion.
**Fix:** Force-added the file (`git add -f`) and committed in `13534c77a`.

### Bug 3 — `composer install` fails on fresh clone (SSH URLs)
**File:** `docker/setup.sh`
**Symptom:** `composer.lock` pins several packages to SSH-form VCS sources
(`git@github.com:...`). A fresh container has no SSH client or keys, so
Composer throws an internal `TypeError` in `Git::runCommand()`.
**Fix:** `setup.sh` now rewrites SSH URLs to HTTPS
(`sed -i 's#git@github\.com:#https://github.com/#g' composer.lock`) and sets
`composer config --global github-protocols https` before running
`composer install`.

### Bug 4 — MariaDB `ONLY_FULL_GROUP_BY` breaks every logged-in page load
**File:** `app/settings.d/050-Fixes.php`
**Symptom:** Error 1055 ("isn't in GROUP BY") from BlueSpice UserSidebar /
PagesVisited queries on every page load while logged in.
**Root cause:** MediaWiki's `$wgSQLMode` (set by the Wikimedia dev image's
`DevelopmentSettings.php`) re-adds `ONLY_FULL_GROUP_BY` on every DB
connection, overriding the server-level `sql-mode.cnf` fix, which was
addressing the wrong layer.
**Fix:** `050-Fixes.php` (loaded after `PlatformSettings.php`) now strips
`ONLY_FULL_GROUP_BY` from `$wgSQLMode` regardless of its position in the
mode string.

### Bug 5 — ChatBot config used the wrong global variable prefix
**File:** `app/settings.d/100-ChatBot.php`
**Symptom:** Every chat message failed with `"The scheme '' is not
supported."` — no request ever reached `chatbot-proxy`.
**Root cause:** The ChatBot extension declares no `config_prefix` in its
`extension.json`, so MediaWiki's default `GlobalVarConfig` reads
`$wg`-prefixed globals. The config used unprefixed names (e.g.
`$GLOBALS['BmbfDeepsetApiChatUrl']`) instead of
`$GLOBALS['wgBmbfDeepsetApiChatUrl']` — a silent no-op. This was the
literal bug described in the original QA handoff and had never actually
been fixed.
**Fix:** All 7 config lines now use the `wg`-prefixed names. Verified:
`$config->get('BmbfDeepsetApiChatUrl')` correctly returns
`http://chatbot-proxy:8080`.

### Bug 6 — Main page shipped generic upstream boilerplate
**Files:** `docker/mediawiki/hauptseite.wiki` (new), `docker/setup.sh`,
`docker-compose.yml`
**Symptom:** Fresh installs showed the stock "Willkommen in BlueSpice pro"
page — no mention of the chatbot, no content overview, no usage guidance.
**Fix:** Added a custom main page with chatbot usage instructions, a
namespace/content-structure overview, an auto-updating "recently edited
pages" list (via DynamicPageList3, degrading gracefully to an empty-state
message on a content-free wiki), and a link to category browsing. Wired into
`setup.sh` as a one-time first-install step guarded by a
`cache/.hauptseite-populated` marker so later admin edits are never
overwritten.

### Bug 7 — MariaDB startup wait too short
**File:** `docker/setup.sh`
**Symptom:** In a fully unattended fresh-clone run, `setup.sh` reported
`ERROR: MariaDB not reachable after 60s` even though the container's own
health check had already reported "healthy." The container's healthy state
and the point at which the `bluespice` application user/database could
actually be reached did not coincide reliably in slower environments.
**Fix:** Increased `max_wait` from 60s to 120s (commit `a0e7adbf1`). Root
cause was confirmed to be a startup-ordering/timing gap, not a configuration
defect — a subsequent run with the extended wait completed successfully with
no other changes.

---

## 4. Verification Results

All verification was performed on the isolated `hdptest` stack described in
Section 2.

| Check | Result |
|---|---|
| Fresh-clone `docker compose up` | All containers reach a healthy state |
| `setup.sh` Step 1 — Composer install | Completes (SSH→HTTPS rewrite applied) |
| `setup.sh` Step 2 — MediaWiki install | Completes |
| `setup.sh` Step 3 — `update.php` | Completes |
| `setup.sh` Step 4 — Main page population | Custom main page rendered |
| Sample content creation | 10 wiki pages created via `edit.php` |
| Ingestion | 11 pages → 16 documents indexed into OpenSearch (`hdp_wiki` index) |
| Chatbot query | `"Welche Cloud Modelle gibt es?"` → correct answer **"IaaS, PaaS, SaaS"** with citation `[3]`, verified via live browser session with SSE streaming response |

The chatbot verification exercised the full pipeline end-to-end: Wiki content
→ ingestion script → local embedding model → OpenSearch → `chatbot-proxy` →
`gpt-4o` → streamed response with source citations rendered in the UI.

---

## 5. Known Limitations

1. **~~Port 1417 hardcoded in `docker-compose.yml`~~ — fixed.** Previously
   the Haystack RAG query API port was hardcoded (`"1417:1417"`), unlike
   `MW_DOCKER_PORT` and `HAYHOOKS_PORT`, which prevented running two stacks
   side-by-side without editing the file. This has been templated as
   `HDP_PDF_PORT` (default `1417`) in this same change — see
   `docker-compose.yml`, `.env.example`, and `README-DOCKER.md`.
2. **Anonymous users see "Anmeldung erforderlich" (login required).** Read
   access requires login by default — a legitimate BlueSpice privacy-policy
   default, not a bug, but may be friction for a "quick look" demo. Documented
   here for awareness; enable anonymous read via
   `$wgGroupPermissions['*']['read']` if desired.
3. **`ob_flush()` PHP notices in the chat SSE stream.** `ChatApi.php` line 80
   calls `ob_flush()`, which occasionally has no active output buffer under
   PHP-FPM. Harmless deprecation-level notices that pollute error logs. Low
   priority.
4. **~915 untracked dev/test files under `app/`.** `.eslintrc.json`,
   `Gruntfile.js`, `tests/` directories from upstream BlueSpice extension
   checkouts. Pre-existing noise from upstream, not introduced by this work.
   Out of scope for this pass but noted for future repo hygiene.
5. **`composer.lock` still contains SSH-form VCS URLs.** `setup.sh` rewrites
   them to HTTPS at install time, which is sufficient for a working install,
   but a cleaner long-term fix would be committing a pre-rewritten
   `composer.lock`. Deferred because regenerating the lockfile risks merge
   conflicts with upstream BlueSpice.
6. **MediaWiki deprecation warnings** (`mediawiki.Uri` module,
   `BlueSpicePrivacyCookieConsentProviderGetGroups` hook). Upstream warnings,
   not actionable from this repo.

None of the above block a successful setup or a working chatbot experience.

---

## 6. Publication Checklist

### Ready
- [x] Docker deployment infrastructure committed to git
- [x] Fresh-clone → `docker compose up` → all containers healthy
- [x] `setup.sh` completes unattended (Composer, MediaWiki install,
      `update.php`, main page)
- [x] Wiki login and page rendering verified
- [x] Sample content ingestion pipeline verified (Wiki → OpenSearch)
- [x] Chatbot end-to-end verified with real LLM query and citations
- [x] Custom, chatbot-aware main page (vs. generic upstream boilerplate)
- [x] Port configuration fully templated via `.env` (no hardcoded host ports)
- [x] GPLv3 licensing intact (fork of GPLv3 BlueSpice/MediaWiki)

### Optional (not blocking)
- [ ] Enable anonymous read access for demo purposes (policy decision, not a
      defect)
- [ ] Silence `ob_flush()` notices in `ChatApi.php`
- [ ] Clean up untracked upstream dev/test files under `app/`
- [ ] Regenerate `composer.lock` with HTTPS-only VCS URLs
