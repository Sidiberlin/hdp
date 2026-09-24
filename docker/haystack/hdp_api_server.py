# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""HDP RAG API — numpy-safe wrapper around Haystack pipeline."""
import asyncio
import logging
import os

from fastapi import FastAPI
from fastapi.responses import JSONResponse

# The ingestion API (QoL6) — a router, not inlined here, so the auth
# dependency it carries stays scoped to /v1/ingest/pages and does not touch
# the unauthenticated routes below (see docker/haystack/ingest_api.py's
# module docstring for why a global dependency would break chatbot-proxy).
from ingest_api import router as ingest_router
from pydantic import BaseModel

# Extracted in Wave 2 so both are reachable from a test. Same directory, which
# is how the container runs this file (uvicorn on /opt/pipeline).
from serialization import load_pipeline, to_native

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("hdp-api")

PIPELINE_FILE = os.path.join(
    os.environ.get("PIPELINE_DIR", "/opt/pipeline"),
    "hdp_pipeline.yaml",
)

pipeline, pipeline_error = load_pipeline(PIPELINE_FILE)

app = FastAPI(title="HDP RAG API")
app.include_router(ingest_router)

class QueryRequest(BaseModel):
    question: str
    query: str = ""
    path: str = "rag"

@app.post("/hdp_pipeline/run")
async def run_pipeline(req: QueryRequest):
    if pipeline is None:
        log.warning("Query rejected — pipeline not loaded (HDP_LLM_API_KEY not configured)")
        return JSONResponse(
            content={
                "error": "HDP_LLM_API_KEY not configured. Set it in .env or Infisical and restart.",
                "answers": [],
                "documents": [],
            },
            status_code=503,
        )
    log.info(f"Query: {req.question[:80]} (path={req.path})")
    try:
        # Run pipeline in thread pool to avoid blocking async event loop
        result = await asyncio.to_thread(
            pipeline.run,
            {
                "question": req.question,
                "query": req.query or req.question,
                "path": req.path,
            }
        )
        cleaned = to_native(result)
        # Return JSONResponse directly to bypass Pydantic serialization
        return JSONResponse(content=cleaned, status_code=200)
    except Exception as e:
        log.error(f"Pipeline error: {e}", exc_info=True)
        return JSONResponse(content={"detail": str(e), "answers": [], "documents": []}, status_code=500)

@app.get("/health")
def health():
    """Liveness. 200 whenever this process is up and serving.

    This deliberately does NOT fail when the RAG pipeline is unloaded. It is
    what docker-compose's healthcheck polls, and tying it to pipeline state
    made the whole haystack container permanently `unhealthy` on any install
    without an LLM key — the pipeline cannot deserialize OpenAIGenerator
    without HDP_LLM_API_KEY, so /health 503'd forever, the container never
    went healthy, and `docker compose up --wait` (and any CI gate asserting
    all seven healthchecks green) could never pass without a live, paid API
    key. That made the documented .env-only dev path unusable and contradicted
    the test strategy's decision not to require a live LLM in CI.

    Degradation is still reported, in the body and via /ready — it just no
    longer masquerades as "this container is broken".
    """
    if pipeline is None:
        return {
            "status": "degraded",
            "pipeline_loaded": False,
            "reason": "RAG pipeline not loaded — HDP_LLM_API_KEY is probably unset",
            "detail": pipeline_error,
        }
    return {"status": "ok", "pipeline_loaded": True}


@app.get("/ready")
def ready():
    """Readiness. 503 until the RAG pipeline is actually usable.

    Split out from /health so the two questions stay separate: /health asks
    "is the process alive" (container orchestration), /ready asks "can this
    serve a RAG query" (traffic routing, and the assertion to use when a test
    genuinely requires a working LLM).
    """
    if pipeline is None:
        return JSONResponse(
            content={
                "status": "degraded",
                "pipeline_loaded": False,
                "reason": "RAG pipeline not loaded — HDP_LLM_API_KEY is probably unset",
                "detail": pipeline_error,
            },
            status_code=503,
        )
    return {"status": "ok", "pipeline_loaded": True}

if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("HDP_API_PORT", "1417"))
    uvicorn.run(app, host="0.0.0.0", port=port)
