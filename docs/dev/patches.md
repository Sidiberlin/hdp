# Patches

HDP ships modifications to vendored upstream code. This file is the inventory:
what they are, how each one is applied, how each one fails, and which ones are
dead.

The machine-readable manifest lives in `docker/patches/` — one YAML sidecar per
patch — and three tools read it:

| Tool | Does | Mutates? |
|---|---|---|
| `scripts/verify-patches.sh` | is every patch still in the tree? | never |
| `scripts/apply-patches.sh` | put back the ones composer clobbered | yes |
| `scripts/check.sh --patches` | runs the verifier as part of the local gate | never |

This page is the prose; the manifest is the source of truth.

---

## Inventory: 19 targets, 18 live

| Class | Count | Applied by | Fails by |
|---|---|---|---|
| **A** — composer-clobbered | 2 | `scripts/apply-patches.sh`, called by `docker/setup.sh` | `composer install` reinstalls the package as a dist zipball over the patch |
| **B** — gitignore-swallowed | 0 | — | *(retired, see below)* |
| **C** — inherited BlueSpice diffs | 17 | `app/_bluespice/pre-autoload-dump.d/99-apply_patches.sh` | the script prints `FAILED!` and continues, with no exit code |

Of the 17 Class-C patches, **one is permanently stale** (`PF_UploadForm.php`,
below), leaving **18 patches that can actually apply**.

### Getting the count right

The CI strategy document says "19 patches" and breaks it down as 2 + 1 + 16.
That arithmetic was wrong in two places, and they cancelled out:

- Class C is **17** `.diff` files, not 16. Count them:
  `find app/_bluespice/patches -name '*.diff' | wc -l`. The same document's
  prose says 17 elsewhere; only the table says 16.
- Class B is now **0**, not 1.

So the total was briefly **20** (2 + 1 + 17), and is now **19** again (2 + 0 +
17) — the same number as the original estimate, reached a different way. The
manifest holds **19 sidecars**, and `verify-patches.sh` reports 18 applicable
plus 1 stale.

---

## Class A — composer-clobbered (2)

Both live in `app/extensions/BlueSpiceExtendedSearch/`, both are reinstalled
from a dist zipball by `composer install`, and both are re-applied afterwards by
`scripts/apply-patches.sh --class A`, which `docker/setup.sh` calls.

| id | Target | Marker |
|---|---|---|
| `es-ssl` | `extensions/BlueSpiceExtendedSearch/src/Backend.php` | `// HDP runs OpenSearch with its default self-signed demo certs` |
| `es-searchcnt` | `extensions/BlueSpiceExtendedSearch/resources/ext.blueSpiceExtendedSearch.SearchCenter.js` | `// Upstream 5.1.4 fires the 'getResults' hook below with` |

The markers are the comments the patch inserts, not `setSSLVerification` or
`const $searchCnt`. Matching the code itself would let verify pass if upstream
one day added its own call while our patch was gone.

Note the real path of the second one. The strategy document refers to it as
`SearchCenter.js`, which matches nothing — the file is
`resources/ext.blueSpiceExtendedSearch.SearchCenter.js`, a flat filename, not
`resources/<module-dir>/SearchCenter.js`. A `find -name 'SearchCenter.js'`
returns nothing and will make you think the patch is missing. Use the path
above when writing the manifest.

These were two inline `sed -i` blocks in `setup.sh` until the applier landed.
`sed -i` exits 0 when its address matches nothing, so an upstream reindent
turned the patch into a silent no-op. `apply-patches.sh` uses
`patch --ignore-whitespace --fuzz 3`, which tolerates that drift, and re-checks
the marker after applying so a patch that reports success without landing is
still caught.

## Class B — retired

`app/skins/Vector/includes/Hooks/HookRunner.php` used to be an HDP-authored
stub, recorded as Class B in the strategy document and as Bug 2 in
`docs/QA-REPORT.md`.

It is no longer a patch. The premise was that upstream Vector does not ship
the file; upstream `REL1_43` does ship it, together with the
`VectorSearchResourceLoaderConfigHook` interface it implements. The file was
absent because the copy of Vector vendored into this repo was incomplete — 59
files under `includes/` had never been committed — not because of anything
upstream did or `.gitignore` swallowed.

Those 59 files have been restored from upstream `REL1_43`, and
`app/skins/Vector/includes/` is now byte-identical to it: 89 files, 0
divergent, verified against the blob ids in the `REL1_43` tree. There is
nothing left to patch, so this class is empty.

