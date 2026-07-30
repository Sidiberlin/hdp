#!/usr/bin/env python3
"""
HDP Wiki Ingestion Pipeline
Populates OpenSearch index 'hdp_wiki' from MediaWiki content.

Usage:
  python3 ingest_hdp_wiki.py                        # Full reindex, local embedder
  python3 ingest_hdp_wiki.py --page "Hauptseite"     # Single page
  python3 ingest_hdp_wiki.py --dry-run               # Preview without writing
  python3 ingest_hdp_wiki.py --missing-only          # Only pages not yet in OpenSearch
  python3 ingest_hdp_wiki.py --provider remote       # Embed via an OpenAI-compatible API
  python3 ingest_hdp_wiki.py --provider hf_space      # Embed via a HuggingFace ZeroGPU
                                                       # Space (ingestion/testing only —
                                                       # see docs, NOT for the live pipeline)

Embedding provider is chosen by --provider (or HDP_EMBEDDING_PROVIDER env var),
one of: local (default), remote, hf_space. See .env.example for the full set of
HDP_EMBEDDING_* variables each mode reads.
"""

import argparse
import hashlib
import json
import logging
import os
import re
import sys
import time
from html.parser import HTMLParser
from urllib.parse import quote

import requests
import pymysql
import warnings

# Silence noise that clutters ingestion output without hiding real errors:
#   - InsecureRequestWarning: expected — OpenSearch uses a self-signed cert
#     on the internal docker-compose network (verify_certs=False is
#     intentional; see get_indexed_page_ids and OpenSearchDocumentStore
#     initialization below).
try:
    from urllib3.exceptions import InsecureRequestWarning
    warnings.filterwarnings("ignore", category=InsecureRequestWarning)
except Exception:
    pass
from haystack.dataclasses import Document
from haystack_integrations.document_stores.opensearch.document_store import (
    OpenSearchDocumentStore,
    DuplicatePolicy,
)

# ─── Configuration ──────────────────────────────────────────────────
OPENSEARCH_HOST = os.environ.get("OPENSEARCH_HOST", "opensearch")
OPENSEARCH_PORT = int(os.environ.get("OPENSEARCH_PORT", "9200"))
OPENSEARCH_PASSWORD = os.environ.get("OPENSEARCH_PASSWORD", "admin")
INDEX_NAME = "hdp_wiki"
EMBEDDING_MODEL = os.environ.get("HDP_EMBEDDING_MODEL", "mixedbread-ai/deepset-mxbai-embed-de-large-v1")
EMBEDDING_DIM = int(os.environ.get("HDP_EMBEDDING_DIM", "1024"))

# MariaDB
DB_HOST = os.environ.get("DB_HOST", "mariadb")
DB_PORT = int(os.environ.get("DB_PORT", "3306"))
DB_NAME = os.environ.get("DB_NAME", "bluespice")
DB_USER = os.environ.get("DB_USER", "bluespice")
DB_PASS = os.environ.get("HDP_DB_PASSWORD", "")

# MediaWiki API
MW_API_URL = os.environ.get("MW_API_URL", "http://mediawiki-web:8080/w/api.php")
MW_ADMIN_USER = os.environ.get("MW_ADMIN_USER", "Admin")
MW_ADMIN_PASS = os.environ.get("HDP_ADMIN_PASSWORD", "")

# Content namespaces to index (from ChatBot extension.json, plus Help namespace)
INDEXABLE_NAMESPACES = [0, 12, 5000, 5002]

# Namespace ID → text mapping (from LocalSettings + extension.json)
NAMESPACE_TEXT = {
    0: "",
    8: "MediaWiki",
    10: "Template",
    12: "Help",
    102: "Property",
    112: "Group",
    5000: "Ministerium",
    5002: "Projektträger",
}

# Embedding provider config (see .env.example for the full variable set)
EMBEDDING_PROVIDER = os.environ.get("HDP_EMBEDDING_PROVIDER", "local").strip().lower()
EMBEDDING_BASE_URL = os.environ.get("HDP_EMBEDDING_BASE_URL", "")
EMBEDDING_API_KEY = os.environ.get("HDP_EMBEDDING_API_KEY", "")
EMBEDDING_HF_SPACE_ID = os.environ.get("HDP_EMBEDDING_HF_SPACE_ID", "")
EMBEDDING_HF_TOKEN = os.environ.get("HDP_EMBEDDING_HF_TOKEN", "") or os.environ.get("HF_TOKEN", "")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger("hdp-ingest")


