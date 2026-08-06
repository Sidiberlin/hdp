# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""Pure wiki-text transforms, extracted from ingest_hdp_wiki.py.

Everything in here is a pure function of its arguments: no database, no
MediaWiki API, no OpenSearch, no environment. That is the entire reason the
module exists — ingest_hdp_wiki.py cannot be imported without pymysql,
requests, haystack and the OpenSearch integration being installed, so the
functions below were untestable while they lived there despite needing nothing
but the standard library.

Extracted in Wave 2 with no behaviour change. The one non-mechanical edit is
that `_decode` is now `decode_varbinary`: it is called from ingest_hdp_wiki.py,
and a leading underscore on a name another module imports says the opposite of
what is true.

Imported by ingest_hdp_wiki.py, which the container runs as
`python3 ingest_hdp_wiki.py` from /opt/pipeline — so this file has to sit
beside it, and docker/haystack/Dockerfile has to COPY it.
"""
import re
from html.parser import HTMLParser

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


def decode_varbinary(v):
    """Decode varbinary columns (page_title, page_content_model) to str."""
    if isinstance(v, bytes):
        return v.decode("utf-8", errors="replace")
    return v


def make_prefixed_title(namespace: int, title: str) -> str:
    ns_text = NAMESPACE_TEXT.get(namespace, "")
    title = title.replace("_", " ")
    if ns_text:
        return f"{ns_text}:{title}"
    return title


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