The `.gitignore` risk it was filed under is still real and still worth a gate:
`app/skins/.gitignore` excludes `/*` and re-includes six skins by name. A new
skin directory without a matching `!/Name/` line is invisible to `git add`.
That is what the T0 `check-ignore` job is for — it just no longer has a patch
attached to it.

## Class C — inherited BlueSpice diffs (17)

`app/_bluespice/patches/**/*.diff`, applied by
`app/_bluespice/pre-autoload-dump.d/99-apply_patches.sh`, which is fired by
`composer dump-autoload` through `app/composer.local.json`'s
`pre-autoload-dump` hook.

Not authored here. We ship them, so we verify them; we do not maintain them.

Three target MediaWiki **core**, which is what makes an upstream security
release the single most likely event to silently drop a patch in this repo:

- `includes/user/UserGroupManager.php`
- `includes/libs/http/MultiHttpClient.php`
- `includes/Rest/RequestFromGlobals.php`

One targets an auth library and is security-critical by definition:

- `vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php`

That one sits under `app/vendor/`, which is wiped by `rm -rf vendor/` on every
setup run and re-created by composer, so it is re-applied every time rather
than persisted. Verified present after a full clean-box install.

The remaining 13 target bundled extensions (MultimediaViewer ×3,
SemanticResultFormats ×3, TextExtracts ×2, PageForms, PdfHandler,
PluggableAuth, SemanticMediaWiki, VisualEditor).

---

## Permanently stale: `PF_UploadForm.php`

```
app/_bluespice/patches/extensions/PageForms/specials/PF_UploadForm.php.diff
```

**This patch can never apply again. Do not try to fix it.**

Its target, `app/extensions/PageForms/specials/PF_UploadForm.php`, does not
exist. PageForms 6.0.4 — the version vendored here — no longer ships that
file; `specials/` contains eleven `PF_*.php` files and that is not one of
them. The patch was written against an older PageForms.

Every `composer dump-autoload` therefore prints:

```
Patching: extensions/PageForms/specials/PF_UploadForm.php ==> FAILED!
```

and continues. That line is expected and is not a regression.

**Why it is a warning, not a failure.** `docker/setup.sh` grades a failed
patch by whether its target still exists:

- target present → the anchor moved, a live file is now unpatched → **failure**, `setup.sh` exits 1
- target absent → upstream deleted the file, nothing can be done → **warning**, exit code unaffected

Without that split this one patch would make `setup.sh` exit non-zero on every
run forever, and an exit code that is always non-zero is one nobody reads.

**What to do about it.** Nothing, for now. The options are to delete the
`.diff` (diverging from the inherited `_bluespice` tree), or to keep it and
carry the warning (current choice, since the tree is upstream's and we do not
maintain it). Revisit if BlueSpice reissues the patch set. The sidecar carries
`stale: true`, so the condition is declared rather than inferred from a missing
file — `verify-patches.sh --stale` lists it, and neither the verifier nor the
applier treats it as a failure.

---

## How each class fails silently

Worth stating plainly, because all three failure modes are silent by default
and each was observed on a clean box:

| Mechanism | Silent because |
|---|---|
| `sed -i` in `setup.sh` | exits 0 when the address matches nothing |
| `99-apply_patches.sh` | prints `FAILED!`, has no `set -e`, returns no exit code |
| `composer dump-autoload` | ignores the hook scripts' exit statuses entirely |

`docker/setup.sh` detects all three and reports them in a summary before
exiting non-zero. That is a backstop, not the gate — the gate is
`scripts/verify-patches.sh`, which inspects the resulting tree rather than
scraping logs.

The first row is also no longer true of this repository: the two Class-A
patches are applied by `scripts/apply-patches.sh` with
`patch --ignore-whitespace --fuzz 3`, not by `sed -i`. The applier re-checks
each patch's marker afterwards, so a patch that reports success without landing
is caught rather than assumed.

## Working with patches

```bash
scripts/verify-patches.sh                 # is everything still applied?
scripts/verify-patches.sh --list          # the inventory
scripts/verify-patches.sh --explain <id>  # what this patch is for
scripts/verify-patches.sh --stale         # patches that can never apply
scripts/verify-patches.sh --static        # schema + paths only, no patch(1)
scripts/verify-patches.sh --upgrade-report --tree DIR   # will they survive DIR?

scripts/apply-patches.sh                  # put back whatever is missing
scripts/apply-patches.sh --id es-ssl      # just one
scripts/apply-patches.sh --class A        # only the composer-clobbered ones
scripts/apply-patches.sh --dry-run        # say what would change
```