# ─── Embedder Providers ─────────────────────────────────────────────
# Three interchangeable embedders, all exposing the same interface used
# below: embedder.embed_documents(list[Document]) -> list[Document] (with
# .embedding set on each). Selected via --provider / HDP_EMBEDDING_PROVIDER.


class LocalEmbedder:
    """Default. Runs sentence-transformers in-container (CPU, or GPU if
    HAYSTACK_DEVICE=cuda). Zero extra config — matches upstream behavior."""

    def __init__(self, model: str):
        from haystack.components.embedders.sentence_transformers_document_embedder import (
            SentenceTransformersDocumentEmbedder,
        )
        logger.info(f"Loading local embedding model: {model}")
        self._embedder = SentenceTransformersDocumentEmbedder(model=model, batch_size=16)
        self._embedder.warm_up()
        logger.info("Local embedding model ready")

    def embed_documents(self, documents: list) -> list:
        result = self._embedder.run(documents=documents)
        return result["documents"]


class RemoteEmbedder:
    """Any OpenAI-compatible embeddings endpoint (self-hosted TEI, a
    commercial API, etc.) — HDP_EMBEDDING_BASE_URL / _MODEL / _API_KEY."""

    def __init__(self, base_url: str, model: str, api_key: str):
        if not base_url:
            raise SystemExit(
                "HDP_EMBEDDING_PROVIDER=remote requires HDP_EMBEDDING_BASE_URL "
                "(and usually HDP_EMBEDDING_API_KEY). See .env.example."
            )
        from haystack.components.embedders.openai_document_embedder import (
            OpenAIDocumentEmbedder,
        )
        from haystack.utils import Secret
        logger.info(f"Using remote embedding API: {model} @ {base_url}")
        self._embedder = OpenAIDocumentEmbedder(
            api_key=Secret.from_token(api_key) if api_key else Secret.from_token("not-required"),
            model=model,
            api_base_url=base_url,
        )

    def embed_documents(self, documents: list) -> list:
        result = self._embedder.run(documents=documents)
        return result["documents"]


class HFSpaceEmbedder:
    """HuggingFace ZeroGPU Space via gradio_client — ingestion/testing ONLY.
    Cold starts and per-session rate limits make this unsuitable for the
    live query-time embedder (see render_pipeline.py, which rejects this
    mode entirely for the RAG pipeline)."""

    def __init__(self, space_id: str, hf_token: str, batch_size: int = 32):
        if not space_id:
            raise SystemExit(
                "HDP_EMBEDDING_PROVIDER=hf_space requires HDP_EMBEDDING_HF_SPACE_ID "
                "(e.g. 'your-username/your-embedder-space'). See .env.example."
            )
        from gradio_client import Client
        logger.info(f"Connecting to HF Space: {space_id}")
        self._client = Client(space_id, token=hf_token or None)
        self._batch_size = batch_size
        logger.info("Connected to HF Space")
        # Smoke test so a broken Space fails fast, before any DB/wiki work.
        test = self._call(["ping"])
        logger.info(f"HF Space embedding test passed ({len(test[0])}-dim)")

    def _call(self, texts: list) -> list:
        texts_json = json.dumps(texts)
        for attempt in range(5):
            try:
                result = self._client.predict(texts_json, api_name="/predict")
                return json.loads(result)
            except Exception as e:
                if "exceeded" in str(e).lower() and attempt < 4:
                    wait = (attempt + 1) * 15
                    logger.warning(f"  ZeroGPU quota hit, waiting {wait}s (attempt {attempt+1}/5)")
                    time.sleep(wait)
                    continue
                raise
        raise RuntimeError("HF Space embedding call exhausted all retries")

    def embed_documents(self, documents: list) -> list:
        texts = [d.content for d in documents]
        embeddings = []
        for i in range(0, len(texts), self._batch_size):
            embeddings.extend(self._call(texts[i:i + self._batch_size]))
        for doc, emb in zip(documents, embeddings):
            doc.embedding = emb
        return documents


def make_embedder(provider: str):
    """Factory: returns an object with .embed_documents(list[Document])."""
    if provider == "local":
        return LocalEmbedder(EMBEDDING_MODEL)
    if provider == "remote":
        return RemoteEmbedder(EMBEDDING_BASE_URL, EMBEDDING_MODEL, EMBEDDING_API_KEY)
    if provider == "hf_space":
        return HFSpaceEmbedder(EMBEDDING_HF_SPACE_ID, EMBEDDING_HF_TOKEN)
    raise SystemExit(
        f"Unknown --provider/HDP_EMBEDDING_PROVIDER={provider!r}. "
        "Valid values: local, remote, hf_space."
    )


