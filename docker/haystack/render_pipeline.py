#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""
Renders the query-time embedder component in hdp_pipeline.yaml based on
HDP_EMBEDDING_PROVIDER, then writes the result to PIPELINE_OUT (or overwrites
PIPELINE_IN in place if PIPELINE_OUT is not given).

Why this exists: Haystack pipeline YAML declares component TYPES statically.
`${VAR}` substitution (via envsubst) can swap init_parameter VALUES but not
the Python class itself — SentenceTransformersTextEmbedder (local CPU model)
and OpenAITextEmbedder (remote HTTP API) are different classes with different
required init_parameters. This script performs that one class-level swap by
replacing the text between two sentinel comments in the YAML before envsubst
and hayhooks ever see it. Every other line in the file is left byte-identical.

Modes (HDP_EMBEDDING_PROVIDER):
  local   (default) — SentenceTransformersTextEmbedder, runs in-container on
                       CPU (or GPU if HAYSTACK_DEVICE=cuda). Zero extra config.
  remote  — OpenAITextEmbedder pointed at any OpenAI-compatible embeddings
            endpoint (self-hosted TEI, a commercial API, etc.) via
            HDP_EMBEDDING_BASE_URL / HDP_EMBEDDING_MODEL / HDP_EMBEDDING_API_KEY.

  hf_space is intentionally NOT a valid value here. It is supported only by
  the ingestion script (ingest_hdp_wiki.py --provider hf_space) for one-off
  bulk ingestion/testing — HF Spaces cold-start and rate-limit unpredictably,
  which is unacceptable for a live per-query embedder in the RAG pipeline.
"""
import os
import re
import sys

SENTINEL_START = "  # ── HDP_EMBEDDER_BLOCK_START ──────────────────────────────────────"
SENTINEL_END = "  # ── HDP_EMBEDDER_BLOCK_END ────────────────────────────────────────"

LOCAL_BLOCK = """  # ── HDP_EMBEDDER_BLOCK_START ──────────────────────────────────────
  # Rendered by render_pipeline.py for HDP_EMBEDDING_PROVIDER=local.
  # Do not hand-edit — edit render_pipeline.py instead, see .env.example.
  query_embedder:
    type: haystack.components.embedders.sentence_transformers_text_embedder.SentenceTransformersTextEmbedder
    init_parameters:
      model: "${HDP_EMBEDDING_MODEL}"
      prefix: "query: "
  # ── HDP_EMBEDDER_BLOCK_END ────────────────────────────────────────"""

REMOTE_BLOCK = """  # ── HDP_EMBEDDER_BLOCK_START ──────────────────────────────────────
  # Rendered by render_pipeline.py for HDP_EMBEDDING_PROVIDER=remote.
  # Do not hand-edit — edit render_pipeline.py instead, see .env.example.
  query_embedder:
    type: haystack.components.embedders.openai_text_embedder.OpenAITextEmbedder
    init_parameters:
      api_key:
        type: env_var
        env_vars:
          - HDP_EMBEDDING_API_KEY
        strict: false
      api_base_url: "${HDP_EMBEDDING_BASE_URL}"
      model: "${HDP_EMBEDDING_MODEL}"
      prefix: "query: "
  # ── HDP_EMBEDDER_BLOCK_END ────────────────────────────────────────"""

BLOCKS = {
    "local": LOCAL_BLOCK,
    "remote": REMOTE_BLOCK,
}


def render(source: str, provider: str) -> str:
    if provider not in BLOCKS:
        valid = ", ".join(sorted(BLOCKS))
        raise SystemExit(
            f"HDP_EMBEDDING_PROVIDER={provider!r} is not valid for the live "
            f"pipeline (valid: {valid}). 'hf_space' is ingestion-only — see "
            f"ingest_hdp_wiki.py --provider hf_space, not this pipeline."
        )

    pattern = re.compile(
        re.escape(SENTINEL_START) + r".*?" + re.escape(SENTINEL_END),
        re.DOTALL,
    )
    new_source, n = pattern.subn(BLOCKS[provider], source, count=1)
    if n != 1:
        raise SystemExit(
            "Could not find the HDP_EMBEDDER_BLOCK sentinels in the pipeline "
            "YAML — has the file been edited by hand? Expected exactly one "
            f"occurrence of the block between {SENTINEL_START.strip()} and "
            f"{SENTINEL_END.strip()}."
        )
    return new_source


def main():
    pipeline_in = os.environ.get("PIPELINE_FILE") or (sys.argv[1] if len(sys.argv) > 1 else None)
    if not pipeline_in:
        raise SystemExit("Usage: render_pipeline.py <pipeline.yaml> [output.yaml]  (or set PIPELINE_FILE)")
    pipeline_out = sys.argv[2] if len(sys.argv) > 2 else pipeline_in

    provider = os.environ.get("HDP_EMBEDDING_PROVIDER", "local").strip().lower()

    with open(pipeline_in) as f:
        source = f.read()

    rendered = render(source, provider)

    with open(pipeline_out, "w") as f:
        f.write(rendered)

    print(f"[render_pipeline] HDP_EMBEDDING_PROVIDER={provider} -> wrote {pipeline_out}")


if __name__ == "__main__":
    main()
