# Third-Party Licences

The aggregate distribution in this repository is licensed under **GPL-3.0-only**
(see [`LICENSE`](LICENSE)). That single file is not the whole picture: `app/` vendors
47,614 committed files from MediaWiki, BlueSpice and 184 extensions, and those carry
**15 distinct licences** between them. This document is the inventory GPL does not
require but a reader needs.

Nothing here supersedes a per-package licence. Each extension keeps its own
`LICENSE`/`COPYING` file in situ — 240 of them survive under `app/` — and those are
the authoritative text. This file exists so nobody has to open 240 files to answer
"what is in here?".

That count is of committed files, so it can be re-derived:

```bash
git ls-files app | grep -Eic '(^|/)(licen[sc]e|copying)[^/]*$'
```

A `find` over the working tree answers a different question and gives a larger
number, because `app/vendor/` is present after a build and is not committed.

## Core platforms

| Component | Version | Licence | Upstream |
|---|---|---|---|
| MediaWiki | 1.43.9 | GPL-2.0-or-later | https://www.mediawiki.org |
| BlueSpice (BlueSpiceFoundation) | 5.1.9 | GPL-3.0-only | https://bluespice.com |

Versions are the ones declared in [`VERSIONS.yml`](VERSIONS.yml) and asserted against
the tree by `scripts/ci/version-consistency.sh` on every push.

## Extensions

184 extensions under `app/extensions/`, counted by the `license-name` field of each
`extension.json`:

| Licence | Count | GPL-3.0 compatible? |
|---|---:|---|
| GPL-3.0-only | 95 | ✅ |
| GPL-2.0-or-later | 53 | ✅ (upgradeable to v3) |
| MIT | 13 | ✅ |
| GPL-3.0-or-later | 7 | ✅ |
| `GPL-2.0` (bare) | 4 | ✅ — see note below |
| GPL-2.0-or-later AND GPL-3.0-or-later | 2 | ✅ |
| *(not declared)* | 2 | ⚠️ see note below |
| GPL-2.0-or-later AND BSD-3-Clause | 1 | ✅ |
| GPL-2.0-or-later AND MIT | 1 | ✅ |
| `GPL-2.0+` | 1 | ✅ |
| `GPL-3.0+` | 1 | ✅ |
| LGPL-3.0-only | 1 | ✅ |
| WTFPL | 1 | ✅ |
| CC0-1.0 | 1 | ✅ |
| ISC | 1 | ✅ |

Regenerate the table rather than editing it by hand:

```bash
python3 - <<'EOF'
import json, glob, collections
c = collections.Counter()
for f in sorted(glob.glob('app/extensions/*/extension.json')):
    c[json.load(open(f, encoding='utf-8')).get('license-name') or '(not declared)'] += 1
for lic, n in c.most_common():
    print(f'{n:4d}  {lic}')
EOF
```

### Note — the four bare `GPL-2.0` entries

`LDAPAuthentication2`, `LDAPAuthorization`, `LDAPGroups` and `LDAPUserInfo` declare
`GPL-2.0` in `extension.json`. Read strictly as SPDX, `GPL-2.0` means *GPL-2.0-only*,
which would be **incompatible** with a GPL-3.0 aggregate.

This was checked rather than assumed. All four declare `GPL-2.0+` (or-later) in their
`composer.json`, which settles it: or-later is upgradeable to v3. The `extension.json`
strings are legacy imprecise SPDX identifiers, not an actual incompatibility. Nothing
to fix — recorded so the question does not have to be re-asked.

(In-file GPL headers are not the evidence here: three of the four carry none at all,
and the one that does — `LDAPGroups/src/Config.php` — says version 3 or later. The
`composer.json` declarations are what the finding rests on.)

`GPL-3.0+` and `GPL-2.0+` are likewise deprecated SPDX spellings (the current forms are
`-or-later`). Upstream metadata, cosmetic.

### Note — the two extensions with no declared licence

`BlueSpiceWikiFarm` and `HeaderFooter` declare no `license-name` in `extension.json`.
This is an upstream omission, inherited by this fork. It is recorded here rather than
guessed at: neither this project nor this document can assign a licence to code it did
not write.

## "Frozen" packages

Two extensions come from a private upstream registry (`gitlab.hallowelt.com`) that this
project cannot reach, so they are vendored directly under `app/extensions/` rather than
resolved by composer:

| Package | Extension | Licence (`extension.json` **and** `composer.json`) |
|---|---|---|
| `hallowelt/chatbot` | `ChatBot` | GPL-3.0-only |
| `mediawiki/page-header` | `PageHeader` | GPL-3.0-only |

Both declare GPL-3.0-only in both manifests, so redistribution in this tree is
permitted. They are Track C of the security process — invisible to Renovate, to
`composer audit` and to the release-watch job — see [`SECURITY.md`](SECURITY.md) and the
`frozen:` block in [`VERSIONS.yml`](VERSIONS.yml).

## Composer dependencies

`app/vendor/` is **not** committed (see [`.gitignore`](.gitignore)); it is produced at
build time from `app/composer.json` / `app/composer.lock`. Licences for those packages
are whatever the lockfile resolves, and are inspectable with:

```bash
composer licenses --working-dir=app
```

## Modifications to upstream files

GPL-3.0 §5(a) requires modified files to carry prominent notices of change. This fork
satisfies it mechanically: `docker/patches/` holds 21 patch manifests, each declaring
the `target` file, `why` it is patched and the `upstream_version` it was written
against. `scripts/verify-patches.sh` then asserts each one is still in place, by
whichever of two mechanisms the patch uses:

- **3 `mode: insert` patches** declare a `marker` regex naming a comment the patch
  injects into the modified file; verification greps the target for it.
- **18 `mode: diff` patches** (inherited from BlueSpice) point at a committed `.diff`;
  verification replays it with `patch --dry-run --forward` and requires the target to
  report "previously applied".

Fork-authored changes are listed in [`CHANGELOG.md`](CHANGELOG.md), which is
deliberately fork-only — a reader must be able to tell this fork's changes from
upstream's.

## Attribution

Copyright in the bulk of this tree is held by **Hallo Welt! GmbH** and the MediaWiki
contributors; see the banner in [`LICENSE`](LICENSE) and the upstream licence files
preserved under `app/`. This repository is a fork — see the "Why this fork?" section of
[`README.md`](README.md) for what was changed and by whom.