class HTMLStripper(HTMLParser):
    """Strips all HTML tags, keeps text content. Equivalent to PHP strip_tags()."""
    def __init__(self):
        super().__init__()
        self.text = []
    def handle_data(self, data):
        self.text.append(data)
    def get_text(self):
        return "".join(self.text)


def strip_tags(html: str) -> str:
    stripper = HTMLStripper()
    stripper.feed(html)
    return stripper.get_text()


def split_by_sections(html_text: str) -> list[dict]:
    """
    Split rendered HTML by <h1>-<h6> headings.
    Mirrors IndexDeepset::getRawPageContentBySections.
    """
    sections = []

    # Intro text (before first heading)
    intro_match = re.match(r'^(.*?)\s*(?=<h[1-6]>)', html_text, re.DOTALL | re.IGNORECASE)
    if intro_match:
        intro_text = strip_tags(intro_match.group(1)).strip()
        if intro_text:
            sections.append({"section_name": "Intro", "content": intro_text})

    # Split by headings
    pattern = r'(<h[1-6]>.*?</h[1-6]>\s*.*?)(?=(<h[1-6]>.*?</h[1-6]>)|$)'
    matches = re.findall(pattern, html_text, re.DOTALL | re.IGNORECASE)

    for match in matches:
        chunk_html = match[0]
        heading_match = re.match(r'<h[1-6]>(.*?)</h[1-6]>', chunk_html, re.DOTALL | re.IGNORECASE)
        section_name = strip_tags(heading_match.group(1)).strip() if heading_match else "Unknown"
        content = strip_tags(chunk_html).strip()
        if content:
            sections.append({"section_name": section_name, "content": content})

    if not sections:
        full_text = strip_tags(html_text).strip()
        if full_text:
            sections.append({"section_name": "Full Page", "content": full_text})

    return sections


def build_title_levels(prefixed_title: str) -> dict:
    parts = prefixed_title.replace("_", " ").split("/")
    levels = {}
    for i in range(1, 6):
        levels[f"title_level_{i}"] = parts[i-1] if i <= len(parts) else ""
    return levels


def _decode(v):
    """Decode varbinary columns (page_title, page_content_model) to str."""
    if isinstance(v, bytes):
        return v.decode("utf-8", errors="replace")
    return v


def get_namespace_pages(conn) -> list[dict]:
    """Fetch all indexable pages from MariaDB."""
    with conn.cursor(pymysql.cursors.DictCursor) as cursor:
        cursor.execute("""
            SELECT p.page_id, p.page_namespace, p.page_title,
                   p.page_is_redirect, p.page_latest, p.page_content_model
            FROM page p
            WHERE p.page_is_redirect = 0
              AND p.page_namespace IN %s
              AND (p.page_content_model = 'wikitext' OR p.page_content_model IS NULL)
            ORDER BY p.page_id
        """, (tuple(INDEXABLE_NAMESPACES),))
        rows = cursor.fetchall()
    # Decode varbinary columns to str
    for row in rows:
        row["page_title"] = _decode(row["page_title"])
        row["page_content_model"] = _decode(row["page_content_model"])
    return rows


