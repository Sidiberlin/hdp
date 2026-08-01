#!/usr/bin/env bash
# ============================================================
# convert-docs.sh
# Convert the codewiki-generated technical documentation under
# docs/wiki/*.md into MediaWiki-native wikitext (.wiki files) that
# docker/setup.sh ingests as Help-namespace pages on first boot.
#
# Runs pandoc via the haystack container's pypandoc-binary (no host
# install required); rewrites relative markdown links to the
# corresponding wiki page names; and rewrites pandoc's
# `<pre class="mermaid">…</pre>` fenced-code emission to the
# `{{#mermaid:…}}` parser-function syntax that the vendored
# Mermaid extension (see app/settings.d/060-Mermaid.php) understands.
#
# Idempotent — safe to re-run any time after docs/wiki/*.md changes.
# ============================================================
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="$REPO_ROOT/docker/mediawiki/wiki-docs"

# pandoc binary shipped inside the haystack container via pypandoc-binary.
# We invoke it through `docker compose exec` so the host needs nothing beyond
# docker + the running stack.
HAYSTACK_PANDOC="/usr/local/lib/python3.11/site-packages/pypandoc/files/pandoc"

# Repo browse URL used for external (out-of-wiki) file references.
# When ingested pages mention e.g. docker-compose.yml or docker/setup.sh, the
# converted link points here so admins can jump straight to the source file.
REPO_BROWSE_URL="${HDP_REPO_BROWSE_URL:-https://github.com/Sidiberlin/hdp/blob/main}"
export REPO_BROWSE_URL

mkdir -p "$OUT_DIR"

# Verify container + pandoc are reachable before doing anything else.
if ! docker compose exec -T haystack test -x "$HAYSTACK_PANDOC" 2>/dev/null; then
    echo "ERROR: pandoc not found in haystack container at $HAYSTACK_PANDOC" >&2
    echo "Run:   docker compose exec haystack pip install pypandoc-binary" >&2
    exit 1
fi

# ─── Page-name mapping (source md → target wiki page) ─────────────
# Uses a plain function instead of an associative array so this script
# works with the /bin/sh-style bash present in minimal images too.
target_page_for() {
    case "$1" in
        docs/wiki/README.md)                       echo "Help:Technische_Dokumentation" ;;
        docs/wiki/architecture.md)                 echo "Help:Architektur" ;;
        docs/wiki/getting-started.md)              echo "Help:Erste_Schritte" ;;
        docs/wiki/modules/chatbot-extension.md)    echo "Help:Modul/ChatBot-Extension" ;;
        docs/wiki/modules/settings-d.md)           echo "Help:Modul/Settings.d" ;;
        docs/wiki/modules/docker-services.md)      echo "Help:Modul/Docker-Services" ;;
        docs/wiki/modules/embedding-providers.md)  echo "Help:Modul/Embedding-Provider" ;;
        docs/wiki/modules/haystack-pipeline.md)    echo "Help:Modul/Haystack-Pipeline" ;;
        docs/wiki/modules/ingestion.md)            echo "Help:Modul/Ingestion" ;;
        docs/wiki/diagrams/sequences.md)           echo "Help:Diagramme/Sequenzen" ;;
        docs/wiki/diagrams/class-diagram.md)       echo "Help:Diagramme/Klassendiagramm" ;;
        *) return 1 ;;
    esac
}

