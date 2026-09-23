"""The composer-audit gate — does a new CVE actually turn it red?

The baseline exists so the gate is not red on every push. The risk that
creates is the opposite one: a baseline broad enough to swallow the next
advisory too. These tests pin the line between the two.

Standard library only; no network, no composer.
"""
import json
import os

import audit_baseline as ab
import pytest

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BASELINE = os.path.join(REPO, "docker", "ci", "composer-audit-baseline.json")


def _adv(pkg, cve=None, pksa="PKSA-aaaa-bbbb-cccc", severity="high"):
    return {"packageName": pkg, "cve": cve, "advisoryId": pksa,
            "severity": severity, "title": "t", "affectedVersions": "<1.0",
            "link": "https://example.invalid/x"}


def _report(advisories):
    return {"advisories": advisories, "abandoned": {}}


def _baseline(**accepted):
    return {"accepted": accepted}


def test_a_known_advisory_is_not_new():
    rep = _report({"a/b": [_adv("a/b", cve="CVE-1")]})
    new, resolved, moved, problems = ab.compare(
        rep, _baseline(**{"a/b": {"advisories": ["CVE-1"], "why": "reason"}}))
    assert new == [] and resolved == [] and problems == []


def test_a_new_advisory_against_an_accepted_package_is_still_new():
    """The likeliest real event: a package we already carry gets another CVE."""
    rep = _report({"a/b": [_adv("a/b", cve="CVE-1"), _adv("a/b", cve="CVE-2")]})
    new, _, _, _ = ab.compare(rep, _baseline(**{"a/b": {"advisories": ["CVE-1"], "why": "r"}}))
    assert [ab.advisory_id(a) for _, a in new] == ["CVE-2"]


def test_an_advisory_against_an_unknown_package_is_new():
    rep = _report({"c/d": [_adv("c/d", cve="CVE-9")]})
    new, _, _, _ = ab.compare(rep, _baseline(**{"a/b": {"advisories": ["CVE-1"], "why": "r"}}))
    assert len(new) == 1


def test_a_pksa_advisory_still_matches_once_a_cve_is_assigned():
    """Packagist mints a PKSA id first and the CVE lands later.

    Matching on the PKSA id alone would re-fire the whole entry as "new" on
    the day the CVE is assigned — noise that trains people to widen the
    baseline, which is how a real advisory gets missed.
    """
    rep = _report({"a/b": [_adv("a/b", cve="CVE-7", pksa="PKSA-known")]})
    new, _, _, _ = ab.compare(rep, _baseline(**{"a/b": {"advisories": ["PKSA-known"], "why": "r"}}))
    assert new == []


def test_advisories_delivered_as_an_object_are_not_dropped():
    """composer emits an object rather than an array for some packages.

    phpunit does this today. Reading only the array form makes those packages
    report clean, which in a security gate is the one bug that must not exist.
    """
    rep = _report({"a/b": {"2": _adv("a/b", cve="CVE-3")}})
    new, _, _, _ = ab.compare(rep, _baseline())
    assert [ab.advisory_id(a) for _, a in new] == ["CVE-3"]


def test_an_acceptance_with_no_reason_fails_the_gate():
    """--update writes the ids and leaves 'why' blank on purpose."""
    rep = _report({"a/b": [_adv("a/b", cve="CVE-1")]})
    _, _, _, problems = ab.compare(rep, _baseline(**{"a/b": {"advisories": ["CVE-1"], "why": ""}}))
    assert any("no 'why'" in p for p in problems)


def test_an_advisory_that_went_away_is_reported_for_removal():
    rep = _report({})
    _, resolved, _, problems = ab.compare(
        rep, _baseline(**{"a/b": {"advisories": ["CVE-1"], "why": "r"}}))
    assert resolved == [("a/b", "CVE-1")]
    assert problems == []  # cleanup, not a failure


def test_a_version_change_under_an_acceptance_is_reported():
    rep = _report({"a/b": [_adv("a/b", cve="CVE-1")]})
    _, _, moved, _ = ab.compare(
        rep,
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        installed={"a/b": "1.1"})
    assert moved == [("a/b", "1.0", "1.1")]


def test_a_malformed_report_raises_rather_than_passing():
    with pytest.raises(ab.AuditError):
        ab.compare({"advisories": {"a/b": "not-a-list"}}, _baseline())


