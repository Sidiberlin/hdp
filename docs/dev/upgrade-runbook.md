# Upgrade runbook

How to take a MediaWiki or BlueSpice release into this fork without losing a
patch, and what to do differently when the reason is an active CVE.

Read this before touching anything. The first step is deliberately not the
version bump.

---

## Why this is not a normal dependency bump

Four facts, each of which changes the procedure:

1. **Upgrading is re-vendoring, not `composer update`.** The whole MediaWiki +
   BlueSpice tree is committed — 53,938 tracked files under `app/`. A version
   bump is a large tree replacement with **21 patches re-applied on top**, not
   a lockfile edit.
2. **Three patches target MediaWiki core** — `UserGroupManager.php`,
   `MultiHttpClient.php`, `RequestFromGlobals.php` — which is precisely what a
   core security release touches. `mw-usergroupmanager` is a `?? []` added to
   three cache reads; any upstream refactor of that cache silently invalidates
   it.
3. **Two patches are on the authentication path** — `pluggableauth-service`
   and `oidc-client`. The second lives under `app/vendor/`, which is gitignored
   *and* recreated by composer on every install, so it is the patch most likely
   to be clobbered without trace. `tests/integration/test_auth_path.py` is what
   catches that.
4. **Two packages can never be upgraded at all.** `hallowelt/chatbot` and
   `mediawiki/page-header` resolve from a private GitLab this project cannot
   reach. See Track C in `SECURITY.md`.

The upside of the same vendoring model: **rollback is a `git revert` of one
merge commit.** No package resolution, no partial state, no database
downgrade to invent. That is what makes a large risky bump survivable here.

---

## Before you start

```bash
git status                       # must be clean. You will not be able to tell
                                 # your changes from upstream's otherwise.
./scripts/check.sh               # main must be green
scripts/ci/release-watch.sh      # what is actually available upstream
```

Read `docker/ci/composer-audit-baseline.json`, specifically the entries marked
**ACTION REQUIRED** — as of DEPS-02 (2026-09-23) there are two,
`mediawiki/maps` and `mediawiki/semantic-media-wiki`. They are known-vulnerable
versions that only a re-vendor can fix, and they are usually the reason to be
doing this at all.

(It was four before the 1.43.9 / 5.1.9 upgrade, which closed three:
`phpoffice/phpspreadsheet`, `phpseclib/phpseclib` and
`universal-omega/dynamic-page-list3`. That left one, `mediawiki/maps`, until
DEPS-02 added `mediawiki/semantic-media-wiki` — SMW 6.0.1's fix floor is 7.3.0,
blocked by the same class of distribution pin as Maps, see the baseline
entry's `why`. The baseline file is the count of record —
`tests/unit/test_audit_baseline.py::test_the_fixable_ones_stay_marked` asserts
exactly `{"mediawiki/maps", "mediawiki/semantic-media-wiki"}` — so read it
rather than this sentence.)

---

## The normal upgrade

### 1. BASELINE — before anything changes

```bash
scripts/verify-patches.sh --upgrade-report | tee before.txt
```

Against the current tree every patch should read **BLUE** (already applied) and
nothing should read AMBER or RED. If something already needs work, fix that
first: you cannot tell an upgrade regression from a pre-existing one otherwise.

### 2. BRANCH

```bash
git switch -c upgrade/mw-1.43.9
```

### 3. BUMP — one commit, nothing else in it

Re-vendor the upstream tree, or bump `app/composer.lock`. **No other changes in
this commit**, so the upstream diff is reviewable in isolation and a bad bump is
revertible on its own.

### 4. TRIAGE — the 21-row table

```bash
scripts/verify-patches.sh --upgrade-report
```

| State | What it means | What to do |
|---|---|---|
| **GREEN** | applies cleanly to the new upstream | nothing |
| **BLUE** | already present — upstream adopted our fix | delete the patch, its sidecar and its `.diff` |
| **AMBER** | target is there, patch does not apply — upstream moved the code | re-derive the `.diff`, update `anchor`/`marker`/`upstream_version` |
| **RED** | target gone, or the anti-pattern matched | blocker; decide `stale: true`, relocate the patch, or stop |
| **N/A** | that component is not in the tree you pointed at | not a pass — re-run against the full candidate tree |

**One commit per patch.** A bad re-derivation is then revertible alone, which
matters because re-deriving a patch by hand against moved code is the step most
likely to be wrong.

Take the three core patches and the two auth patches first. They are the ones
whose failure is invisible.

### 5. VALIDATE — the whole pyramid, including T4

