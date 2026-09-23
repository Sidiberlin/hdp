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

## Inventory: 29 targets, 28 live

| Class | Count | Applied by | Fails by |
|---|---|---|---|
| **A** — composer-clobbered | 11 | `scripts/apply-patches.sh`, called by `docker/setup.sh` | `composer install` reinstalls the package as a dist zipball over the patch |
| **B** — gitignore-swallowed | 0 | — | *(retired, see below)* |
| **C** — inherited BlueSpice diffs | 18 | `app/_bluespice/pre-autoload-dump.d/99-apply_patches.sh` | the script prints `FAILED!` and continues, with no exit code |

Of the 18 Class-C patches, **one is permanently stale** (`PF_UploadForm.php`,
below), leaving **20 patches that can actually apply**.

### Getting the count right

The CI strategy document says "19 patches" and breaks it down as 2 + 1 + 16.
That arithmetic was wrong in two places, and they cancelled out:

- Class C is **17** `.diff` files, not 16. Count them:
  `find app/_bluespice/patches -name '*.diff' | wc -l`. The same document's
  prose says 17 elsewhere; only the table says 16.
- Class B is now **0**, not 1.

So the total was briefly **20** (2 + 1 + 17), and reached **19** again (1 + 0 +
18) — the same number as the original estimate, arrived at a different way.

The 1 + 18 split was itself post-upgrade arithmetic. Going to BlueSpice 5.1.9
retired `es-searchcnt` from Class A and added `gallery-slideshow` to Class C,
so the two classes traded a patch and the total did not move. See "Retired"
below.

It was **21** (3 + 0 + 18) after the two `maps-layercontrol-xss-*` patches
landed on 2026-08-04 as the in-tree mitigation for CVE-2026-52854, which
cannot be fixed by re-vendoring inside the BlueSpice 5.1 series.

It is now **29** (11 + 0 + 18): DEPS-02 (2026-09-23) added eight more Class A
sidecars — seven backported SMW advisories, one of them (CVE-2025-61682) two
sidecars for one upstream commit, same reason as the Maps pair. See "The SMW
set" below. The manifest holds **29 sidecars**, and `verify-patches.sh`
reports 28 applicable plus 1 stale.

---

## Class A — composer-clobbered (11)

These live under `app/extensions/`, are reinstalled from dist zipballs by
`composer install`, and are re-applied afterwards by
`scripts/apply-patches.sh --class A`, which `docker/setup.sh` calls.