# ─── Post-processor: rewrite the pandoc wikitext output ───────────
# Reads wikitext on stdin, writes cleaned wikitext to stdout.
#   1. `<pre class="mermaid">…</pre>` blocks (pandoc's default rendering
#      of a ```mermaid fenced code block) → `{{#mermaid:…}}` parser
#      function calls, with pandoc's HTML-entity escapes decoded back to
#      raw characters (mermaid.js will re-decode as needed).
#   2. `<span id="…"></span>` header anchors emitted by pandoc for every
#      heading → stripped (BlueSpice generates its own toc anchors, and
#      these bare spans render as visual noise).
#   3. `[[relative/path.md|label]]` links → `[[Help:Target_Page|label]]`
#      following the same page-name mapping used by target_page_for.
#   4. `[[../../repo/relative/path|label]]` links to files OUTSIDE the
#      wiki → external `[URL label]` links pointing at the repo browser.
postprocess() {
    REPO_URL="$REPO_BROWSE_URL" python3 - "$1" <<'PY'
import os, re, sys, html

src_relpath = sys.argv[1]  # e.g. "docs/wiki/modules/ingestion.md"
text = sys.stdin.read()

# Map from source md path → target wiki page name (mirrors target_page_for()).
MAP = {
    "docs/wiki/README.md":                      "Help:Technische_Dokumentation",
    "docs/wiki/architecture.md":                "Help:Architektur",
    "docs/wiki/getting-started.md":             "Help:Erste_Schritte",
    "docs/wiki/modules/chatbot-extension.md":   "Help:Modul/ChatBot-Extension",
    "docs/wiki/modules/settings-d.md":          "Help:Modul/Settings.d",
    "docs/wiki/modules/docker-services.md":     "Help:Modul/Docker-Services",
    "docs/wiki/modules/embedding-providers.md": "Help:Modul/Embedding-Provider",
    "docs/wiki/modules/haystack-pipeline.md":   "Help:Modul/Haystack-Pipeline",
    "docs/wiki/modules/ingestion.md":           "Help:Modul/Ingestion",
    "docs/wiki/diagrams/sequences.md":          "Help:Diagramme/Sequenzen",
    "docs/wiki/diagrams/class-diagram.md":      "Help:Diagramme/Klassendiagramm",
}

REPO_URL = os.environ.get("REPO_URL", "https://github.com/Sidiberlin/hdp/blob/main")

# ---------------------------------------------------------------
# 1. mermaid fenced-code blocks → {{#mermaid:...}} parser function
# ---------------------------------------------------------------
def mermaid_replace(m):
    body = m.group(1)
    # pandoc encodes < > & " when emitting a <pre class="mermaid"> block.
    # Decode back to raw mermaid syntax; the extension's parser function
    # will re-escape as needed for the data-mermaid attribute.
    body = html.unescape(body)
    # Mermaid's hexagon-node shape uses {{"..."}} — which collides with
    # MediaWiki template syntax and gets expanded by the wiki parser
    # BEFORE the mermaid extension sees the content, corrupting the
    # graph. Substitute the visually similar "subroutine" shape [[...]]
    # which has no wiki-syntax collision.
    body = re.sub(r'(\w+)\{\{("[^"]+")\}\}', r'\1[[\2]]', body)
    # Strip a single trailing newline so the closing }} sits flush.
    body = body.rstrip("\n")
    return "{{#mermaid:" + body + "\n}}"

text = re.sub(
    r'<pre class="mermaid">(.*?)</pre>',
    mermaid_replace,
    text,
    flags=re.DOTALL,
)
# Some pandoc versions emit <syntaxhighlight lang="mermaid"> instead.
text = re.sub(
    r'<syntaxhighlight lang="mermaid">(.*?)</syntaxhighlight>',
    mermaid_replace,
    text,
    flags=re.DOTALL,
)

# ---------------------------------------------------------------
# 2. Strip pandoc's <span id="..."></span> header anchors
# ---------------------------------------------------------------
text = re.sub(r'<span id="[^"]*"></span>\n?', '', text)

# ---------------------------------------------------------------
# 3+4. Rewrite [[…]] links pandoc produced from relative markdown links
# ---------------------------------------------------------------
def resolve_relative(src_relpath, target):
    """
    Given the current source file's repo-relative path and a relative
    target string (may include ../, may end in .md, may include #anchor),
    return either a page-name mapping key (as posix path) or None if the
    target isn't a wiki page.
    """
    from pathlib import PurePosixPath
    # Drop any URL fragment before resolving on the filesystem.
    frag = ""
    if "#" in target:
        target, frag = target.split("#", 1)
        frag = "#" + frag
    base = PurePosixPath(src_relpath).parent
    resolved = (base / target).as_posix()
    # Collapse ../ segments the same way a POSIX filesystem would.
    parts = []
    for p in resolved.split("/"):
        if p == "..":
            if parts and parts[-1] != "..":
                parts.pop()
            else:
                parts.append(p)
        elif p and p != ".":
            parts.append(p)
    return "/".join(parts), frag

def rewrite_link(m):
    target = m.group(1).strip()
    label  = m.group(2)
    # External URLs already look like [url label] in wikitext, not [[…]],
    # so anything here is meant to be a wiki link.
    resolved, frag = resolve_relative(src_relpath, target)
    if resolved in MAP:
        page = MAP[resolved]
        return "[[" + page + frag + "|" + label + "]]"
    # Not one of our wiki pages — treat as a link to a source file in
    # the repo. Convert to an external link so it opens in a new tab.
    if resolved.endswith(".md"):
        # An .md target we don't recognise; leave it as a plain label so
        # we notice it in review rather than emit a broken redlink.
        return label
    return "[" + REPO_URL + "/" + resolved + frag + " " + label + "]"

# Pandoc emits [[target|label]] for markdown [label](target) when the
# target looks like a filesystem path (no scheme). Match those.
text = re.sub(
    r'\[\[([^\[\]|]+)\|([^\[\]]+)\]\]',
    rewrite_link,
    text,
)

# Also handle bare [[target]] with no label (pandoc uses target as label).
def rewrite_bare(m):
    target = m.group(1).strip()
    if "|" in target or ":" in target and target.split(":")[0] in ("http", "https", "mailto"):
        return m.group(0)
    resolved, frag = resolve_relative(src_relpath, target)
    if resolved in MAP:
        return "[[" + MAP[resolved] + frag + "]]"
    if resolved.endswith(".md"):
        return target
    return "[" + REPO_URL + "/" + resolved + frag + "]"

# Deliberately conservative — only match tokens that look like a
# relative path, to avoid clobbering legitimate wiki-internal links.
text = re.sub(
    r'\[\[((?:\.\./|[A-Za-z0-9_./-]+\.md|[A-Za-z0-9_./-]+/[A-Za-z0-9_./-]+)[^\[\]|]*)\]\]',
    rewrite_bare,
    text,
)

sys.stdout.write(text)
PY
}

