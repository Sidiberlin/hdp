"""HDP RAG API — numpy-safe wrapper around Haystack pipeline."""
import os, logging, subprocess, asyncio
import numpy as np
from pathlib import Path
from haystack import Pipeline

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("hdp-api")

PIPELINE_FILE = os.path.join(
    os.environ.get("PIPELINE_DIR", "/opt/pipeline"),
    "hdp_pipeline.yaml",
)

rendered = subprocess.run(
    ["envsubst"], input=Path(PIPELINE_FILE).read_text(),
    capture_output=True, text=True,
).stdout

pipeline = None
try:
    pipeline = Pipeline.loads(rendered)
    log.info("Pipeline loaded")
except Exception as e:
    pipeline_error = str(e)
    log.warning("Pipeline could not be loaded — likely missing HDP_LLM_API_KEY.")
    log.warning(f"  Error: {pipeline_error}")
    log.warning("  The API server will start but return 503 on all queries until configured.")
    log.warning("  Set HDP_LLM_API_KEY in .env or Infisical and restart the haystack container.")

def to_native(obj):
    """Recursively convert numpy/Haystack types to JSON-safe native types."""
    from haystack import Answer, GeneratedAnswer, ExtractedAnswer, Document
    
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

from fastapi import FastAPI
from fastapi.responses import JSONResponse
from pydantic import BaseModel

app = FastAPI(title="HDP RAG API")

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
    if pipeline is None:
        return JSONResponse(
            content={"status": "degraded", "error": "HDP_LLM_API_KEY not configured"},
            status_code=503,
        )
    return {"status": "ok"}

if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("HDP_API_PORT", "1417"))
    uvicorn.run(app, host="0.0.0.0", port=port)
