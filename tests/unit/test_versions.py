"""The version-consistency gate — does it actually go red?

A gate that only ever runs against a consistent tree proves nothing: it would
pass just as happily with the comparison deleted. Every check in
scripts/lib/versions.py is exercised here against a tree fact that disagrees
with the declaration, so removing any one of them turns a test red.

Standard library only, no repo state beyond VERSIONS.yml itself.
"""
import os

import pytest
import versions

# scripts/lib is on pytest.ini's pythonpath, which is what makes the bare
# `import versions` above work from any working directory.
REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


# ─── The YAML subset reader ─────────────────────────────────────────

def test_parses_nested_mappings_comments_and_quotes():
    doc = versions.parse_minimal("""
# a comment
mw_core: '1.43.5'
php: 8.3
exceptions:
  bluespice/package-wikifarm: '5.1.5'   # trailing comment
frozen:
  hallowelt/chatbot:
    owner: 'unassigned'
    last_reviewed: '2026-08-03'
empty_value: ~
""")
    assert doc["mw_core"] == "1.43.5"
    assert doc["php"] == "8.3"
    assert doc["exceptions"] == {"bluespice/package-wikifarm": "5.1.5"}
    assert doc["frozen"]["hallowelt/chatbot"]["owner"] == "unassigned"
    assert doc["empty_value"] is None


def test_a_hash_inside_a_quoted_scalar_is_not_a_comment():
    doc = versions.parse_minimal("ref: 'abc#def'")
    assert doc["ref"] == "abc#def"


def test_folded_blocks_fold_the_way_pyyaml_folds_them():
    doc = versions.parse_minimal("why: >-\n  one two\n  three\nnext: 'x'\n")
    assert doc == {"why": "one two three", "next": "x"}


@pytest.mark.parametrize("bad", [
    "items:\n  - one\n",            # lists
    "a:\n\tb: 1\n",                 # tab indentation
    "a:\n   b: 1\n",                # not a multiple of two
    "a: {b: 1}\n",                  # flow mapping
    "a: [1, 2]\n",                  # flow sequence
    "a: |\n  literal\n",            # literal block: PyYAML keeps the newline
    "novalue\n",                    # not a key: value line
    "a: 1\n  b: 2\n",               # indented past what any open mapping allows
    "a:\n  b: 1\n    c: 2\n",       # indented two levels at once
])
def test_refuses_what_it_cannot_represent(bad):
    """The fallback raises rather than guessing.

    A version file parsed wrongly is worse than one that fails to parse: it
    would report agreement that was never checked.
    """
    with pytest.raises(versions.VersionsError):
        versions.parse_minimal(bad)


def test_both_backends_read_the_real_file_identically():
    """PyYAML and the fallback must not disagree about VERSIONS.yml.

    This is the whole risk of carrying a second parser. `php: 8.3` is the
    concrete trap — PyYAML types it as a float and the fallback as a string,
    so without normalisation the comparison against an image tag would pass on
    one backend and fail on the other.
    """
    yaml = pytest.importorskip("yaml")
    path = os.path.join(REPO, "VERSIONS.yml")
    text = open(path, encoding="utf-8").read()
    assert versions._stringify(yaml.safe_load(text)) == versions.parse_minimal(text, path)


def test_the_committed_file_declares_everything_the_gate_needs():
    decl = versions.load_versions(os.path.join(REPO, "VERSIONS.yml"))
    for key in ("mw_core", "bluespice", "php", "mariadb", "opensearch", "haystack", "python"):
        assert decl.get(key), f"VERSIONS.yml is missing '{key}'"
    assert decl["frozen"], "the two Track C packages must stay declared — see SECURITY.md"
    assert decl["extensions"], "the extension inventory must not be empty"


# ─── The comparison ─────────────────────────────────────────────────

def _decl(**over):
    base = {
        "mw_core": "1.43.5",
        "bluespice": "5.1.4",
        "php": "8.3",
        "mariadb": "10.11",
        "opensearch": "2.18.0",
        "haystack": "2.15.0",
        "python": "3.11",
        "exceptions": {"bluespice/package-wikifarm": "5.1.5", "BlueSpiceWikiFarm": "5.1.5"},
        "frozen": {"hallowelt/chatbot": {
            "vendored_from": "gitlab.hallowelt.com/x@abc",
            "owner": "unassigned",
            "last_reviewed": "2026-08-03",
            "why": "private GitLab",
        }},
        "extensions": {"PluggableAuth": "7.5.0"},
    }
    base.update(over)
    return base


def _obs(**over):
    base = {
        "mw_core": "1.43.5",
        "composer": {"bluespice/about": "5.1.4",
                     "bluespice/package-wikifarm": "5.1.5",
                     "hallowelt/chatbot": "dev-main"},
        "extensions": {"BlueSpiceAbout": "5.1.4", "BlueSpiceWikiFarm": "5.1.5",
                       "PluggableAuth": "7.5.0"},
        "images": ["mariadb:10.11", "hdp-opensearch:2.18.0",
                   "docker-registry.wikimedia.org/dev/bookworm-php83-fpm:1.0.0"],
        "opensearch_base": "2.18.0",
        "haystack": "2.15.0",
        "python": "3.11",
        "publiccode": "5.1.4",
        "stripped": ["hallowelt/chatbot"],
    }
    base.update(over)
    return base