def test_update_keeps_reasons_already_written():
    rep = _report({"a/b": [_adv("a/b", cve="CVE-1"), _adv("a/b", cve="CVE-2")]})
    lock = {"packages": [{"name": "a/b", "version": "1.2.3"}]}
    built = ab.build_baseline(rep, lock, {"accepted": {"a/b": {"why": "keep me"}}})
    assert built["accepted"]["a/b"]["why"] == "keep me"
    assert built["accepted"]["a/b"]["installed"] == "1.2.3"
    assert "CVE-2" in built["accepted"]["a/b"]["advisories"]


def test_render_does_not_crash_on_null_fields():
    """Packagist emits ``null`` for unpopulated fields (severity, title,
    affectedVersions).  The default of ``.get(key, default)`` is the value
    returned only when the key is **absent**; when the key is present with a
    ``None`` value the default is ignored, and the subsequent format spec or
    slice raises ``TypeError``.  This is exactly the bug that crashed the gate
    in production.
    """
    adv = {"packageName": "x/y", "advisoryId": "PKSA-1", "cve": "CVE-1",
           "severity": None, "title": None, "affectedVersions": None, "link": None}
    lines = ab.render([("x/y", adv)], [], [], [])
    joined = "\n".join(lines)
    assert "?" in joined            # severity fell back
    assert "CVE-1" in joined        # advisory still identified
    assert "x/y" in joined


# ─── The committed baseline ─────────────────────────────────────────

def test_the_committed_baseline_is_complete():
    """Every accepted package carries a reason, and the file says when it was reviewed."""
    data = json.load(open(BASELINE, encoding="utf-8"))
    assert data.get("reviewed"), "the baseline must record when a human last looked"
    assert data["accepted"], "an empty baseline would accept nothing and fail on everything"
    for pkg, entry in data["accepted"].items():
        assert entry.get("why"), f"{pkg} is accepted with no stated reason"
        assert entry.get("advisories"), f"{pkg} lists no advisory ids"


def test_the_fixable_ones_stay_marked():
    """These are fixable by re-vendoring, and the runbook reads this file.

    If an upgrade drops one, delete the entry — do not quietly drop the marker
    while still carrying the vulnerable version.

    It was four before the 1.43.9 / 5.1.9 upgrade. That upgrade closed three:
    phpoffice/phpspreadsheet (1.30.1 -> 1.30.6), phpseclib/phpseclib
    (3.0.48 -> 3.0.56) and universal-omega/dynamic-page-list3
    (3.6.2.1+BlueSpice511 -> 3.6.4). mediawiki/maps is the one that survived,
    and it cannot be fixed inside the 5.1 series at all: CVE-2026-52854 is
    fixed in 12.1.3 and the BlueSpice pro distribution constrains the package
    to 11.0.*, so only a series bump relaxes it.

    DEPS-02 (2026-09-23) added mediawiki/semantic-media-wiki: 8 advisories
    against vendored 6.0.1, the minimum fix floor 7.3.0, blocked the same way
    — a distribution pin (6.0.*) plus a param-processor version conflict that
    only a BlueSpice series bump resolves. Same class of entry as
    mediawiki/maps, same reason it stays marked rather than silently carried.
    """
    data = json.load(open(BASELINE, encoding="utf-8"))
    flagged = {p for p, e in data["accepted"].items() if "ACTION REQUIRED" in e["why"]}
    assert flagged == {"mediawiki/maps", "mediawiki/semantic-media-wiki"}


MAPS_PATCHES = ("maps-layercontrol-xss-php", "maps-layercontrol-xss-js")


def test_the_maps_mitigation_the_baseline_claims_actually_exists():
    """The mediawiki/maps entry says CVE-2026-52854 is mitigated by two patches.

    An acceptance that points at a mitigation is only as good as the mitigation.
    Deleting the patches while the entry still claims them would leave the one
    high in this file silently unmitigated, and `composer audit` cannot notice:
    it reads the installed version, which is 11.0.1 either way.

    So: if the "why" names the patches, the sidecars must be on disk. Retire
    them together — when Maps reaches 12.1.3 the entry goes and so do they.
    """
    why = json.load(open(BASELINE, encoding="utf-8"))["accepted"]["mediawiki/maps"]["why"]
    patch_dir = os.path.join(REPO, "docker", "patches")
    for patch_id in MAPS_PATCHES:
        if patch_id not in why:
            continue
        for suffix in (".yaml", ".patch"):
            path = os.path.join(patch_dir, patch_id + suffix)
            assert os.path.exists(path), (
                f"the baseline's mediawiki/maps entry names {patch_id}, "
                f"but {path} is missing"
            )


