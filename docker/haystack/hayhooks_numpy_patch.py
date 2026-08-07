# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""Make hayhooks' own /{pipeline}/run endpoint able to serialize its results.

The bug
-------
Haystack's `Document` and `GeneratedAnswer` carry their relevance in a `score`
field, and the retrievers and rankers put a `numpy.float32` there rather than a
Python float. hayhooks hands the pipeline result straight to a Pydantic
response model, and Pydantic has no encoder for numpy scalars, so a query that
ran perfectly — embed, retrieve, rank, LLM answer, all of it — dies on the way
out with:

    PydanticSerializationError: Unable to serialize unknown type:
    <class 'numpy.float32'>

Found end to end on a GPU box: the pipeline logs a successful run and the
client gets a 500.

Note which endpoint this is. The chatbot never sees it — chatbot-proxy talks to
hdp_api_server.py on :1417, whose /hdp_pipeline/run already launders its result
through serialization.to_native(). This is hayhooks' *own* endpoint on :1416,
which is what you hit when you query the pipeline directly, and it is the one
docs and manual testing reach for.

Why a source patch
------------------
hayhooks is a third-party package. There is no hook, no setting and no response
encoder to override — the offending call is a closure (`_handle_request`,
created inside `create_run_endpoint_handler`) that Pydantic-wraps the result
inline. Upstream has not fixed it either: hayhooks 1.23.0, the newest release
at the time of writing, contains no numpy handling anywhere in the package, so
bumping the pin buys nothing. That was checked, not assumed.

So the installed `deploy_utils.py` is rewritten at image build time to call
into this module. `patch_source()` below does the rewrite; it is a pure
string function so the fast test tier can cover it without hayhooks installed.

The anchor is deliberately strict
---------------------------------
`patch_source()` matches four exact consecutive lines and raises if it does not
find exactly one occurrence. That is the point, not a limitation. hayhooks
1.23.0 has already reshaped this block (it routes through
`_execute_pipeline_run_with_tracing` and a `traced_result`), so a version bump
in the Dockerfile fails the *build*, loudly, with a diff to look at — rather
than installing a no-op patch and handing the failure back to whoever next
queries the endpoint in production.

Relationship to serialization.to_native()
-----------------------------------------
`to_native()` in serialization.py does the same job for the :1417 API and is
not reused here, for two reasons. It imports `haystack` at module scope, and
this module is imported from inside site-packages during hayhooks startup where
/opt/pipeline is not on sys.path. And it maps `Document` and the `Answer` types
to a fixed, hand-written dict shape that the :1417 response contract and its
tests depend on; hayhooks' endpoint has no such contract, so `sanitize_result()`
converts structurally instead and preserves whatever fields the object had.
Keep the two in step in intent, not in code.
"""
import argparse
import importlib.util
import os
import sys
import tempfile

# The four lines are matched verbatim, including indentation, from
# hayhooks 1.10.0 deploy_utils.py (inside create_run_endpoint_handler's nested
# _handle_request). The `if` and its `return` are part of the anchor so the
# insertion lands *after* the streaming check: sanitizing before it would walk
# a StreamingResponse or an SSEStream and destroy it. Only the JSON path — the
# one that reaches a Pydantic response model — needs laundering.
ANCHOR = """        result = await _execute_pipeline_run(pipeline_wrapper, payload)
        streaming_response = _streaming_response_from_result(result)
        if streaming_response is not None:
            return streaming_response
"""

# Imported inside the function rather than at deploy_utils' module top, so the
# patch is a single contiguous insertion at one anchor instead of two edits at
# two unrelated places. The import is a dict lookup after the first call.
INSERTION = """
        # HDP patch: Haystack puts numpy.float32 in Document.score and
        # GeneratedAnswer.score, and Pydantic cannot serialize those. See
        # hayhooks_numpy_patch.py. Applied at image build time by the Dockerfile.
        from hayhooks_numpy_patch import sanitize_result

        result = sanitize_result(result)
