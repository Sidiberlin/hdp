"""Tests for patch_source() in docker/haystack/hayhooks_numpy_patch.py.

This is the half of the patch that runs during `docker build` and edits an
installed third-party file. Getting it wrong has two failure shapes and they
want opposite handling:

  * it does not match      -> the build must fail, loudly, so someone looks at
                              the hayhooks diff. Silently shipping an unpatched
                              image reproduces the original 500.
  * it matches wrongly     -> the build must fail too, because a file written
                              into site-packages that does not parse takes
                              hayhooks down at import.

So the tests below are mostly about refusing to write, not about writing.

Standard library only. sanitize_result() needs numpy and Haystack objects to
mean anything, so it is covered separately in tests/haystack/test_hayhooks_sanitize.py
— the module keeps its numpy import inside that function precisely so this tier
can import it at all.
"""
import ast
import importlib.util

import pytest
from hayhooks_numpy_patch import ANCHOR, INSERTION, MARKER, PatchError, apply, patch_source

# A stand-in for deploy_utils.py: the real anchor lines in a syntactically
# valid module, with something before and after so the tests can prove the
# insertion is local. The real file is exercised too — see the last test.
SYNTHETIC = f'''\
async def _execute_pipeline_run(pipeline_wrapper, payload):
    return {{}}


def create_run_endpoint_handler(pipeline_wrapper, request_model):
    async def _handle_request(run_req):
        payload = run_req.model_dump()

{ANCHOR}
        return response_model(result=result)

    return _handle_request
'''


def _handle_request_body(src: str) -> str:
    """The lines of _handle_request, so a test can assert on ordering inside it."""
    return src.split("async def _handle_request")[1]


# ─── inserting ──────────────────────────────────────────────────────────

def test_insertion_lands_in_the_run_handler():
    patched = patch_source(SYNTHETIC)

    assert MARKER in patched
    assert "sanitize_result(result)" in _handle_request_body(patched)


def test_the_result_still_parses():
    # The build compiles before writing; this catches a broken INSERTION here,
    # where the failure names the constant instead of a path in site-packages.
    ast.parse(patch_source(SYNTHETIC))


def test_insertion_lands_after_the_streaming_check():
    # The one ordering that matters. _streaming_response_from_result() returns
    # a StreamingResponse or an SSEStream for streaming pipelines, and
    # sanitize_result() would walk it and hand back a dict of its internals.
    # Sanitizing only after that check is why the `if` is part of the anchor.
    body = _handle_request_body(patch_source(SYNTHETIC))

    assert body.index("return streaming_response") < body.index("sanitize_result(result)")


def test_the_rewrite_is_purely_additive():
    # Taking the insertion back out has to reproduce the input byte for byte.
    # This is what rules out the class of bug where a future edit to ANCHOR or
    # INSERTION quietly drops or rewrites a line of hayhooks' own code.
    patched = patch_source(SYNTHETIC)

    assert patched.replace(INSERTION, "") == SYNTHETIC


# ─── refusing to insert ─────────────────────────────────────────────────

def test_already_patched_is_a_no_op():
    once = patch_source(SYNTHETIC)

    assert patch_source(once) == once


def test_missing_anchor_raises():
    with pytest.raises(PatchError) as excinfo:
        patch_source("def unrelated():\n    return 1\n")

    # The message has to be actionable from a build log with no other context,
    # so it names the file to re-derive the anchor in.
    assert "found 0" in str(excinfo.value)
    assert "hayhooks_numpy_patch.py" in str(excinfo.value)


def test_duplicated_anchor_raises():
    # Two matches means hayhooks grew a second run handler and patching only
    # the first would leave half the endpoints broken — with nothing in the
    # build log to say so. Refuse instead of guessing.
    with pytest.raises(PatchError) as excinfo:
        patch_source(SYNTHETIC + SYNTHETIC)

    assert "found 2" in str(excinfo.value)


def test_apply_leaves_the_file_alone_when_the_anchor_is_missing(tmp_path):
    target = tmp_path / "deploy_utils.py"
    target.write_text("def unrelated():\n    return 1\n")

    with pytest.raises(PatchError):
        apply(str(target))

    assert target.read_text() == "def unrelated():\n    return 1\n"
    # The atomic write goes through a temp file in the same directory; a failed
    # run must not leave one behind.
    assert [p.name for p in tmp_path.iterdir()] == ["deploy_utils.py"]


# ─── writing ────────────────────────────────────────────────────────────

def test_apply_writes_once_then_reports_no_change(tmp_path):
    target = tmp_path / "deploy_utils.py"
    target.write_text(SYNTHETIC)

    assert apply(str(target)) is True
    first = target.read_text()

    assert apply(str(target)) is False
    assert target.read_text() == first


# ─── against the real thing ─────────────────────────────────────────────

def test_the_installed_hayhooks_still_matches_the_anchor():
    """Runs only where hayhooks is installed — the haystack container.

    The synthetic fixture above cannot catch the failure this patch actually
    has: hayhooks changing the shape of _handle_request. Neither the unit nor
    the haystack tier installs hayhooks (it is a runtime dependency of the
    image, not of the tests), so this skips on a plain checkout and is the
    check that means something when the suite is run with
    `docker compose exec haystack python -m pytest /tests`.
    """
    # find_spec, not importorskip: importing hayhooks executes create_app and
    # the whole server graph, which is both slow and able to fail for reasons
    # that have nothing to do with this file. _target_path() avoids the import
    # for the same reason, so the test's availability check should match it.
    if importlib.util.find_spec("hayhooks") is None:
        pytest.skip("hayhooks is only installed in the haystack image")

    from hayhooks_numpy_patch import _target_path

    with open(_target_path(), encoding="utf-8") as f:
        installed = f.read()

    # Either the image is already patched (the Dockerfile did it at build time)
    # or the anchor is still there to patch. Both are fine; neither being true
    # is the regression.
    assert MARKER in installed or installed.count(ANCHOR) == 1