```bash
./scripts/check.sh                # T0, TF, unit, bats, versions, composer-audit
scripts/ci/t3-integration.sh      # the wiki boots and serves authenticated traffic
scripts/ci/t5-migration.sh        # update.php against the PREVIOUS release's data
scripts/ci/t4-smoke.sh            # all seven containers, search and chatbot
```

T4 is nightly-only in normal life. **Not here**: the blast radius of an upstream
bump is the whole stack, and one extra T4 run is trivial against a broken
security release.

`t5-migration.sh` is the one that matters most and the one easiest to skip. It
is the only thing that runs the new code against the *old* release's data —
which is what every real operator will do, and what T3 does not test.

Note the ordering constraint: the fixture in `docker/ci/fixtures/` must still be
from the **old** release when you run this. Regenerate it *after* the upgrade
merges, not before (see step 7).

### 6. QA — by hand, from a genuinely fresh clone

`docs/QA-REPORT.md` §2 found six of seven bugs precisely because it used a
fresh clone rather than the dev checkout. An upstream bump is a fresh-clone
shaped risk; automation does not replace this for a major bump.

### 7. DECLARE

```bash
# VERSIONS.yml: mw_core, bluespice, and any exceptions
python3 scripts/lib/versions.py emit-extensions   # regenerate the inventory
./scripts/check.sh --only versions                # must be green
```

Also update `publiccode.yml` (`softwareVersion`), `CHANGELOG.md`, and any
version string in `README.md`. The `versions` gate enforces that you did not
miss one — that is its whole purpose.

Then, from a working stack built on the **new** release:

```bash
scripts/ci/make-db-fixture.sh     # the next upgrade's "previous release"
scripts/ci/composer-audit.sh --update-baseline   # and write the reasons
```

Both are deliberately manual. A fixture or baseline that regenerates itself
records whatever the code does today, which is the opposite of what either is
for.

### 8. MERGE + TAG

Tagging re-runs T4 on the merged result.

---

## The security fast path

For an **actively exploited** vulnerability, or a MediaWiki security release
you cannot sit on:

* Steps 1–5, including T4 and `t5-migration.sh`.
* **Skip step 6** (the manual fresh-clone QA pass).
* **Time-box step 4.** An AMBER patch may be *temporarily dropped* to ship the
  fix — but record the decision in the PR, with the patch id and why dropping
  it is acceptable.

**Three patches are never droppable on a fast path:**

| Patch | Why |
|---|---|
| `mw-usergroupmanager` | guards a null group-cache read; dropping it is a PHP 8 fatal on a permission check |
| `pluggableauth-service` | authentication form path |
| `oidc-client` | authentication library — the audience check itself |

Dropping any of those trades one vulnerability for another. If the upgrade
cannot proceed without dropping one, the upgrade is blocked, not the patch.

---

## Rollback

```bash
git revert -m 1 <merge-commit>
docker compose down && docker compose up -d --build
docker compose exec mediawiki bash /setup.sh
```

Because the whole tree is in git, this is complete: no package resolution, no
half-migrated vendor directory.

**The database is the exception.** `update.php` migrates schema forward and
MediaWiki has no downgrade path. If the upgrade ran `update.php` against
production data, reverting the code needs a database restore from the backup
taken before the upgrade — take one, every time, before step 5. This is the
single thing rollback does not give you for free.

---

## Deciding whether to upgrade at all

| Situation | Do |
|---|---|
| MediaWiki patch release on our branch (1.43.x) | **Take it.** This is where core security fixes land, and the patches usually survive — the three core patches were all GREEN against 1.43.9. |
| MediaWiki minor/major (1.44+) | **Plan it.** 1.43 is LTS; the trigger is the end of that support window, not the existence of 1.46. |
| BlueSpice patch release on our series (5.1.x) | **Take it**, with the full triage — it moves the extension code the 17 inherited patches are written against. |
| BlueSpice series bump (5.2+) | **Plan it.** Expect several AMBER rows. Not a fast path. |
| A composer advisory with no upstream release carrying the fix | **Wait, and record it** in `docker/ci/composer-audit-baseline.json` with a reason. Editing `app/composer.lock` by hand desynchronises it from the vendored tree, and the `versions` gate will say so. |
| An advisory in a Track C frozen package | **You cannot upgrade it.** See `SECURITY.md`; the options are to negotiate access, replace the package, or accept the risk in writing. |

---

## What this process does not solve

The two frozen packages drift indefinitely and no automation will ever flag
them. `VERSIONS.yml` records a `last_reviewed` date and the version-consistency
gate warns when it ages past six months — that is a reminder, not a fix. The
honest options are in `SECURITY.md`.
