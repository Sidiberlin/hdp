"""Shared test fixtures and helpers.

Import paths are configured in pytest.ini (`pythonpath`), not here — this file
deliberately does not touch sys.path, so there is exactly one place that
decides how the code under test becomes importable.
"""
import shutil

import pytest

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
