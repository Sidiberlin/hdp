# Changelog

All notable changes to this **fork** of the BlueSpice HDP Edition are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

For changes in the upstream BlueSpice HDP Edition, see the
[original repository](https://gitlab.opencode.de/bmbf/teamdigital/hdp).

---

## [Unreleased]

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
- Standardized Infisical secret names — removed `GLM_API_KEY` / `VOICE_TOOLS_OPENAI_KEY` special cases; all secrets now use the `HDP_` prefix convention

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
