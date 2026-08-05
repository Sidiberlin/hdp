# Security Policy / Sicherheitsrichtlinie

## Reporting a Vulnerability / Melden einer Schwachstelle

**EN:**

**Do NOT open an issue for security vulnerabilities.** Issues in this
repository are public, and there is no such thing as a confidential issue on
GitHub — an earlier version of this file told you to open one, which would have
published your report along with a working exploit.

To report a security issue, use GitHub's **private vulnerability reporting**:

1. Go to **[Security → Report a vulnerability](https://github.com/Sidiberlin/hdp/security/advisories/new)**
   (repository → *Security* tab → *Report a vulnerability*). The report is
   visible only to you and the maintainers, and it becomes the draft advisory
   the fix is published from.
2. If that page is not available to you, contact a maintainer directly through
   GitHub and ask for a private channel — **do not describe the issue in a
   public issue, pull request, or discussion thread first.**

Please include:
- Description of the vulnerability
- Steps to reproduce or proof of concept
- Affected versions (see `VERSIONS.yml`)
- Suggested fix (if any)

We will acknowledge receipt within **72 hours** and provide an initial assessment within **7 days**.

> **Maintainer note.** Private vulnerability reporting has to be switched on for
> the link above to work: *Settings → Code security → Private vulnerability
> reporting → Enable*. It is available on public repositories, so it must be
> enabled as part of making this one public — a disclosure policy whose only
> channel is a 404 is the same failure as the confidential-issue text it
> replaces. If a monitored mail address is preferred instead, name it here and
> delete this note; either is fine, an unreachable channel is not.

---

**DE:**

**Erstellen Sie KEIN Issue für Sicherheitslücken.** Issues in diesem Repository
sind öffentlich, und vertrauliche Issues gibt es auf GitHub nicht — eine
frühere Fassung dieser Datei forderte genau das, was Ihre Meldung samt
funktionsfähigem Exploit veröffentlicht hätte.

So melden Sie ein Sicherheitsproblem — über GitHubs **private
Schwachstellenmeldung**:

1. Öffnen Sie **[Security → Report a vulnerability](https://github.com/Sidiberlin/hdp/security/advisories/new)**
   (Repository → Reiter *Security* → *Report a vulnerability*). Die Meldung ist
   nur für Sie und die Maintainer sichtbar und wird zum Entwurf des Advisories,
   aus dem der Fix veröffentlicht wird.
2. Falls diese Seite für Sie nicht verfügbar ist, kontaktieren Sie eine
   Maintainerin oder einen Maintainer direkt über GitHub und bitten Sie um einen
   vertraulichen Kanal — **beschreiben Sie das Problem nicht vorab in einem
   öffentlichen Issue, Pull Request oder Diskussionsthread.**

Bitte geben Sie an:
- Beschreibung der Schwachstelle
- Schritte zur Reproduktion oder Proof of Concept
- Betroffene Versionen (siehe `VERSIONS.yml`)
- Vorgeschlagene Lösung (falls vorhanden)

Wir bestätigen den Eingang innerhalb von **72 Stunden** und liefern eine Ersteinschätzung innerhalb von **7 Tagen**.

---

## Supported Versions / Unterstützte Versionen

**EN:** Only the latest release receives security updates. **`VERSIONS.yml` at
the repository root is the single source of truth for what version this is** —
MediaWiki core, BlueSpice, PHP, MariaDB, OpenSearch, Haystack and all 184
installed extensions. A CI gate (`./scripts/check.sh --only versions`) fails
the build if that file and the tree disagree, so the answer to *"are we
affected by CVE-X"* is one file lookup rather than an archaeology exercise.

**DE:** Nur die neueste Version erhält Sicherheitsupdates. **`VERSIONS.yml` im
Wurzelverzeichnis ist die einzige verbindliche Versionsangabe** — MediaWiki,
BlueSpice, PHP, MariaDB, OpenSearch, Haystack und alle 184 installierten
Erweiterungen. Ein CI-Gate schlägt fehl, sobald Datei und Baum voneinander
abweichen.

## Scope / Geltungsbereich

**EN:** This policy covers the HDP Docker distribution, `docker-compose.yml`, `setup.sh`, and the Haystack RAG pipeline. Vulnerabilities in upstream BlueSpice extensions or MediaWiki core should be reported to their respective projects:

- MediaWiki: <https://phabricator.wikimedia.org/maniphest/>
- BlueSpice: <https://help.bluespice.com/>

**DE:** Diese Richtlinie deckt die HDP-Docker-Distribution, `docker-compose.yml`, `setup.sh` und die Haystack-RAG-Pipeline ab. Schwachstellen in Upstream-BlueSpice-Erweiterungen oder MediaWiki-Core sollten bei den jeweiligen Projekten gemeldet werden:

- MediaWiki: <https://phabricator.wikimedia.org/maniphest/>
- BlueSpice: <https://help.bluespice.com/>

---

## How this project watches for vulnerabilities

Three tracks, because the three parts of this tree are visible to completely
different tooling. Full design in `docs/dev/upgrade-runbook.md`.

### Track A — the composer-visible packages (381 of them)

`composer audit --locked` runs in CI on every push
(`scripts/ci/composer-audit.sh`). It is compared against
`docker/ci/composer-audit-baseline.json`, which records the advisories this
fork **knowingly carries**, with a reason for each, and the gate fails on
anything new. It audits the whole lockfile, `packages` and `packages-dev`
both — 311 + 70. (This section used to say 148, which is the count of the
`mediawiki/*` and `bluespice/*` entries alone, not what the gate covers.)

The baseline exists because the tree carries advisories inherited from
upstream's dependency choices — `app/composer.json` is upstream's
`bluespice/core`, so none of those versions is this fork's to pick. A gate that
is red on every push is a gate people stop reading; a gate that fires on a
*new* advisory is the signal worth having.

**As reviewed on 2026-08-04 that is 8 advisories across 3 packages, two of them
high.** It was 34 across 12 packages, two critical, until the 1.43.9 / 5.1.9
upgrade cleared 28 — that is what the upgrade was for. The baseline file is the
count of record; the numbers here date, it does not.

**One of those entries is marked ACTION REQUIRED** and is fixable only by
re-vendoring upstream:

| Package | Severity | Fixed in |
|---|---|---|
| `mediawiki/maps` | high — stored XSS via `display_map` | 12.1.3 |

It was four. The 1.43.9 / 5.1.9 upgrade closed three of them —
`phpoffice/phpspreadsheet` (2 critical, 5 high; parses uploaded spreadsheets)
at 1.30.6, `phpseclib/phpseclib` (2 high; sits under the OIDC client) at
3.0.56, and `universal-omega/dynamic-page-list3` (high; exposed suppressed
usernames) at 3.6.4. `mediawiki/maps` survived and **cannot be fixed inside the
5.1 series at all**: the fix is in 12.1.3 and the BlueSpice pro distribution
constrains the package to `11.0.*`, so only a series bump relaxes it.

The other two entries — `guzzlehttp/guzzle` and `web-auth/webauthn-lib` — are
carried, not fixable here, and not marked: both are pinned by upstream past the
version that would close them. The `why` field on each says what the exposure
is and what would change the assessment.

Renovate (`renovate.json`) opens grouped weekly PRs and **never auto-merges** —
merging is what triggers the patch re-application this process exists to guard.

### Track B — vendored MediaWiki core and BlueSpice

Invisible to Renovate: MediaWiki core here is 53,938 committed files, not a
composer dependency. `.github/workflows/release-watch.yml` polls
`releases.wikimedia.org` and `packages.bluespice.com` weekly, compares them
against `VERSIONS.yml`, and **opens an issue** on drift.

**The human backstop is the [`mediawiki-announce`](https://lists.wikimedia.org/postorius/lists/mediawiki-announce.lists.wikimedia.org/)
mailing list.** MediaWiki security releases are announced there first and the
weekly job sees them up to seven days later. The maintainer address should be
subscribed; no workflow can do this, and it is the fastest signal available.

### Track C — the two frozen packages

`hallowelt/chatbot` and `mediawiki/page-header` resolve from
`git@gitlab.hallowelt.com`, a private GitLab this project cannot reach.
`docker/setup.sh` strips both from `composer.lock` at install time and the
vendored source under `app/extensions/` is used instead.

**Their security posture is frozen at whatever was vendored.** No tooling will
ever flag a CVE in them: Renovate cannot resolve them, `composer audit` never
sees them because they are stripped, and the release-watch job does not know
they exist.

| Package | Vendored from | Owner |
|---|---|---|
| `hallowelt/chatbot` | `gitlab.hallowelt.com/GovTech/mediawiki-extensions-chatbot@d6ab09fb` | **unassigned** |
| `mediawiki/page-header` | `gitlab.hallowelt.com/BlueSpice/mediawiki-extensions-pageheader@505d0aa4` | **unassigned** |

The declaration lives in `VERSIONS.yml` under `frozen:`, including a
`last_reviewed` date. The version-consistency gate warns when that date is more
than six months old and asserts that `setup.sh` still strips both packages — a
declaration that has drifted from the code is worse than none.

**This is an accepted risk, recorded rather than solved.** The honest options
are: negotiate read access to the upstream repositories, replace both packages,
or keep accepting the risk with a named owner and a periodic manual diff.
Assigning that owner is an open item.

### What none of the three covers

Extensions vendored under `app/extensions/` that are not also composer
packages. They are inventoried in `VERSIONS.yml` — so a version *change* is
caught — but no advisory feed is consulted for them.
