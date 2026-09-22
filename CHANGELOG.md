# Changelog

All notable changes to this **fork** of the BlueSpice HDP Edition are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

For changes in the upstream BlueSpice HDP Edition, see the
[original repository](https://gitlab.opencode.de/bmbf/teamdigital/hdp).

---

## [Unreleased]

### Added

- **`update.sh`** — moves an existing, configured install to the latest
  release tag without losing `.env`, the database, the volumes or wiki
  content. Follows the newest `v*` tag on the remote by default (pre-release
  tags excluded, `-QoL*` tags included — see the script's own comments for
  why); `HDP_UPDATE_REF=main` opts into tracking the branch instead, and
  `HDP_UPDATE_REF=<tag>` pins one specific tag. Every destructive step
  (stopping the two live-traffic containers, resetting the tree, rebuilding
  images, running `docker/setup.sh`) is named and confirmed by a human first;
  on failure it prints the exact rollback commands rather than running them.
  Detects and offers a database backup whenever `update.php` is about to run,
  detects the case where a `-QoL*`-shaped release publishes no new images and
  offers to build from source instead of silently re-pulling stale ones, and
  reports (without writing) `.env.example` drift that has no safe default.
  Reachable from a checkout, after the one-line install, or piped from curl.

### Changed

- **`install.sh`** prints a one-line pointer to `./update.sh` in the
  "Your wiki is ready" block.
- **`README.md`** and **`README-DOCKER.md`** each gained an "Updating an
  existing install" section — there was previously no "how do I update" text
  anywhere in the repo.

---

## [5.1.9-QoL3] — 2026-08-19

### Fixed

- **A pipe-fed (`curl … | bash`) install survives model pre-download.** The
  pre-download `docker compose run` now passes `-T` and reads
  `< /dev/null`, so docker no longer drains the script pipe bash is
  reading — the services step, the start-now prompt and the exit message
  all execute instead of the installer dying silently after
  "Models cached…" (`8080147a3`); a bats regression test pins the shape.
- **First-boot setup tolerates slow MariaDB boots.** `docker/setup.sh`
  waits up to 600 s (was 120 s) for MariaDB — cold-disk InnoDB init on
  cloud boxes no longer kills setup while the compose healthcheck reads
  healthy (`c6c626923`); the poll loop is unchanged.
- **A port-less Server URL is caught at the prompt.** Entering a Server
  URL without a port while the host port is not 80 now warns that
  canonical redirects would point at port 80 and offers to append the
  port (decline re-prompts; an explicitly different port, e.g. a reverse
  proxy on 443, is never blocked) (`0f1e23c20`).

---

## [5.1.9-QoL2] — 2026-08-18

### Security

- **Anonymous chat requests are rejected before the LLM pipeline.** A
  cookie-less request to `/w/rest.php/bmbf/chat` now answers HTTP 403 with
  the `rest-read-denied` JSON body — the handler no longer opts out of the
  read gate and an explicit authority guard runs first (`5da99ec88`). A new
  integration test pins the behavior.
- **Anonymous clients no longer receive exception details.** Error responses
  now carry only the generic message — no exception message, backtrace or
  hostnames — while the private error and exception logs are kept; only the
  outward-facing responses are generic (`fa1f7c2e4` + `2ecd98950`).

### Fixed

- **`mediawiki-web` healthcheck works on any host port.** The probe now
  targets the in-container `http://localhost:8080/w/` instead of the
  host-interpolated `${MW_DOCKER_PORT}` — the wiki stays healthy on
  non-default ports even though apache always listens on 8080 inside the
  container (`70f58eca4`).
- **All five footer legal pages exist on a fresh install.** `Site:Impressum`,
  `Site:Haftungsausschluss` and `Site:Über` are seeded alongside the two
  BlueSpice defaults (`6f73b3535`); on existing installs exactly the three
  new pages are created under a versioned marker and existing pages or admin
  edits are never overwritten (`5c73d7074`).

### Docs

- **README states the real image pull: ~9 GB** (hdp-haystack 2.57 +
  hdp-opensearch 2.47 + hdp-chatbot-proxy 0.18 + mariadb 0.46 + the three
  Wikimedia images ≈ 3.08 GB), and raises the disk minimum to 20 GB to
  match (`f81e7fadd` + `ec367cb9f`).
- A new recipe explains why `git describe --tags` reports a `-QoL*` tag
  while `docker images` shows `v5.1.9`, and how to pin a version via
  `HDP_IMAGE_TAG` (`00c88cbf9`).
- docs/wiki sources synced to the shipped behavior (anon-chat 403, footer
  legal pages) with the wikitext mirrors regenerated in the same commit
  (`212c7307c`).
- **`-QoL*` prerelease tags publish no images** — by design, not omission:
  the release workflow's image-publish job skips them, and container images
  keep tracking the base `v5.1.9` release this bundle rides on
  (`7c887cac1`).

### Known Issues

- **The first avatar request on a fresh install can fail once.** Generating
  a user's avatar image may answer HTTP 500 on the very first request; it
  self-heals on the next request — reload the page. No action needed.
- **A `?search=` URL does not feed the search term into the search lookup
  server-side.** This does not reproduce for humans: a person typing into
  the search bar gets results (the term is carried client-side). The QA
  observation came from browser automation; upstream `SearchCenter` reads
  only `q`/`raw_term`, never `search`.
- QA Findings 7 and 8 are benign as reported: a first-boot PHP notice about
  the `bs_settings3` table (no user impact) and console-only deprecation
  warnings (deprecated `mediawiki.Uri` module, one unused preload).
- **composer-audit reports CVE-2026-65954** (`phpcsstandards/phpcsutils`) —
  a documented exception: the package is dev tooling only, never installed
  in the production container, and no lockfile-only pin exists because the
  fixed version conflicts with the pinned codesniffer toolchain. The fix
  rides the next toolchain upgrade.
- **The read-access lockout covers the chat handler only.** The four
  sibling ChatBot REST handlers (`/bmbf/session`, `/bmbf/history`,
  `/bmbf-export-chat`, `/bmbf-odf-export-chat`) remain open on non-default
  configurations — an intentional deferral to the next release.

---

## [5.1.9-QoL1] — 2026-08-12

### Changed
- **Upgraded MediaWiki 1.43.5 → 1.43.9 and BlueSpice 5.1.4 → 5.1.9.** Applied as
  upstream deltas rather than a tree replacement, so BlueSpice's own core
  modifications and this fork's patches survive. Covers MediaWiki core, its 37
  bundled extensions and skins (submodules, and so absent from the core diff),
  all 59 `bluespice/*` packages, and the 39 further distribution extensions
  whose constraints resolve forward alongside a BlueSpice bump.
- `bluespice/package-wikifarm` is 5.1.10 — upstream ships it ahead of the
  series, as it did at 5.1.5 against 5.1.4. Recorded as a `VERSIONS.yml`
  exception rather than pinned back.

### Security
- **28 of 34 known composer advisories cleared**, including both criticals.
  `phpoffice/phpspreadsheet` 1.30.1 → 1.30.6 (2 critical + 5 high; parses
  user-uploaded spreadsheets), `phpseclib/phpseclib` 3.0.48 → 3.0.56 (2 high;
  sits under the OIDC client), `universal-omega/dynamic-page-list3` → 3.6.4
  (exposed suppressed usernames).
  That leaves **6** carried out of the 34, but the baseline and `SECURITY.md`
  say **8**, and both are right: `CVE-2026-69245` and `CVE-2026-69246` (both
  `guzzlehttp/guzzle`) were published after the 34-item count was taken and are
  marked *new 2026-08* in `docker/ci/composer-audit-baseline.json`. 6 + 2 = 8.
  The baseline file is the count of record; these numbers date, it does not.
- **`mediawiki/maps` remains vulnerable** to CVE-2026-52854 (high, stored XSS
  via `display_map`). The fix is in 12.1.3; BlueSpice constrains the package to
  `11.0.*`, so no upgrade within the 5.1 series can clear it. Tracked in
  `docker/ci/composer-audit-baseline.json`.
- MediaWiki 1.43.9 adds `SVGCSSChecker`, `UnsafeLogFormatter` and
  `GetSecurityLogContextHook`; OATHAuth's base32 padding fix (T408225,
  T401393) is now upstream's rather than a distribution backport.