"""

# What patch_source() greps for to decide it has already run. It has to appear
# in INSERTION and nowhere in stock hayhooks, which the module name satisfies.
MARKER = "hayhooks_numpy_patch"

TOP_LEVEL_PACKAGE = "hayhooks"
TARGET_RELPATH = ("server", "utils", "deploy_utils.py")
TARGET_MODULE = "hayhooks.server.utils.deploy_utils"  # for error messages only — see _target_path


class PatchError(RuntimeError):
    """The installed hayhooks does not look like the version this patch targets."""


def sanitize_result(obj):
    """Recursively convert Haystack dataclasses and numpy scalars to JSON-safe types.

    Structural rather than type-directed: it knows about dataclasses, mappings,
    sequences and numpy, not about `Document` or `GeneratedAnswer` specifically.
    That is what lets it sit in site-packages without importing haystack, and it
    means a Haystack release that adds a field or a component that returns some
    other numpy-bearing object is handled without an edit here.

    numpy is imported in the body, not at module scope, so that patch_source()
    above stays importable — and testable — on a bare Python with no numpy.
    """
    import dataclasses

    import numpy as np

    # Dataclasses first, and before the to_dict branch below: Document and the
    # Answer types are all dataclasses, and asdict() recurses into nested ones
    # (GeneratedAnswer.documents) without needing to know they are there.
    # asdict() leaves numpy scalars as numpy scalars, so the comprehension has
    # to recurse rather than return its result directly.
    if dataclasses.is_dataclass(obj) and not isinstance(obj, type):
        return {k: sanitize_result(v) for k, v in dataclasses.asdict(obj).items()}

    # Haystack's non-dataclass component outputs (ChatMessage, ByteStream, the
    # Document store types) all expose to_dict(). Best effort: anything that
    # raises falls through to the generic branches below.
    if not isinstance(obj, type) and callable(getattr(obj, "to_dict", None)):
        try:
            return sanitize_result(obj.to_dict())
        except Exception:  # noqa: BLE001 - any failure here just means "try the next branch"
            pass

    if isinstance(obj, dict):
        return {k: sanitize_result(v) for k, v in obj.items()}

    if isinstance(obj, (list, tuple)):
        return [sanitize_result(v) for v in obj]

    # np.generic covers every numpy scalar in one check — float16/32/64,
    # int8..64, bool_, and the sized aliases — and .item() returns the native
    # Python equivalent. Enumerating the concrete types instead would silently
    # pass through the ones nobody thought to list.
    if isinstance(obj, np.generic):
        return obj.item()

    # tolist() has already converted the scalars; recurse anyway, because an
    # object-dtype array's elements are arbitrary Python objects.
    if isinstance(obj, np.ndarray):
        return [sanitize_result(v) for v in obj.tolist()]

    # Last resort for plain classes. str/int/float/bool/None are excluded
    # because __dict__ on them is not what a caller means; without the guard a
    # bare string would come back as a dict.
    if hasattr(obj, "__dict__") and not isinstance(obj, (str, int, float, bool, type(None))):
        return sanitize_result(vars(obj))

    return obj


def patch_source(src: str) -> str:
    """Return `src` with the sanitize call inserted, or unchanged if already patched.

    Pure string in, string out — no filesystem, no imports of the thing being
    patched. Raises PatchError if the anchor is missing or appears more than
    once, which is how a hayhooks version bump gets caught.
    """
    if MARKER in src:
        return src

    found = src.count(ANCHOR)
    if found != 1:
        raise PatchError(
            f"expected exactly 1 occurrence of the hayhooks run-handler anchor, found {found}. "
            f"This patch targets hayhooks 1.10.0; if the pin in docker/haystack/Dockerfile "
            f"moved, re-derive ANCHOR in docker/haystack/hayhooks_numpy_patch.py against the "
            f"new {TARGET_MODULE} and re-check that the insertion still lands after the "
            f"streaming-response check."
        )

    return src.replace(ANCHOR, ANCHOR + INSERTION, 1)


def _target_path() -> str:
    """Absolute path of the installed deploy_utils.py, without importing hayhooks.

    Note the shape: find_spec on the *top-level* package, then the rest of the
    path joined by hand. find_spec("hayhooks.server.utils.deploy_utils") would
    read better and is wrong — a dotted name makes it import every parent
    package to find the child, and hayhooks/__init__.py pulls in create_app and
    with it the whole FastAPI, Haystack and pipeline-wrapper graph.

    That is not a theoretical objection. A build probe with hayhooks installed
    but haystack-ai not yet resolved died here on `cannot import name
    'AsyncPipeline' from 'haystack'` — the patcher failing over a dependency it
    has no business touching, while the file it wanted to edit sat right there
    on disk. Locating a file should not require the program to be runnable.
    """
    spec = importlib.util.find_spec(TOP_LEVEL_PACKAGE)
    if spec is None or not spec.submodule_search_locations:
        raise PatchError(f"cannot locate the {TOP_LEVEL_PACKAGE} package — is hayhooks installed?")

    path = os.path.join(spec.submodule_search_locations[0], *TARGET_RELPATH)
    if not os.path.isfile(path):
        raise PatchError(
            f"{TOP_LEVEL_PACKAGE} is installed but {path} does not exist. "
            f"This patch targets hayhooks 1.10.0; check the pin in docker/haystack/Dockerfile."
        )
    return path


def apply(path: str | None = None) -> bool:
    """Patch the installed deploy_utils.py in place. Returns True if it wrote.

    Writes via a temp file in the same directory and os.replace, so an
    interrupted run cannot leave hayhooks with a half-written source file.
    """
    path = path or _target_path()

    with open(path, encoding="utf-8") as f:
        original = f.read()

    patched = patch_source(original)
    if patched == original:
        return False

    # Compile before writing, not after. A patched file that does not parse
    # would take hayhooks down at import with a SyntaxError pointing into
    # site-packages, which is a miserable thing to debug from a container log.
    compile(patched, path, "exec")

    directory = os.path.dirname(path)
    fd, tmp = tempfile.mkstemp(dir=directory, prefix=".deploy_utils-", suffix=".py")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            f.write(patched)
        os.replace(tmp, path)
    except BaseException:
        if os.path.exists(tmp):
            os.unlink(tmp)
        raise

    return True


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument(
        "--path",
        help="deploy_utils.py to patch. Defaults to the installed hayhooks copy.",
    )
    args = parser.parse_args(argv)

    try:
        wrote = apply(args.path)
    except PatchError as e:
        print(f"ERROR: hayhooks numpy patch not applied: {e}", file=sys.stderr)
        return 1

    target = args.path or _target_path()
    print(f"hayhooks numpy patch: {'applied to' if wrote else 'already present in'} {target}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
