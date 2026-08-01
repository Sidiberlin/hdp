# Patches

HDP ships modifications to vendored upstream code. This file is the inventory:
what they are, how each one is applied, how each one fails, and which ones are
dead.

It is deliberately hand-maintained and small. The machine-readable manifest
(one YAML sidecar per patch) and `scripts/verify-patches.sh` are Wave 1 work;
until they land this page is the only place the count is written down.

---

## Inventory: 19 targets, 18 live

| Class | Count | Applied by | Fails by |
|---|---|---|---|
| **A** — composer-clobbered | 2 | `docker/setup.sh`, inline `sed -i` | `composer install` reinstalls the package as a dist zipball over the patch |
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
17) — the same number as the original estimate, reached a different way. If
you are building the Wave 1.4 manifest, build **19 sidecars**, and expect
`verify-patches.sh` to report 18 applicable plus 1 stale.

---

## Class A — composer-clobbered (2)

Both live in `app/extensions/BlueSpiceExtendedSearch/`, both are reinstalled
from a dist zipball by `composer install`, and both are re-applied by
`docker/setup.sh` after composer runs.

| id | Target | Marker |
|---|---|---|
| `es-ssl` | `extensions/BlueSpiceExtendedSearch/src/Backend.php` | `setSSLVerification( false )` |
| `es-searchcnt` | `extensions/BlueSpiceExtendedSearch/resources/ext.blueSpiceExtendedSearch.SearchCenter.js` | `const $searchCnt` |

Note the real path of the second one. The strategy document refers to it as
`SearchCenter.js`, which matches nothing — the file is
`resources/ext.blueSpiceExtendedSearch.SearchCenter.js`, a flat filename, not
`resources/<module-dir>/SearchCenter.js`. A `find -name 'SearchCenter.js'`
returns nothing and will make you think the patch is missing. Use the path
above when writing the manifest.

Both blocks re-verify their own marker after running (`docker/setup.sh`).
`sed -i` exits 0 when its address matches nothing, so without that re-check
applying the patch and silently doing nothing are indistinguishable.

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
maintain it). Revisit if BlueSpice reissues the patch set. When the Wave 1.4
manifest lands, this patch gets an explicit `stale: true` field so the
condition is declared rather than inferred.

---

## How each class fails silently

Worth stating plainly, because all three failure modes are silent by default
and each was observed on a clean box:

| Mechanism | Silent because |
|---|---|
| `sed -i` in `setup.sh` | exits 0 when the address matches nothing |
| `99-apply_patches.sh` | prints `FAILED!`, has no `set -e`, returns no exit code |
| `composer dump-autoload` | ignores the hook scripts' exit statuses entirely |

`docker/setup.sh` now detects all three and reports them in a summary before
exiting non-zero. That is a backstop, not the gate — the gate is
`scripts/verify-patches.sh` (Wave 1.5), which checks the resulting tree rather
than scraping logs.

## Verifying by hand

Until `verify-patches.sh` exists:

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