# ─── Convert every mapped file ────────────────────────────────────
count=0
for src in \
    docs/wiki/README.md \
    docs/wiki/architecture.md \
    docs/wiki/getting-started.md \
    docs/wiki/modules/chatbot-extension.md \
    docs/wiki/modules/settings-d.md \
    docs/wiki/modules/docker-services.md \
    docs/wiki/modules/embedding-providers.md \
    docs/wiki/modules/haystack-pipeline.md \
    docs/wiki/modules/ingestion.md \
    docs/wiki/diagrams/sequences.md \
    docs/wiki/diagrams/class-diagram.md
do
    page="$(target_page_for "$src")"
    # Filenames may not contain '/' — the MediaWiki subpage separator is
    # encoded as '__' (double underscore) on disk. setup.sh reverses this
    # when calling edit.php. Underscore-in-page-name → still '_' on disk
    # (single), because MediaWiki page names never contain literal '__'.
    fname="${page//\//__}"
    out="$OUT_DIR/${fname}.wiki"
    src_full="$REPO_ROOT/$src"

    if [ ! -f "$src_full" ]; then
        echo "WARN: source missing: $src_full" >&2
        continue
    fi

    echo "  $src  ->  ${page}"

    # Convert md → mediawiki via containerised pandoc, then post-process.
    # We route the md file into the container via stdin and get wikitext
    # on stdout, avoiding any need for shared temp files.
    #
    # Post-processor needs the source path so it can resolve relative
    # markdown links against the correct base directory.
    docker compose exec -T haystack "$HAYSTACK_PANDOC" \
        -f markdown -t mediawiki --wrap=preserve \
        < "$src_full" \
        | postprocess "$src" > "$out"

    count=$((count + 1))
done

# ─── Generate the top-level Help:Inhaltsverzeichnis ────────────────
# hauptseite.wiki line 62 links here as "Hilfebereich" — it's the
# single entry point into all the codewiki-derived documentation.
cat > "$OUT_DIR/Help:Inhaltsverzeichnis.wiki" <<'WIKI'
== Hilfebereich — Inhaltsverzeichnis ==

Diese Seite listet die von [https://github.com/anthropics/codewiki codewiki]
aus dem Projekt-Quelltext generierte technische Dokumentation. Die
Quelldateien liegen im Repository unter <code>docs/wiki/</code> und werden
beim ersten Container-Boot in den Wiki-Hilfebereich importiert.

=== Übersicht ===

* [[Help:Technische_Dokumentation]] — Projektüberblick und Komponentenkarte
* [[Help:Architektur]] — Systemdiagramm und Design-Entscheidungen
* [[Help:Erste_Schritte]] — Installation, Erststart, wichtige Workflows

=== Module ===

* [[Help:Modul/ChatBot-Extension]] — REST-Routen, Deepset-Connector, Chat-UI
* [[Help:Modul/Haystack-Pipeline]] — YAML-Pipeline, hayhooks, hdp_api_server
* [[Help:Modul/Ingestion]] — Wiki → Sections → Embeddings → OpenSearch
* [[Help:Modul/Embedding-Provider]] — Lokales Modell / Remote / HuggingFace Space
* [[Help:Modul/Docker-Services]] — docker-compose.yml, Volumes, Netzwerk, Health-Checks
* [[Help:Modul/Settings.d]] — BlueSpice-Extension-Loader und HDP-Overrides

=== Diagramme ===

* [[Help:Diagramme/Sequenzen]] — Chat-Query, Ingestion, First-Boot als sequenceDiagram
* [[Help:Diagramme/Klassendiagramm]] — Warum kein klassisches Klassendiagramm passt

=== Externe Ressourcen ===

* [[Chatbot-FAQ|Chatbot-FAQ]] — Endbenutzer-FAQ zum Chatbot
* [[Hauptseite]] — Wiki-Hauptseite
WIKI

count=$((count + 1))
echo ""
echo "Wrote $count files to $OUT_DIR/"
