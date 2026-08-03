#!/usr/bin/env python3
"""Track B — has upstream released something we do not have?

Renovate (Track A) cannot answer this. MediaWiki core is not a composer
dependency in this repository, it is 53,938 committed files; and the BlueSpice
extensions come from a composer repository of their own. Neither is visible to
a lockfile bump, so the only way to notice a core security release is to ask
the release feeds directly and compare against VERSIONS.yml.

The output is an *issue*, not a pull request. There is no automatable diff for
a vendored tree: taking a MediaWiki release here means re-vendoring 53,938
files and re-applying 19 patches, three of which target core. A bot cannot
prepare that. What it can do is make sure a human hears about it in the same
week rather than the same year.

Feeds, both chosen for being machine-readable and authoritative:

  https://releases.wikimedia.org/mediawiki/            the branch list
  https://releases.wikimedia.org/mediawiki/1.43/       the tarballs in a branch
  https://packages.bluespice.com/p2/bluespice/foundation.json
                                                       BlueSpice's own composer
                                                       repository (packages.json
                                                       declares metadata-url
                                                       /p2/%package%.json)

`bluespice/foundation` is the package used as the BlueSpice version signal: it
is in this fork's composer.lock, every BlueSpice release publishes it, and it
carries the whole 91-version history.

Exit: 0 nothing new · 1 upstream has moved · 77 a feed did not answer
"""
import json
import re
import sys
import urllib.error
import urllib.request

MW_INDEX = "https://releases.wikimedia.org/mediawiki/"
BS_METADATA = "https://packages.bluespice.com/p2/bluespice/foundation.json"
BS_PACKAGE = "bluespice/foundation"
TIMEOUT = 30
USER_AGENT = "hdp-release-watch (+https://github.com/Sidiberlin/hdp)"


class FeedError(Exception):
    """A feed could not be read. Distinct from 'a feed said we are behind'."""


# ─── Version handling ───────────────────────────────────────────────


def parse_version(text):
    """('1.43.5') -> (1, 43, 5). Returns None for anything non-numeric.

    Deliberately strict: a pre-release like 5.2.0-beta must not compare as a
    release, and a tag this function cannot read must be ignored rather than
    guessed at — announcing a fictional upstream release would be worse than
    announcing nothing, because the first false alarm is what teaches people
    to close the issue unread.
    """
    if not text or not re.fullmatch(r"\d+(\.\d+)*", str(text).strip()):
        return None
    return tuple(int(p) for p in str(text).strip().split("."))


def format_version(parts):
    return ".".join(str(p) for p in parts)


def branch_of(version):
    """(1, 43, 5) -> (1, 43) — the release branch a version belongs to."""
    return tuple(version[:2])


# ─── Feed parsing (pure; the tests drive these directly) ────────────


def parse_mw_branches(html):
    """Every MAJOR.MINOR directory in the releases index."""
    found = {parse_version(m) for m in re.findall(r'href="(\d+\.\d+)/"', html)}
    return sorted(v for v in found if v)


def parse_mw_releases(html):
    """Every mediawiki-X.Y.Z.tar.gz in a branch index.

    `.tar.gz` only. The same directory also lists `-patch-`, `.tar.gz.sig` and
    `.patch.gz` files, and matching those would report a security *patch file*
    as if it were a release.
    """
    found = {parse_version(m) for m in re.findall(r"mediawiki-(\d+\.\d+\.\d+)\.tar\.gz(?![.\w])", html)}
    return sorted(v for v in found if v)


def parse_bluespice_versions(payload):
    """Stable versions of bluespice/foundation from a composer p2 document."""
    packages = (payload or {}).get("packages") or {}
    entries = packages.get(BS_PACKAGE) or []
    out = set()
    for entry in entries:
        raw = entry.get("version") if isinstance(entry, dict) else None
        if not raw:
            continue
        # -beta / -rc / -alpha are published to the same repository. A beta is
        # not something to upgrade a public-sector wiki to on a Monday.
        if "-" in str(raw):
            continue
        parsed = parse_version(str(raw).lstrip("v"))
        if parsed:
            out.add(parsed)
    return sorted(out)