### Fixed
- Track 58 missing BlueSpiceDiscovery skin files (fonts, JS, PHP classes, tests) — fresh clones now produce a fully-rendered skin
- `publiccode.yml` license corrected from invalid `GPLv3.0` to SPDX-valid `GPL-3.0-only`; country codes uppercased to match schema
- Standardized Infisical secret names — removed special-case legacy keys; all secrets now use the `HDP_` prefix convention

### Added
- `CONTRIBUTING.md` — bilingual dev setup, PR workflow, code style guide
- `SECURITY.md` — responsible disclosure policy via GitHub issues with `security` label
- `CODE_OF_CONDUCT.md` — Contributor Covenant 2.1 adapted, bilingual
- `.dockerignore` — reduces Docker build context (excludes `.git/`, docs, dev artifacts)
- `.gitlab-ci.yml` — CI pipeline with shellcheck, YAML lint, Docker Compose validation

### Changed
- Expanded `app/extensions/.gitignore` — 888 → 21 untracked files after `setup.sh` (97.6% reduction in `git status` noise)
- Moved `CLAUDE.md` and `AGENTS.md` to `docs/dev/` (AI-agent operating docs no longer in repo root)

### Removed
- Internal investigation files from repo root (`FINDINGS-PLAN.md`, `INVESTIGATION-COOKIE-CONSENT.md`, `INVESTIGATION-SETUP-ARTIFACTS.md`)