def _run(decl, obs):
    return versions.check(decl, obs)


def test_a_consistent_pair_passes():
    rep = _run(_decl(), _obs())
    assert rep.failures == [], rep.lines
    assert rep.warnings == []


@pytest.mark.parametrize("obs_override, expect", [
    ({"mw_core": "1.43.9"}, "mw_core"),
    ({"publiccode": "5.1.3"}, "publiccode.yml"),
    ({"opensearch_base": "2.19.0"}, "opensearch"),
    ({"haystack": "2.16.0"}, "haystack"),
    ({"python": "3.12"}, "python"),
    ({"images": ["mariadb:11.4", "hdp-opensearch:2.18.0",
                 "docker-registry.wikimedia.org/dev/bookworm-php83-fpm:1.0.0"]}, "mariadb"),
    ({"images": ["mariadb:10.11", "hdp-opensearch:2.18.0",
                 "docker-registry.wikimedia.org/dev/bookworm-php84-fpm:1.0.0"]}, "php"),
])
def test_every_declared_version_is_actually_compared(obs_override, expect):
    rep = _run(_decl(), _obs(**obs_override))
    assert any(expect in f for f in rep.failures), rep.lines


def test_a_bluespice_package_off_the_baseline_is_drift():
    obs = _obs(composer={"bluespice/about": "5.1.6", "hallowelt/chatbot": "dev-main"})
    rep = _run(_decl(), obs)
    assert any("bluespice/*" in f for f in rep.failures)


def test_a_declared_exception_is_not_drift():
    """5.1.5 on package-wikifarm is upstream's doing, and it is declared."""
    rep = _run(_decl(), _obs())
    assert not any("wikifarm" in f.lower() for f in rep.failures)


def test_a_blue_spice_extension_off_the_baseline_is_drift():
    obs = _obs(extensions={"BlueSpiceAbout": "5.1.6", "BlueSpiceWikiFarm": "5.1.5",
                           "PluggableAuth": "7.5.0"})
    rep = _run(_decl(), obs)
    assert any("baseline" in f for f in rep.failures)


def test_an_undeclared_extension_is_drift():
    """A new extension appearing in the tree is exactly the silent change."""
    obs = _obs(extensions=dict(_obs()["extensions"], SomethingNew="1.0.0"))
    rep = _run(_decl(), obs)
    assert any("not declared" in f for f in rep.failures)


def test_a_declared_extension_that_vanished_is_drift():
    obs = _obs(extensions={"BlueSpiceAbout": "5.1.4", "BlueSpiceWikiFarm": "5.1.5"})
    rep = _run(_decl(), obs)
    assert any("not installed" in f for f in rep.failures)


def test_a_changed_extension_version_is_drift():
    obs = _obs(extensions=dict(_obs()["extensions"], PluggableAuth="7.6.0"))
    rep = _run(_decl(), obs)
    assert any("differ" in f for f in rep.failures)


# ─── Track C ────────────────────────────────────────────────────────

def test_a_frozen_package_setup_no_longer_strips_is_drift():
    """The declaration has to stay tied to the code that implements it.

    Leaving a frozen package in composer.lock makes `composer install` reach a
    private GitLab, which fails the whole install.
    """
    rep = _run(_decl(), _obs(stripped=[]))
    assert any("does not strip it" in f for f in rep.failures)


def test_a_frozen_package_missing_from_the_lockfile_is_drift():
    rep = _run(_decl(), _obs(composer={"bluespice/about": "5.1.4"}))
    assert any("absent from app/composer.lock" in f for f in rep.failures)


def test_an_incomplete_frozen_entry_is_drift():
    decl = _decl(frozen={"hallowelt/chatbot": {"owner": "unassigned"}})
    rep = _run(decl, _obs())
    assert sum("missing" in f for f in rep.failures) == 3  # vendored_from, last_reviewed, why


def test_a_stale_review_date_warns_and_does_not_fail():
    """Deliberate: a date passing in the night is not a code defect.

    A gate that fails for a reason nobody can fix by editing code is a gate
    people learn to bypass, and this one guards the two packages with no other
    signal at all.
    """
    decl = _decl(frozen={"hallowelt/chatbot": {
        "vendored_from": "x", "owner": "y", "why": "z", "last_reviewed": "2001-01-01"}})
    rep = _run(decl, _obs())
    assert rep.failures == []
    assert any("last reviewed" in w for w in rep.warnings)


def test_a_malformed_review_date_is_drift():
    decl = _decl(frozen={"hallowelt/chatbot": {
        "vendored_from": "x", "owner": "y", "why": "z", "last_reviewed": "August 2026"}})
    rep = _run(decl, _obs())
    assert any("YYYY-MM-DD" in f for f in rep.failures)


def test_dropping_the_frozen_section_entirely_is_not_silent():
    rep = _run(_decl(frozen={}), _obs())
    assert any("frozen" in w for w in rep.warnings)