# ─── Fetching ───────────────────────────────────────────────────────


def fetch(url, opener=None):
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    try:
        with (opener or urllib.request.urlopen)(request, timeout=TIMEOUT) as response:
            return response.read().decode("utf-8", "replace")
    except (urllib.error.URLError, OSError, ValueError) as exc:
        raise FeedError(f"{url}: {exc}") from exc


def check(declared_mw, declared_bs, fetcher=fetch):
    """Compare the declared versions against the feeds. Returns a report dict."""
    mw = parse_version(declared_mw)
    bs = parse_version(declared_bs)
    if not mw or not bs:
        raise FeedError(f"VERSIONS.yml holds unreadable versions: mw_core={declared_mw!r}, "
                        f"bluespice={declared_bs!r}")

    report = {"declared": {"mw_core": format_version(mw), "bluespice": format_version(bs)},
              "findings": [], "checked": []}

    branch = branch_of(mw)
    branch_url = f"{MW_INDEX}{format_version(branch)}/"
    releases = parse_mw_releases(fetcher(branch_url))
    report["checked"].append(branch_url)
    same_branch = [v for v in releases if branch_of(v) == branch]
    if same_branch and max(same_branch) > mw:
        latest = max(same_branch)
        report["findings"].append({
            "what": "mediawiki-patch",
            "severity": "act",
            "current": format_version(mw),
            "latest": format_version(latest),
            "url": branch_url,
            "note": ("A newer release exists on our own branch. MediaWiki ships security "
                     "fixes as patch releases on supported branches, so this is the finding "
                     "the whole job exists for — read the release notes before anything else."),
        })

    branches = parse_mw_branches(fetcher(MW_INDEX))
    report["checked"].append(MW_INDEX)
    newer_branches = [b for b in branches if b > branch]
    if newer_branches:
        report["findings"].append({
            "what": "mediawiki-branch",
            "severity": "plan",
            "current": format_version(branch),
            "latest": format_version(max(newer_branches)),
            "url": MW_INDEX,
            "note": ("A newer release branch exists. This is a planning signal, not an "
                     "alarm: 1.43 is an LTS branch and stays supported. It becomes urgent "
                     "only as that support window closes."),
        })

    payload = json.loads(fetcher(BS_METADATA) or "{}")
    versions = parse_bluespice_versions(payload)
    report["checked"].append(BS_METADATA)
    bs_branch = branch_of(bs)
    same_series = [v for v in versions if branch_of(v) == bs_branch]
    if same_series and max(same_series) > bs:
        report["findings"].append({
            "what": "bluespice-patch",
            "severity": "act",
            "current": format_version(bs),
            "latest": format_version(max(same_series)),
            "url": "https://en.wiki.bluespice.com/wiki/Setup:Release_History",
            "note": ("BlueSpice has published a newer patch release on our own series. "
                     "Same reasoning as the MediaWiki one: a patch release on the series "
                     "you are already running is where security content lands."),
        })
    newer_series = [v for v in versions if branch_of(v) > bs_branch]
    if newer_series:
        report["findings"].append({
            "what": "bluespice-minor",
            "severity": "plan",
            "current": format_version(bs_branch),
            "latest": format_version(max(newer_series)),
            "url": "https://en.wiki.bluespice.com/wiki/Setup:Release_History",
            "note": ("A newer BlueSpice series exists. Planning signal: a series bump "
                     "moves extension code the 17 inherited patches are written against, "
                     "so it needs the full upgrade-report triage, not a fast path."),
        })

    return report


