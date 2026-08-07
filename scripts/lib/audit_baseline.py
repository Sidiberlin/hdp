#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""Compare `composer audit` output against the accepted-advisory baseline.

`composer audit --locked` on this tree reports 8 advisories across 3 packages
as reviewed on 2026-08-04, two of them high. None of those versions is this
fork's choice: `app/composer.json` is upstream's
`bluespice/core`, and the affected packages are transitive dependencies of
MediaWiki 1.43 and BlueSpice 5.1.9, or vendored extensions of it. Fixing them
means re-vendoring upstream, which is the upgrade process in
docs/dev/upgrade-runbook.md — not something a contributor can do in a PR.

So a bare `composer audit` as a blocking gate would be red on day one and red
on every push after it, and a permanently red gate teaches everyone to ignore
the pipeline. That is the failure mode this file exists to avoid *without*
giving up the signal.

The baseline records what was known and accepted, with a reason per package.
The gate fails on anything **not** in it: a new advisory against a package we
already carry is as much news as a new package appearing. That is the actual
signal — "did upstream's dependency surface get worse since we last looked" —
and it is the question `composer audit` can answer that nothing else in this
repo can.

Exit: 0 nothing new · 1 a new advisory, a version move, or a bad entry
      2 malformed input
"""
import json
import os
import sys

SEVERITY_ORDER = {"critical": 0, "high": 1, "medium": 2, "low": 3}


class AuditError(Exception):
    pass


def advisory_id(adv):
    """The stable identifier, preferring the CVE.

    Packagist mints a PKSA- id for every advisory and a CVE arrives later, so
    an entry keyed only on the PKSA id would re-fire as "new" the day the CVE
    is assigned. Both are recorded; either matches.
    """
    return adv.get("cve") or adv.get("advisoryId") or "UNKNOWN"


def advisory_ids(adv):
    return {i for i in (adv.get("cve"), adv.get("advisoryId")) if i}


def normalise(report):
    """{package: [advisory, ...]} from composer's JSON.

    `composer audit --format=json` emits each package's advisories as a JSON
    array — except when it does not: a package whose advisory list has
    non-sequential keys comes back as an *object* keyed by index (phpunit does
    this today). Reading only the list form drops those packages silently,
    which in a security gate means a vulnerability that reports as clean.
    """
    if not isinstance(report, dict):
        raise AuditError("audit output is not a JSON object")
    out = {}
    for pkg, items in (report.get("advisories") or {}).items():
        if isinstance(items, dict):
            items = list(items.values())
        if not isinstance(items, list):
            raise AuditError(f"advisories for {pkg} are neither a list nor an object")
        for adv in items:
            if not isinstance(adv, dict):
                raise AuditError(f"advisory for {pkg} is not an object")
        out[pkg] = items
    return out


def compare(report, baseline, installed=None):
    """Returns (new, resolved, moved, problems) — everything the gate reports.

    `installed` is {package: version} from app/composer.lock. It is read from
    the lockfile rather than from the report because composer's audit JSON
    does not carry the installed version at all — only the affected range.
    """
    installed = installed or {}
    accepted = baseline.get("accepted")
    if not isinstance(accepted, dict):
        raise AuditError("baseline has no 'accepted' object")

    found = normalise(report)
    new, problems = [], []

    for pkg, advisories in sorted(found.items()):
        entry = accepted.get(pkg)
        known = set(entry.get("advisories") or []) if isinstance(entry, dict) else set()
        for adv in advisories:
            if not (advisory_ids(adv) & known):
                new.append((pkg, adv))

    seen_ids = {pkg: set().union(*(advisory_ids(a) for a in advs)) if advs else set()
                for pkg, advs in found.items()}
    resolved = []
    for pkg, entry in sorted(accepted.items()):
        if not isinstance(entry, dict):
            problems.append(f"accepted/{pkg}: entry is not an object")
            continue
        if not entry.get("why"):
            # `--update` writes the ids and leaves this blank on purpose: an
            # accepted vulnerability with no stated reason is not an
            # acceptance, it is a silence.
            problems.append(f"accepted/{pkg}: no 'why' — say why carrying this is acceptable")
        for known in entry.get("advisories") or []:
            if known not in seen_ids.get(pkg, set()):
                resolved.append((pkg, known))

    moved = []
    for pkg, entry in sorted(accepted.items()):
        if isinstance(entry, dict) and entry.get("installed") and pkg in found:
            was, now = entry["installed"], installed.get(pkg)
            if now and now != was:
                moved.append((pkg, was, now))

    return new, resolved, moved, problems


def render(new, resolved, moved, problems):
    lines = []
    if new:
        lines.append("")
        lines.append(f"  {len(new)} advisory/advisories are NOT in the accepted baseline:")
        for pkg, adv in sorted(new, key=lambda t: (SEVERITY_ORDER.get(t[1].get("severity"), 9), t[0])):
            lines.append(f"    {adv.get('severity') or '?':8s} {pkg}  {advisory_id(adv)}")
            lines.append(f"             {(adv.get('title') or '')[:100]}")
            lines.append(f"             affects {(adv.get('affectedVersions') or '?')[:80]}")
            if adv.get("link"):
                lines.append(f"             {adv['link']}")
    for pkg, known in resolved:
        lines.append(f"  gone   {pkg} {known} is no longer reported — drop it from the baseline")
    for pkg, was, now in moved:
        lines.append(f"  moved  {pkg} was {was}, is now {now} — re-review the acceptance")
        lines.append(f"         the reason recorded for {pkg} argues about {was} specifically;")
        lines.append("         at a different version it is an assertion, not an assessment")
    for p in problems:
        lines.append(f"  BAD    {p}")
    return lines


def build_baseline(report, lock, previous=None):
    """A baseline from the current report, keeping any reasons already written."""
    previous = previous or {}
    prev_accepted = previous.get("accepted") or {}
    installed = {p["name"]: p["version"]
                 for p in lock.get("packages", []) + lock.get("packages-dev", [])}
    accepted = {}
    for pkg, advisories in sorted(normalise(report).items()):
        ids = sorted({i for adv in advisories for i in advisory_ids(adv)})
        accepted[pkg] = {
            "installed": installed.get(pkg, "?"),
            "advisories": ids,
            "why": (prev_accepted.get(pkg) or {}).get("why", ""),
        }
    out = dict(previous)
    out["accepted"] = accepted
    return out


def main(argv):
    if len(argv) < 3:
        print("usage: audit_baseline.py <audit.json> <baseline.json> <composer.lock> [--update]",
              file=sys.stderr)
        return 2
    report = json.load(open(argv[0], encoding="utf-8"))
    baseline_path = argv[1]
    lock = json.load(open(argv[2], encoding="utf-8"))
    installed = {p["name"]: p["version"]
                 for p in lock.get("packages", []) + lock.get("packages-dev", [])}

    if "--update" in argv:
        try:
            previous = json.load(open(baseline_path, encoding="utf-8"))
        except FileNotFoundError:
            previous = {}
        built = build_baseline(report, lock, previous)
        with open(baseline_path, "w", encoding="utf-8") as fh:
            # ensure_ascii=False because the reasons are prose and contain em
            # dashes and arrows. Without it every regeneration rewrites every
            # "why" line into \uXXXX escapes, and the real change — which
            # advisories moved — is buried in a diff nobody will read.
            json.dump(built, fh, indent=2, sort_keys=False, ensure_ascii=False)
            fh.write("\n")
        missing = [p for p, e in built["accepted"].items() if not e["why"]]
        print(f"  wrote {baseline_path}: {len(built['accepted'])} package(s)")
        if missing:
            print(f"  {len(missing)} still need a 'why': {', '.join(missing)}")
        return 0

    baseline = json.load(open(baseline_path, encoding="utf-8"))
    new, resolved, moved, problems = compare(report, baseline, installed)
    for line in render(new, resolved, moved, problems):
        print(line)

    total = sum(len(v) for v in normalise(report).values())

    # `moved` fails, and it did not used to. compare() found it and render()
    # printed it, but main() returned 1 only on `new or problems`, so a package
    # whose installed version changed under an existing acceptance went by as a
    # line in a passing log.
    #
    # That is the wrong way round. These acceptances lean hard on the exact
    # version: guzzle's is a 400-word argument about 7.12.3 — which call sites
    # exist in this tree, that core substitutes its own CookieJar, that
    # $wgAllowCopyUploads is off — and it is reasoning about a specific
    # dependency surface, not about the package in general. A version move is
    # precisely the moment that reasoning stops being known-good, which makes it
    # the same class as a blank `why`: an acceptance nobody has actually made.
    #
    # `--update-baseline` rewrites `installed` and keeps the prose, so clearing
    # this is a deliberate act — re-read the reason, then regenerate.
    if new or problems or moved:
        print("")
        if new or problems:
            print("  A new advisory is the signal this gate exists for. Either take the fix")
            print("  (docs/dev/upgrade-runbook.md — the security fast path), or record the")
            print("  acceptance with a reason:")
        else:
            print("  An accepted package moved version. The recorded reason was written")
            print("  against the old one, so re-read it against the new one — then, if it")
            print("  still holds, record the move:")
        print("    scripts/ci/composer-audit.sh --update-baseline")
        # GitHub renders these in the job summary and against the file, which is
        # where somebody skimming a red run actually looks.
        if os.environ.get("GITHUB_ACTIONS"):
            for pkg, was, now in moved:
                print(f"::error title=Accepted advisory moved version::{pkg} was {was}, "
                      f"is now {now} — the acceptance in {baseline_path} was reasoned "
                      "against the old version")
        return 1
    print(f"  {total} known advisory/advisories, all accepted in "
          f"{baseline_path} (reviewed {baseline.get('reviewed', '?')})")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main(sys.argv[1:]))
    except AuditError as exc:
        print(f"audit_baseline.py: {exc}", file=sys.stderr)
        sys.exit(2)
    except (OSError, ValueError) as exc:
        print(f"audit_baseline.py: {exc}", file=sys.stderr)
        sys.exit(2)