# ─── The exit code, which is the part CI reads ──────────────────────
# compare() found `moved` and render() printed it, but main() returned 1 only
# on `new or problems` — so a package whose installed version changed under an
# existing acceptance scrolled past as a line in a *passing* log. These call
# main() rather than compare(), because the exit code is the only part of this
# script anything downstream acts on.


def _run_main(tmp_path, report, baseline, lock_versions):
    (tmp_path / "audit.json").write_text(json.dumps(report))
    (tmp_path / "baseline.json").write_text(json.dumps(baseline))
    (tmp_path / "composer.lock").write_text(json.dumps(
        {"packages": [{"name": n, "version": v} for n, v in lock_versions.items()],
         "packages-dev": []}))
    return ab.main([str(tmp_path / "audit.json"), str(tmp_path / "baseline.json"),
                    str(tmp_path / "composer.lock")])


def test_a_fully_accepted_tree_exits_zero(tmp_path):
    rc = _run_main(
        tmp_path,
        _report({"a/b": [_adv("a/b", cve="CVE-1")]}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        {"a/b": "1.0"})
    assert rc == 0


def test_a_version_move_under_an_acceptance_fails_the_gate(tmp_path):
    """The acceptances reason about a specific version.

    guzzle's `why` is a 400-word argument about 7.12.3 — which call sites exist
    in this tree, that core substitutes its own CookieJar, that
    $wgAllowCopyUploads is off. At a different version that is an assertion, not
    an assessment, which makes a move the same class as a blank `why`.
    """
    rc = _run_main(
        tmp_path,
        _report({"a/b": [_adv("a/b", cve="CVE-1")]}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        {"a/b": "1.1"})
    assert rc == 1


def test_a_new_advisory_still_fails_the_gate(tmp_path):
    rc = _run_main(
        tmp_path,
        _report({"a/b": [_adv("a/b", cve="CVE-1"), _adv("a/b", cve="CVE-2")]}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        {"a/b": "1.0"})
    assert rc == 1


def test_a_blank_why_still_fails_the_gate(tmp_path):
    rc = _run_main(
        tmp_path,
        _report({"a/b": [_adv("a/b", cve="CVE-1")]}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": ""}}),
        {"a/b": "1.0"})
    assert rc == 1


def test_a_resolved_advisory_alone_does_not_fail_the_gate(tmp_path):
    """Cleanup, not news — dropping a stale baseline entry is not urgent."""
    rc = _run_main(
        tmp_path, _report({}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        {"a/b": "1.0"})
    assert rc == 0


def test_a_move_is_annotated_for_github(tmp_path, capsys, monkeypatch):
    monkeypatch.setenv("GITHUB_ACTIONS", "true")
    _run_main(
        tmp_path,
        _report({"a/b": [_adv("a/b", cve="CVE-1")]}),
        _baseline(**{"a/b": {"advisories": ["CVE-1"], "installed": "1.0", "why": "r"}}),
        {"a/b": "1.1"})
    assert "::error title=Accepted advisory moved version::" in capsys.readouterr().out


def test_the_committed_baseline_passes_against_its_own_versions():
    """The real baseline against app/composer.lock — this must not go red."""
    baseline = json.load(open(BASELINE, encoding="utf-8"))
    lock = json.load(open(os.path.join(REPO, "app", "composer.lock"), encoding="utf-8"))
    installed = {p["name"]: p["version"]
                 for p in lock.get("packages", []) + lock.get("packages-dev", [])}
    stale = [(pkg, e["installed"], installed.get(pkg))
             for pkg, e in baseline["accepted"].items()
             if e.get("installed") and installed.get(pkg)
             and installed[pkg] != e["installed"]]
    assert stale == [], f"baseline 'installed' has drifted from composer.lock: {stale}"