def render(report):
    lines = []
    declared = report["declared"]
    lines.append(f"  declared: MediaWiki {declared['mw_core']}, BlueSpice {declared['bluespice']}")
    if not report["findings"]:
        lines.append("  up to date with both feeds.")
        return lines
    for finding in report["findings"]:
        lines.append("")
        lines.append(f"  [{finding['severity'].upper()}] {finding['what']}: "
                     f"{finding['current']} -> {finding['latest']}")
        lines.append(f"    {finding['note']}")
        lines.append(f"    {finding['url']}")
    lines.append("")
    lines.append("  Next: docs/dev/upgrade-runbook.md. Step 1 is the patch upgrade report,")
    lines.append("  before anything is bumped: scripts/verify-patches.sh --upgrade-report")
    return lines


def issue_payload(report):
    """The GitHub issue this job files, as {title, body}.

    The title carries the versions so it is stable while the finding is: the
    workflow files an issue only when no open issue already has this exact
    title, so a weekly run cannot pile up duplicates, and upstream releasing
    again produces a genuinely different title.
    """
    declared = report["declared"]
    acts = [f for f in report["findings"] if f["severity"] == "act"]
    parts = []
    for finding in report["findings"]:
        if finding["what"] == "mediawiki-patch":
            parts.append(f"MediaWiki {finding['latest']}")
        elif finding["what"] == "bluespice-patch":
            parts.append(f"BlueSpice {finding['latest']}")
        elif finding["what"] in ("mediawiki-branch", "bluespice-minor") and not acts:
            parts.append(f"{finding['what'].split('-')[0]} series {finding['latest']}")
    title = "Upstream release: " + (", ".join(parts) if parts else "upstream has moved")

    body = [
        f"This wiki declares **MediaWiki {declared['mw_core']}** and "
        f"**BlueSpice {declared['bluespice']}** (`VERSIONS.yml`). The upstream release "
        "feeds say otherwise.",
        "",
        "| | current | latest | |",
        "|---|---|---|---|",
    ]
    for finding in report["findings"]:
        body.append(f"| {finding['what']} | {finding['current']} | {finding['latest']} | "
                    f"{finding['severity'].upper()} |")
    body += [
        "",
        *[f"**{f['what']}** — {f['note']} ({f['url']})\n" for f in report["findings"]],
        "### What to do",
        "",
        "Read `docs/dev/upgrade-runbook.md`. The first step is deliberately not the",
        "bump — it is the patch baseline, because 19 patches have to survive it and",
        "three of them target MediaWiki core:",
        "",
        "```",
        "scripts/verify-patches.sh --upgrade-report",
        "```",
        "",
        "If the release notes describe an actively exploited vulnerability, take the",
        "security fast path in that runbook rather than the normal procedure.",
        "",
        "---",
        "_Filed by `.github/workflows/release-watch.yml`. Closing this issue is fine —",
        "it will be filed again if upstream moves further._",
    ]
    return {"title": title, "body": "\n".join(body)}


def main(argv):
    import os

    here = os.path.dirname(os.path.abspath(__file__))
    sys.path.insert(0, here)
    import versions as versions_mod

    root = os.environ.get("HDP_REPO_ROOT") or os.path.abspath(os.path.join(here, "..", ".."))
    declared = versions_mod.load_versions(os.path.join(root, "VERSIONS.yml"))

    try:
        report = check(declared.get("mw_core"), declared.get("bluespice"))
    except FeedError as exc:
        # A feed that did not answer is not "no news". Reporting up-to-date on
        # a failed fetch would be the same defect as a security gate passing
        # because it never ran.
        print(f"  release-watch could not read a feed: {exc}")
        print("  This is NOT a clean result — nothing was compared.")
        return 77

    if "--json" in argv:
        print(json.dumps(report, indent=2))
    elif "--issue" in argv:
        print(json.dumps(issue_payload(report), indent=2))
    else:
        print("\n".join(render(report)))
    return 1 if report["findings"] else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
