"""JSON-safe serialization and pipeline loading, extracted from hdp_api_server.py.

Both functions here were previously unreachable from a test. hdp_api_server.py
runs `subprocess.run(["envsubst"])` and `Pipeline.loads()` at module scope, so
importing it shells out and tries to build a live RAG pipeline. Nothing in that
file could be tested without first standing up the container it belongs to.

This module has no module-level side effects. Importing it does nothing but
define two functions.

Extracted in Wave 2 with no behaviour change. The one deliberate edit is that
to_native's `from haystack import ...` moved from inside the function body to
module scope: the lazy import bought nothing (hdp_api_server.py already
imported haystack at module level), and this module is the haystack-dependent
one by definition.
"""
import logging
import subprocess
from pathlib import Path

import numpy as np
from haystack import Answer, Document, ExtractedAnswer, GeneratedAnswer, Pipeline

log = logging.getLogger("hdp-api")


def to_native(obj):
    """Recursively convert numpy/Haystack types to JSON-safe native types."""
    # Handle Haystack Document objects
    if isinstance(obj, Document):
        return {
            "content": obj.content,
            "meta": to_native(obj.meta) if obj.meta else {},
            "id": obj.id,
            "score": float(obj.score) if obj.score is not None else None,
        }

    # Handle Haystack Answer objects
    if isinstance(obj, (GeneratedAnswer, ExtractedAnswer, Answer)):
        return {
            "answer": obj.data if hasattr(obj, "data") else str(obj),
            "query": obj.query if hasattr(obj, "query") else None,
            "meta": to_native(obj.meta) if hasattr(obj, "meta") else {},
            "documents": to_native(obj.documents) if hasattr(obj, "documents") else [],
            "score": float(obj.score) if hasattr(obj, "score") and obj.score is not None else None,
        }

    # Handle dicts
    if isinstance(obj, dict):
        return {k: to_native(v) for k, v in obj.items()}

    # Handle lists/tuples
    if isinstance(obj, (list, tuple)):
        return [to_native(v) for v in obj]

    # Handle numpy types
    if isinstance(obj, (np.integer,)):
        return int(obj)
    if isinstance(obj, (np.floating,)):
        return float(obj)
    if isinstance(obj, np.ndarray):
        return obj.tolist()

    return obj


def load_pipeline(pipeline_file):
    """Render the pipeline YAML through envsubst and deserialize it.

    Returns (pipeline, error): exactly one of the two is None.

    The envsubst call is deliberately outside the try, which is how this has
    always behaved. The asymmetry is intentional and worth stating:

      * a pipeline that renders but will not deserialize is a *configuration*
        problem — almost always an unset HDP_LLM_API_KEY, since the
        OpenAIGenerator cannot be constructed without one. That degrades: the
        caller keeps the error, /health stays 200 and /ready returns 503. Wave
        0 fixed the opposite behaviour, where this took the whole container
        permanently unhealthy and made the documented .env-only dev path
        unusable.

      * a pipeline file that cannot be read, or an image with no envsubst, is a
        *broken install*. There is no degraded mode worth offering for it, and
        raising here surfaces it at container start rather than as a confusing
        503 on every query.
    """
    # check=False is subprocess.run's default; it is written out because
    # ruff's PLW1510 is selected and because the choice is deliberate. If
    # envsubst itself fails, stdout is empty and Pipeline.loads below produces
    # the error this function returns — the caller inspects the result rather
    # than catching an exception from here.
    rendered = subprocess.run(
        ["envsubst"], input=Path(pipeline_file).read_text(),
        capture_output=True, text=True, check=False,
    ).stdout

    try:
        pipeline = Pipeline.loads(rendered)
        log.info("Pipeline loaded")
        return pipeline, None
    except Exception as e:
        pipeline_error = str(e)
        log.warning("Pipeline could not be loaded — likely missing HDP_LLM_API_KEY.")
        log.warning(f"  Error: {pipeline_error}")
        log.warning("  The API server will start but return 503 on all queries until configured.")
        log.warning("  Set HDP_LLM_API_KEY in .env or Infisical and restart the haystack container.")
        return None, pipeline_error