---

## [0.2.0] — 2026-07-31

### Added
- Vendored Mermaid extension for MediaWiki 1.43 (117 files) — diagram support in wiki pages
- Mermaid sequence diagram parser fix for edge-case syntax
- Chatbot FAQ default page, auto-populated on first boot via bind mount
- Codewiki Help pages with first-boot population (idempotent guard in `setup.sh`)
- Markdown-to-wikitext conversion script (`scripts/convert-docs.sh`)
- Pre-rendered architecture diagram (PNG + SVG + Mermaid source) with static serving
- First-time user guidance on wiki main page template
- Help namespace added to RAG indexable namespaces
- `MW_SERVER` documentation for LAN access in `.env.example` and README
- Agent/developer operating documentation (now in `docs/dev/`)
- `HDP_PDF_PORT` templated in Docker Compose for configurable Haystack PDF port

### Fixed
- Hands-off setup: MariaDB `ONLY_FULL_GROUP_BY` mode, ChatBot config prefix, composer SSH→HTTPS rewrite, main page template
- MariaDB startup wait increased from 60s to 120s for slower environments
- SMW `.smw.json` error eliminated during first-boot install
- Silenced `urllib3` `InsecureRequestWarning` noise in ingestion script
- BlueSpiceDiscovery skin directories whitelisted in `.gitignore`
- Invalid `Permissions-Policy` features (`document-domain`, `web-share`) removed
- Broken OpenSearch verification snippet in docs; expanded troubleshooting section
- Typos in README fork introduction

### Changed
- Removed hard dependency on deepset cloud platform for ingestion — replaced with self-hosted Haystack pipeline
- README updated with fork description pointing to `README-DOCKER.md`

---

## [0.1.0] — 2026-07-29

### Added
- **Docker deployment infrastructure** — complete clone-and-go Docker Compose stack (7 services: MariaDB, MediaWiki PHP-FPM, Apache, job runner, OpenSearch, Haystack, chatbot-proxy)
- **Haystack RAG pipeline** — self-hosted retrieval-augmented generation with configurable LLM provider and three embedding modes (local CPU, remote API, HuggingFace Space)
- **`.env.example`** — fully commented environment template with all configuration variables
- **`README-DOCKER.md`** — 180-line Docker setup guide covering clone, configure, build, ingest, troubleshoot
- **`setup.sh`** — idempotent first-boot setup script (`set -euo pipefail`) handling composer install, MediaWiki install, BlueSpice extension activation, and database migration
- **Infisical secret management** — loader scripts for both host-side (`hdp.sh`) and in-container (`infisical-loader.sh`) secret resolution
- **Chatbot proxy** — bridges BlueSpice ChatBot extension's Deepset-API format to Haystack's API
- **Regenerated technical wiki** covering Docker + RAG chatbot infrastructure
- Architecture diagram (Mermaid source + rendered PNG/SVG)