| id | Target | Marker |
|---|---|---|
| `es-ssl` | `extensions/BlueSpiceExtendedSearch/src/Backend.php` | `// HDP runs OpenSearch with its default self-signed demo certs` |
| `maps-layercontrol-xss-js` | `extensions/Maps/resources/leaflet/jquery.leaflet.js` | `// HDP: layer-control labels are rendered as HTML by Leaflet (CVE-2026-52854)` |
| `maps-layercontrol-xss-php` | `extensions/Maps/src/LeafletService.php` | `HDP: backport of upstream Maps 12.1.3 (CVE-2026-52854)` |
| `smw-ask-sep-xss` | `extensions/SemanticMediaWiki/src/Query/ResultPrinters/TableResultPrinter.php` | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77607)` |
| `smw-ask-plain-header-xss` | `extensions/SemanticMediaWiki/src/Query/ResultPrinters/TableResultPrinter.php` | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77606)` |
| `smw-searchbyproperty-error-xss` | `extensions/SemanticMediaWiki/src/MediaWiki/Specials/SearchByProperty/PageBuilder.php` | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77608)` |
| `smw-uriresolver-open-redirect` | `extensions/SemanticMediaWiki/src/MediaWiki/Specials/SpecialURIResolver.php` | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77609)` |
| `smw-debug-query-xss` | `extensions/SemanticMediaWiki/src/Query/DebugFormatter.php` | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77610)` |
| `smw-facetedsearch-cstate-xss` | `extensions/SemanticMediaWiki/src/MediaWiki/Specials/FacetedSearch/HtmlBuilder.php` | `HDP: backport of upstream SMW 7.2.1 (GHSA-9rcc-pmj8-ffhr)` |
| `smw-subtab-xss-php` | `extensions/SemanticMediaWiki/src/Utils/HtmlTabs.php` | `HDP: backport of upstream SMW 7.0.0 (CVE-2025-61682)` |
| `smw-subtab-xss-js` | `extensions/SemanticMediaWiki/res/smw/ext.smw.js` | `HDP: backport of upstream SMW 7.0.0 (CVE-2025-61682)` |

In every case the marker is a comment the patch inserts, not the code it
changes. Matching the code would let verify pass if upstream one day made the
same change while our patch was gone — and for the Maps pair upstream *has*
made it, in 12.1.3, so matching `mw.html.escape` would be actively misleading.

### The Maps pair — CVE-2026-52854

Stored XSS: Leaflet's `L.control.layers` renders base-layer and overlay names
as HTML, and the `layers` / `overlays` parameters of `display_map` declare a
`values` whitelist that ParamProcessor only *warns* about rather than
enforcing. So any editor who can save wikitext could put markup into a layer
name and have it execute for every reader of the page.

Backported from upstream commit `737a993f`, released in Maps **12.1.3**. Two
sidecars for one upstream commit because a manifest entry is one target file:

- **`-php`** filters `layers` and `overlays` to the
  `egMapsLeafletAvailableLayers` / `egMapsLeafletAvailableOverlayLayers`
  whitelists in `LeafletService::newMapDataFromParameters`, so a non-enabled
  value never reaches the client. This is the half that actually closes it.
- **`-js`** wraps the label in `mw.html.escape` at both places Leaflet uses one
  as a control label. Defence in depth: with the PHP half in place the names
  are already known-good.

Note the deviation from upstream in the JS half. Upstream 12.1.x builds a
`baseLayers` object and passes it to `L.control.layers(baseLayers, overlays)`;
11.0.1 calls `control.addBaseLayer(layerObject, layerName)` in a loop instead.
The escape goes on the `addBaseLayer` argument here. The overlay half is
verbatim.

**This is not the real fix and is not meant to be permanent.** The real fix is
Maps ≥ 12.1.3, which this tree cannot take: `mediawiki/maps` is constrained to
`11.0.*` by `app/_bluespice/build/bluespice-pro-distribution/composer.json`, so
only a BlueSpice series bump relaxes it. `composer audit` will keep reporting
CVE-2026-52854 against the installed 11.0.1 — correctly, since the version is
still the vulnerable one — and the baseline entry in
`docker/ci/composer-audit-baseline.json` stays **ACTION REQUIRED** for that
reason. Retire both patches and both sidecars when Maps moves to 12.1.3 or
later; `--upgrade-report` will call them BLUE against such a tree, which is the
signal to delete rather than re-derive.

Note the real path of the retired second one. The strategy document refers to
it as `SearchCenter.js`, which matches nothing — the file is
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

### The SMW set — DEPS-02

Same precedent as the Maps pair: `mediawiki/semantic-media-wiki` 6.0.1's fix
floor is 7.3.0 (`docker/ci/composer-audit-baseline.json`'s
`mediawiki/semantic-media-wiki` entry has the full derivation), reachable only
by a BlueSpice series bump. Seven of the eight advisories that entry lists are
mitigated in-tree instead, as seven Class A patches across eight sidecars —
one upstream commit (CVE-2025-61682) needed two, one manifest entry per
target file, same reason as the Maps pair:

| id | Target | Fixed in | Marker |
|---|---|---|---|
| `smw-ask-sep-xss` | `TableResultPrinter.php` | 7.2.0 | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77607)` |
| `smw-ask-plain-header-xss` | `TableResultPrinter.php` | 7.2.0 | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77606)` |
| `smw-searchbyproperty-error-xss` | `SearchByProperty/PageBuilder.php` | 7.2.0 | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77608)` |
| `smw-uriresolver-open-redirect` | `SpecialURIResolver.php` | 7.2.0 | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77609)` |
| `smw-debug-query-xss` | `DebugFormatter.php` | 7.2.0 | `HDP: backport of upstream SMW 7.2.0 (CVE-2026-77610)` |
| `smw-facetedsearch-cstate-xss` | `FacetedSearch/HtmlBuilder.php` | 7.2.1 | `HDP: backport of upstream SMW 7.2.1 (GHSA-9rcc-pmj8-ffhr)` |
| `smw-subtab-xss-php` | `Utils/HtmlTabs.php` | 7.0.0 | `HDP: backport of upstream SMW 7.0.0 (CVE-2025-61682)` |
| `smw-subtab-xss-js` | `res/smw/ext.smw.js` | 7.0.0 | `HDP: backport of upstream SMW 7.0.0 (CVE-2025-61682)` |

All eight target paths above are relative to `extensions/SemanticMediaWiki/`.
Each was derived by diffing this tree's vendored 6.0.1 source against the
actual upstream release that fixed it (not just the advisory's prose), so
every patch is the minimal change at the real vulnerable sink — not a
mechanical port of upstream's surrounding refactor, most of which (property
promotion, `MessageBuilder` → `wfMessage()`, the `TemplateParser` migration in
FacetedSearch) is unrelated to the fix and not taken here.

**Not backported here:** `GHSA-jr78-w6w5-m8f8` (`action=smwtask`, the one
unauthenticated-reachable advisory of the eight). Deliberately carried, not
mitigated, pending the ChatBot Sibling Handler Sweep — see the baseline
entry's `why` and that phase's scope.

Retire all eight sidecars together when SMW reaches 7.3.0 or later (the
`smw-pager` Class C patch, discovered BLUE against 7.3.0 during this phase's
research, is a separate case — see its own sidecar).

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

## Retired at BlueSpice 5.1.9: `es-searchcnt`

`es-searchcnt` declared the `$searchCnt` that
`ext.blueSpiceExtendedSearch.SearchCenter.js` fired the
`bs.extendedsearch.searchcenter.getResults` hook with but never defined — a
`ReferenceError` out of the `.done()` handler that left the Search Center
spinning on results the API had already returned.

BlueSpice 5.1.9 deletes both `.fire()` calls. The only `mw.hook` left in the
file is `bs.extendedSearch.makeLookup`, so there is no longer an undeclared
variable to declare, and applying the patch just inserts a `const` nothing
reads. Retired: patch, sidecar and the tree edit are all gone.

Note what made this visible. The patch still *applied* cleanly against 5.1.9 —
its anchor, `const $altSearchCnt = ...`, is still there — so both
`apply-patches.sh` and `verify-patches.sh` reported it healthy. A patch that
applies is not the same as a patch that is still needed, and only reading the
new upstream tells you which. When triaging an upgrade, check the bug, not
just the hunk.

## Class C — inherited BlueSpice diffs (18)

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

The remaining 14 target bundled extensions and core resources
(MultimediaViewer ×3, SemanticResultFormats ×3, TextExtracts ×2, PageForms,
PdfHandler, PluggableAuth, SemanticMediaWiki, VisualEditor, and
`resources/src/mediawiki.page.gallery.slideshow.js`).

`gallery-slideshow` is new in the 5.1.9 patch set — the first addition to the
inherited patches since this file was written. BlueSpice also re-derived
`pdfhandler` and `mmv-bootstrap` for 5.1.9; the re-derived `pdfhandler` is
what kept that row out of AMBER when MediaWiki 1.43.9 reworked the same
argument list.

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

   Consider `anti:` — an ERE matching the **upstream content the patch
   replaces**, which must not be in the file once the patch is applied. It is
   checked in every mode, including `diff`, because a fuzzy application can
   leave both forms in the file and `patch` still reports "previously
   applied". The two authentication patches carry one for exactly that reason.
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