Exit codes: `0` all present, `1` a patch is missing, `2` the manifest itself is
malformed. The last two are deliberately distinct — a typo in a sidecar must not
read as a patch regression.

Run the verifier **after** composer, which is when patches disappear. On a fresh
clone before `setup.sh` has run, `app/vendor/` does not exist yet, so the
`oidc-client` target is legitimately absent; `--static` reports it as
not-applicable rather than missing.

### The upgrade report

`--upgrade-report` answers a different question from everything above, and
mixing the two up is the easiest way to misread it:

| | question | a patch that applies cleanly means |
|---|---|---|
| `verify-patches.sh` | is this patch in the tree right now? | it is **missing** |
| `--upgrade-report` | will this patch still work against *that* tree? | it is **GREEN** |

Same probe, opposite reading, because the trees are different. Point `--tree`
at a candidate upstream — an extracted `mediawiki-1.43.9`, or a branch where
the re-vendor commit has landed and nothing has been re-applied yet.

| State | Meaning | Action |
|---|---|---|
| **GREEN** | applies cleanly | none |
| **BLUE** | already in the file: applied here, or upstream adopted it | on a fresh upstream tree, delete the patch, the sidecar and the `.diff` |
| **AMBER** | target is there, patch does not apply — upstream moved the code | re-derive by hand; this is the regression signal |
| **RED** | target gone, or the anti-pattern matched | blocker — do not ship |
| **N/A** | the whole component is absent from that tree | nothing was evaluated; not a pass |

Exit is `0` only when every patch was evaluated and none needs work; AMBER, RED
**and N/A** all exit `1`, because a report that skipped eight patches is not a
clean report.

Run against the working tree it prints BLUE for everything — the patches are
already applied there. That is correct, and it is why BLUE exists.

A worked example, against the real MediaWiki 1.43.9 tarball (2026-08-03):

```
9 GREEN  0 BLUE  1 AMBER  0 RED  8 not evaluated  1 stale   of 19
```

All three MediaWiki **core** patches are GREEN against 1.43.9 — the most
valuable single line in this document, since those are the ones a core security
release is most likely to break. `pdfhandler` is AMBER: `Hunk #1 FAILED at
226`. The eight N/A are the BlueSpice extensions and `app/vendor/`, which a
core tarball does not contain — on a real re-vendored branch an N/A would mean
a component got dropped, and there it has to be chased.

### Adding a patch

1. Make the change in the tree and confirm it works.
2. Produce a `.patch`: reconstruct the unpatched file, then
   `diff -u <before> <after>`. Verify it round-trips — applying to the before
   state must reproduce the after state byte-for-byte.
3. Write `docker/patches/<id>.yaml`. Required keys: `id`, `title`, `class`,
   `mode`, `target`, `stale`, `why`. `mode: insert` also requires `marker`;
   `mode: diff` requires `patch`.
4. `scripts/verify-patches.sh --static` to check the schema, then
   `scripts/verify-patches.sh --id <id>`.
5. `./scripts/check.sh` before pushing.

Pick the marker carefully. It should be a string unique to *our* change — the
comment the patch inserts, not the function it calls. Matching a function name
means verify goes green if upstream later adds its own call while our patch is
gone.

### If the tooling is unavailable

The same checks by hand:

```bash
# Class A — marker present?
grep -c 'setSSLVerification( false )' app/extensions/BlueSpiceExtendedSearch/src/Backend.php
grep -c 'const \$searchCnt'  app/extensions/BlueSpiceExtendedSearch/resources/ext.blueSpiceExtendedSearch.SearchCenter.js

# Class C — a patch that is already applied will refuse to apply again.
# "previously applied" = present. "applies cleanly" = MISSING from the tree.
cd app
for d in $(find _bluespice/patches -name '*.diff' | sort); do
  t="${d#_bluespice/patches/}"; t="${t%.diff}"
  [ -e "$t" ] || { echo "STALE/ABSENT  $t"; continue; }
  patch --dry-run --forward --ignore-whitespace --fuzz 3 "$t" "$d" >/dev/null 2>&1 \
    && echo "MISSING       $t" || echo "present       $t"
done
```
