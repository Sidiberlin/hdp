"""Shared test fixtures and helpers.

Import paths are configured in pytest.ini (`pythonpath`), not here — this file
deliberately does not touch sys.path, so there is exactly one place that
decides how the code under test becomes importable.
"""
import json
import os
import re
import shutil
import uuid

import pytest

REGEN_ENV = "HDP_REGEN_GOLDEN"

# Matches the canonical 8-4-4-4-12 hex form. Deliberately not anchored to any
# particular key: the normaliser walks the whole structure, so a UUID added
# somewhere new is still validated rather than silently passing through.
UUID_RE = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$")
UUID_SENTINEL = "<uuid4>"

REPO_ROOT_MARKER = "docker-compose.yml"


@pytest.fixture(scope="session")
def repo_root():
    """Absolute path to the repository root.

    Derived from this file's location rather than the working directory, so
    the tests give the same answer whether they were started from the repo
    root, from tests/, or from inside a container with the tree bind-mounted
    somewhere else entirely.
    """
    from pathlib import Path

    here = Path(__file__).resolve().parent
    for candidate in (here, *here.parents):
        if (candidate / REPO_ROOT_MARKER).is_file():
            return candidate
    raise RuntimeError(
        f"Could not locate the repo root: no {REPO_ROOT_MARKER} in {here} or any parent."
    )


@pytest.fixture(scope="session")
def pipeline_yaml(repo_root):
    """The real hdp_pipeline.yaml, as text.

    Tests that assert on rendering use the file that actually ships rather than
    a synthetic fixture, because the failure this guards against is the real
    file drifting away from what render_pipeline.py expects to find in it.
    """
    return (repo_root / "docker" / "haystack" / "hdp_pipeline.yaml").read_text()


def have_envsubst() -> bool:
    """Whether `envsubst` is on PATH.

    load_pipeline() shells out to it, so its absence is a missing test
    dependency, not a reason to pass. scripts/ci/pytest.sh is responsible for
    providing it; this exists so the failure names itself instead of surfacing
    as a bare FileNotFoundError from subprocess.
    """
    return shutil.which("envsubst") is not None


def normalise_uuids(value):
    """Replace every UUID4 string with a sentinel, asserting it is really one.

    build_result_from_haystack mints result_id and query_id with uuid.uuid4(),
    so its output cannot be compared byte-for-byte against a checked-in file.

    The alternative — monkeypatching uuid.uuid4 — would make the comparison
    trivial but would stop testing the real call. If someone replaced uuid4()
    with a counter, or with uuid1() (which encodes the host MAC address and the
    time, and would leak both to every chat client), a monkeypatched test would
    stay green. This validates the value and then elides it.
    """
    if isinstance(value, dict):
        return {k: normalise_uuids(v) for k, v in value.items()}
    if isinstance(value, list):
        return [normalise_uuids(v) for v in value]
    if isinstance(value, str) and UUID_RE.match(value):
        parsed = uuid.UUID(value)
        assert parsed.version == 4, (
            f"{value!r} is UUID version {parsed.version}, not 4. uuid1 encodes the "
            "host MAC address and the time; these ids are sent to every chat client."
        )
        return UUID_SENTINEL
    return value


@pytest.fixture
def assert_golden(request):
    """Compare a produced structure against a checked-in golden JSON file.

    Set HDP_REGEN_GOLDEN=1 (or run `scripts/ci/pytest.sh --regen-golden`) to
    rewrite the files from the current implementation instead of comparing.
    Regeneration is never automatic and never runs in CI — a golden file that
    updates itself records whatever the code does today, which is the opposite
    of what it is for.
    """
    fixtures = request.path.parent / "fixtures" / request.path.stem.replace("test_", "")

    def _assert(name, produced):
        path = fixtures / f"{name}.json"
        normalised = normalise_uuids(produced)
        rendered = json.dumps(normalised, indent=2, ensure_ascii=False, sort_keys=True) + "\n"

        if os.environ.get(REGEN_ENV):
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(rendered)
            pytest.skip(f"regenerated {path.relative_to(request.config.rootpath)}")

        assert path.is_file(), (
            f"missing golden file {path.relative_to(request.config.rootpath)} — "
            f"create it with: scripts/ci/pytest.sh --regen-golden"
        )
        assert json.loads(rendered) == json.loads(path.read_text()), (
            f"output no longer matches {path.relative_to(request.config.rootpath)}. "
            f"If the change is intended, regenerate with "
            f"`scripts/ci/pytest.sh --regen-golden` and review the diff."
        )
        return normalised

    return _assert