def get_indexed_page_ids() -> set:
    """Get the set of page_ids already present in OpenSearch (for --missing-only)."""
    import ssl
    import urllib.request
    # TLS verification disabled: matches OpenSearchDocumentStore(verify_certs=False)
    # used elsewhere in this file — the OpenSearch container uses a self-signed
    # cert and is only reachable on the internal docker-compose network.
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    import base64
    auth = base64.b64encode(f"admin:{OPENSEARCH_PASSWORD}".encode()).decode()
    body = json.dumps({"size": 0, "aggs": {"ids": {"terms": {"field": "page_id", "size": 1000}}}}).encode()
    req = urllib.request.Request(
        f"https://{OPENSEARCH_HOST}:{OPENSEARCH_PORT}/{INDEX_NAME}/_search",
        data=body, headers={"Authorization": f"Basic {auth}", "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
            data = json.loads(resp.read().decode())
            return {b["key"] for b in data["aggregations"]["ids"]["buckets"]}
    except Exception as e:
        logger.warning(f"Could not fetch indexed page_ids (index may not exist yet): {e}")
        return set()


def make_prefixed_title(namespace: int, title: str) -> str:
    ns_text = NAMESPACE_TEXT.get(namespace, "")
    title = title.replace("_", " ")
    if ns_text:
        return f"{ns_text}:{title}"
    return title


def mw_api_login() -> requests.Session:
    """
    Login to MediaWiki API via clientlogin (action=login is blocked in MW 1.43+).
    Handles BlueSpice Privacy consent multi-step authentication.
    """
    session = requests.Session()

    # Get login token
    r = session.get(MW_API_URL, params={
        "action": "query", "meta": "tokens", "type": "login", "format": "json"
    }, timeout=30)
    login_token = r.json().get("query", {}).get("tokens", {}).get("logintoken", "")

    # Step 1: clientlogin with username/password
    r = session.post(MW_API_URL, data={
        "action": "clientlogin", "format": "json",
        "username": MW_ADMIN_USER, "password": MW_ADMIN_PASS,
        "logintoken": login_token,
        "loginreturnurl": "http://mediawiki-web:8080/w/",
    }, timeout=30)
    data = r.json()
    cl = data.get("clientlogin", {})

    # Step 2: handle UI status (BlueSpice Privacy consent form)
    while cl.get("status") == "UI":
        fields = {}
        for req in cl.get("requests", []):
            req_id = req["id"]
            for fname, fdef in req.get("fields", {}).items():
                # Checkbox fields: submit "1" to accept
                if fdef.get("type") == "checkbox":
                    fields[fname] = "1"
        logger.info(f"  Auth UI step: accepting {list(fields.keys())}")

        r = session.post(MW_API_URL, data={
            "action": "clientlogin", "format": "json",
            "logintoken": login_token,
            "logincontinue": 1,
            **fields,
        }, timeout=30)
        data = r.json()
        cl = data.get("clientlogin", {})

    result = cl.get("status", "")
    if result != "PASS":
        raise RuntimeError(f"MediaWiki clientlogin failed: {data}")
    logger.info(f"Logged in to MediaWiki as {MW_ADMIN_USER}")
    return session


def render_page(session: requests.Session, prefixed_title: str) -> dict:
    """Render a wiki page via action=parse API."""
    r = session.get(MW_API_URL, params={
        "action": "parse", "format": "json",
        "page": prefixed_title,
        "prop": "text|displaytitle|categories|sections|properties|externallinks",
        "disablelimitreport": 1,
        "disableeditsection": 1,
        "disabletoc": 1,
    }, timeout=60)
    data = r.json()
    if "error" in data:
        raise RuntimeError(f"Parse error for {prefixed_title}: {data['error']}")
    return data["parse"]


def build_metadata(parsed: dict, page: dict, section_name: str) -> dict:
    """Build the metadata dict matching the query pipeline expectations."""
    ns = page["page_namespace"]
    prefixed_title = make_prefixed_title(ns, page["page_title"])
    title_levels = build_title_levels(prefixed_title)

    # Extract categories
    categories = [c.get("*", c.get("category", "")) for c in parsed.get("categories", [])]

    # Extract chatbotmeta from properties (SMW)
    chatbotmeta = ""
    for prop in parsed.get("properties", []):
        if prop.get("name", "").lower() == "chatbotmeta":
            chatbotmeta = "; ".join(prop.get("values", []))
            break

    # Display title
    display_title = strip_tags(parsed.get("displaytitle", "")).strip() or prefixed_title

    meta = {
        # Prompt-required fields
        "title_level_1": title_levels["title_level_1"],
        "title_level_2": title_levels["title_level_2"],
        "title_level_3": title_levels["title_level_3"],
        "title_level_4": title_levels["title_level_4"],
        "title_level_5": title_levels["title_level_5"],
        # Ranker fields
        "chatbotmeta": chatbotmeta,
        "display_title": display_title,
        "sections": [section_name] if section_name != "Full Page" else [],
        # Additional fields (from UpdateIndexTable mapping)
        "prefixed_title": prefixed_title,
        "namespace": ns,
        "namespace_text": NAMESPACE_TEXT.get(ns, ""),
        "categories": categories,
        "tags": [],
        "sourcekey": "wikipage",
        "page_id": page["page_id"],
        "uri": f"http://mediawiki-web:8080/w/{prefixed_title.replace(' ', '_')}",
    }
    return meta


def process_page(session, embedder, store, page, dry_run=False):
    """Render, split, embed, and index a single page."""
    prefixed_title = make_prefixed_title(page["page_namespace"], page["page_title"])
    logger.info(f"Processing: {prefixed_title} (page_id={page['page_id']})")

    try:
        parsed = render_page(session, prefixed_title)
    except Exception as e:
        logger.error(f"  Render failed: {e}")
        return 0

    html = parsed.get("text", {}).get("*", "")
    if not html.strip():
        logger.warning(f"  Empty HTML for {prefixed_title}")
        return 0

    sections = split_by_sections(html)
    logger.info(f"  Split into {len(sections)} sections")

    documents = []
    for section in sections:
        meta = build_metadata(parsed, page, section["section_name"])
        doc_id = hashlib.sha256(
            f"{page['page_id']}:{section['section_name']}".encode()
        ).hexdigest()[:32]

        doc = Document(
            id=doc_id,
            content=section["content"],
            meta=meta,
        )
        documents.append(doc)

    if dry_run:
        for d in documents:
            logger.info(f"  [DRY RUN] {d.meta['sections']}: {d.content[:80]}...")
        return len(documents)

    # Embed
    logger.info(f"  Embedding {len(documents)} documents...")
    documents = embedder.embed_documents(documents)

    # Write to OpenSearch
    logger.info(f"  Writing to OpenSearch index '{INDEX_NAME}'...")
    written = store.write_documents(
        documents,
        policy=DuplicatePolicy.OVERWRITE,
    )
    logger.info(f"  Wrote {written} documents")
    return written


def main():
    parser = argparse.ArgumentParser(description="Ingest HDP wiki into OpenSearch")
    parser.add_argument("--page", help="Index only this page (by title)")
    parser.add_argument("--dry-run", action="store_true", help="Don't write to OpenSearch")
    parser.add_argument(
        "--missing-only", action="store_true",
        help="Only index pages whose page_id is not already in OpenSearch (fast resume)",
    )
    parser.add_argument(
        "--provider", choices=["local", "remote", "hf_space"], default=None,
        help="Embedding provider for this run. Defaults to $HDP_EMBEDDING_PROVIDER "
             "(itself defaulting to 'local'). 'hf_space' is for ingestion/testing "
             "only — never valid for the live query pipeline.",
    )
    args = parser.parse_args()

    provider = (args.provider or EMBEDDING_PROVIDER).strip().lower()

    # Connect to MariaDB
    conn = pymysql.connect(
        host=DB_HOST, port=DB_PORT, user=DB_USER, password=DB_PASS, database=DB_NAME,
        charset="utf8mb4",
    )
    logger.info(f"Connected to MariaDB at {DB_HOST}:{DB_PORT}/{DB_NAME}")

    # Get pages
    pages = get_namespace_pages(conn)
    if args.page:
        pages = [p for p in pages if args.page.lower() in p["page_title"].lower()]
    logger.info(f"Found {len(pages)} pages in indexable namespaces")

    if args.missing_only:
        indexed = get_indexed_page_ids()
        before = len(pages)
        pages = [p for p in pages if p["page_id"] not in indexed]
        logger.info(f"--missing-only: {before - len(pages)} already indexed, {len(pages)} remaining")

    if not pages:
        logger.warning("No pages to index. Exiting.")
        return

    # Login to MediaWiki API
    mw_session = mw_api_login()

    # Initialize embedder (see make_embedder / LocalEmbedder / RemoteEmbedder / HFSpaceEmbedder above)
    logger.info(f"Embedding provider: {provider}")
    embedder = make_embedder(provider)

    # Initialize OpenSearch document store
    store = OpenSearchDocumentStore(
        hosts=[f"{OPENSEARCH_HOST}:{OPENSEARCH_PORT}"],
        use_ssl=True,
        verify_certs=False,
        http_auth=["admin", OPENSEARCH_PASSWORD],
        index=INDEX_NAME,
        embedding_dim=EMBEDDING_DIM,
        similarity="cosine",
    )
    logger.info(f"OpenSearch store initialized for index '{INDEX_NAME}'")

    # Process pages
    total_docs = 0
    for i, page in enumerate(pages, 1):
        logger.info(f"[{i}/{len(pages)}]")
        try:
            total_docs += process_page(mw_session, embedder, store, page, args.dry_run)
        except Exception as e:
            logger.error(f"  FAILED: {e}", exc_info=True)
        time.sleep(0.2)  # Rate limit API calls

    logger.info(f"\nDone! Total documents indexed: {total_docs}")

    # Verify
    if not args.dry_run:
        doc_count = store.count_documents()
        logger.info(f"OpenSearch index '{INDEX_NAME}' now has {doc_count} documents")


if __name__ == "__main__":
    main()
