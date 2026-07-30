# Publication-Readiness Fixes — Plan & Summary

Applied against a running stack (containers untouched, no restart, no commit).

## Changes applied

### 1. `README.md` — editorial pass on the fork intro
Fixed: "for" → "fork", "docker- compose.yml" → "docker-compose.yml",
`AGENTS.MD` → `AGENTS.md`, `README-DOCKER.MD` → `README-DOCKER.md`,
`example.env` → `.env.example`, `codewiki` → `codewiki-generated`.
Left the German content and mixed-language tone untouched.

### 2. `docker/setup.sh` — no more scary `.smw.json` ERROR
Root cause: SMW's setup writes `.smw.json` into
`extensions/BlueSpiceFoundation/data/` during `update.php`. If the dir
isn't writable by www-data at that moment, MW logs an `ERROR: … not
writable` line. The old script created/chowned the dir *after* step
[3/4], then re-ran update.php in step [4/4] to mask it.

Fix: hoisted the `mkdir -p`, `touch .smw.json`, `chown -R www-data`
block to run *before* step [3/4]. Removed the redundant second
`update.php` invocation. Step [4/4] now just re-asserts ownership in
case update.php created new files as root. Result: single clean
update.php run, no phantom ERROR.

### 3. `CLAUDE.md` + `README-DOCKER.md` — verification snippet
Replaced the broken `${HDP_OPENSEARCH_PASSWORD}` (host-shell expansion,
empty → 401; also `!` in typical passwords triggers bash history
expansion) with a single-quoted `bash -c` running inside the
container, which uses the container's own `OPENSEARCH_INITIAL_ADMIN_PASSWORD`
env var (verified in `docker-compose.yml`).

### 4. `README-DOCKER.md` — realistic ingestion timing
"roughly 1-3 minutes per page" → "anywhere from a few seconds to a few
minutes per page depending on page size and host CPU". Matches the
observed ~6s for 19 pages / 51 chunks.

### 5. `app/skins/.gitignore` — whitelist shipped skins
Kept the upstream `/*` catch-all (there's a reason: MediaWiki lets
users drop in arbitrary skins without them being tracked). Added
explicit whitelist entries for the six vendored skins:
`BlueSpiceDiscovery`, `hdp`, `MinervaNeue`, `MonoBook`, `Timeless`,
`Vector`. Also updated the corresponding CLAUDE.md "Known Gotchas"
entry.

### 6. `docker/haystack/ingest_hdp_wiki.py` — narrow warning filter
Added a targeted `warnings.filterwarnings("ignore",
category=InsecureRequestWarning)` block near the imports, wrapped in
`try/except` so it silently no-ops if urllib3 layout changes. HF-Hub
"unauthenticated requests" warnings intentionally left alone — they
originate from `huggingface_hub` and reflect real config state (no
token). Suppressing them broadly risks hiding auth/quota surprises.

### 7. Spezialseiten alias conflict — documented, not code-fixed
Root cause traced: `app/extensions/EnhancedStandardUIs/languages/EnhancedSpecialPages.i18n.alias.php`
line 22 registers `'Spezialseiten'` as a German alias for
`Special:EnhancedSpecialPages`, colliding with MediaWiki core's own
`Special:Spezialseiten`. MediaWiki logs a Notice at load time and
keeps the core mapping — functionally harmless. Editing the vendored
upstream file would create a maintenance burden vs. upstream re-syncs.
Added a troubleshooting entry to `README-DOCKER.md` explaining the
Notice is expected and safe to ignore.

## Not changed / follow-ups

- **HF-Hub warnings in ingestion** — left visible on purpose (see §6).
  If they get annoying, wrap only the specific `UserWarning` subclass
  the hub emits with a comment noting what it hides.
- **Vendored extension patch for `Spezialseiten`** — a one-line fix
  (drop line 22) is trivial but couples this fork more tightly to the
  vendored extension tree. Reasonable future work if BlueSpice
  upstreams a fix or this fork adopts a patches/ directory pattern.
- **Not committed** — as requested; changes left as unstaged diff for
  review.

## Files touched

- `README.md`
- `README-DOCKER.md`
- `CLAUDE.md`
- `docker/setup.sh`
- `docker/haystack/ingest_hdp_wiki.py`
- `app/skins/.gitignore`
- `FINDINGS-PLAN.md` (this file)
