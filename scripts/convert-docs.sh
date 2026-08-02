#!/usr/bin/env bash
# ============================================================
# convert-docs.sh
# Convert the codewiki-generated technical documentation under
# docs/wiki/*.md into MediaWiki-native wikitext (.wiki files) that
# docker/setup.sh ingests as Help-namespace pages on first boot.
#
# Runs pandoc from a pinned image (no host install required);
# rewrites relative markdown links to the
# corresponding wiki page names; and rewrites pandoc's
# `<pre class="mermaid">…</pre>` fenced-code emission to the
# `{{#mermaid:…}}` parser-function syntax that the vendored
# Mermaid extension (see app/settings.d/060-Mermaid.php) understands.
#
# Idempotent — safe to re-run any time after docs/wiki/*.md changes.
#
#   scripts/convert-docs.sh            regenerate docker/mediawiki/wiki-docs/
#   scripts/convert-docs.sh --check    regenerate into a temp dir and diff
#                                      against the committed output; exit 1 on
#                                      any drift, and change nothing.
#
# --check exists because the converted wikitext is a *committed build product*.
# setup.sh seeds docker/mediawiki/wiki-docs/*.wiki, not docs/wiki/*.md, so
# editing a markdown source without re-running this script ships documentation
# that silently does not match the repo. Nothing noticed that before Wave 3.
#
# The post-processor's own behaviour is covered by golden files that need
# neither docker nor pandoc — see tests/unit/test_convert_docs_postprocess.py.
# What --check adds on top is the half those cannot reach: the real pandoc, and
# whether the committed output is current.
# ============================================================
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMMITTED_DIR="$REPO_ROOT/docker/mediawiki/wiki-docs"
OUT_DIR="$COMMITTED_DIR"
CHECK=0

while [ $# -gt 0 ]; do
    case "$1" in
        --check) CHECK=1 ;;
        -h|--help) sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "convert-docs.sh: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
    shift
done

if [ "$CHECK" -eq 1 ]; then
    OUT_DIR="$(mktemp -d)"
    # shellcheck disable=SC2064  # expand OUT_DIR now, not at trap time
    trap "rm -rf '$OUT_DIR'" EXIT
fi

# pandoc, from a pinned image — the same shape scripts/check.sh uses for every
# other tool, so this needs docker and nothing else.
#
# It used to run pandoc out of the haystack container, at
# /usr/local/lib/python3.11/site-packages/pypandoc/files/pandoc, on the
# assumption that pypandoc-binary was installed there. **It is not, and never
# was**: nothing under docker/haystack/ mentions pandoc or pypandoc, so that
# path does not exist in the image and this script has been unable to run at
# all. Its own error message told you to `pip install pypandoc-binary` into a
# running container — a manual step, lost on the next `docker compose up`.
#
# Two things improve by moving off it. The converter no longer needs the 2.5 GB
# RAG image to reformat markdown, so it runs anywhere docker does — including
# the T3 minimal profile. And the version is pinned, which matters more here
# than anywhere else in the repo: the output of this script is *committed*, so
# an unpinned converter means the committed wikitext silently depends on
# whichever pandoc the last person happened to have.
#
# Verified across pandoc/core 3.5, 3.6.4, 3.7.0.2 and 3.10: byte-identical
# output for all eleven source documents. The pin is for reproducibility, not
# because the versions disagree.
IMG_PANDOC="pandoc/core:3.5"
PANDOC_PINNED_VERSION="3.5"

# Repo browse URL used for external (out-of-wiki) file references.
# When ingested pages mention e.g. docker-compose.yml or docker/setup.sh, the
# converted link points here so admins can jump straight to the source file.
REPO_BROWSE_URL="${HDP_REPO_BROWSE_URL:-https://gitlab.cloudsoziologe.de/edwin/hdp/-/blob/main}"
export REPO_BROWSE_URL

mkdir -p "$OUT_DIR"

# ─── Resolve a pandoc to run ──────────────────────────────────────
# Host binary first, but only when its version matches the pin — the output is
# committed, so a version difference is a real difference. Otherwise the pinned
# image. This mirrors scripts/check.sh's handling of ruff, where a host 0.15
# reporting nothing while CI's pinned 0.16 reported sixteen findings is the
# recorded precedent for not trusting whatever happens to be installed.
run_pandoc() {  # reads markdown on stdin, writes wikitext to stdout
    "${PANDOC_CMD[@]}" -f markdown -t mediawiki --wrap=preserve
}

host_pandoc_version="$(pandoc --version 2>/dev/null | head -1 | awk '{print $2}' || true)"

if [ -n "$host_pandoc_version" ] && [ "$host_pandoc_version" = "$PANDOC_PINNED_VERSION" ]; then
    PANDOC_CMD=(pandoc)
elif command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    if [ -n "$host_pandoc_version" ]; then
        echo "note: host pandoc $host_pandoc_version differs from the pinned" >&2
        echo "      $PANDOC_PINNED_VERSION; using $IMG_PANDOC so the committed" >&2
        echo "      wikitext stays reproducible." >&2
    fi
    PANDOC_CMD=(docker run --rm -i --entrypoint pandoc "$IMG_PANDOC")
elif [ -n "$host_pandoc_version" ]; then
    echo "WARNING: no usable docker; falling back to host pandoc" >&2
    echo "         $host_pandoc_version instead of the pinned $PANDOC_PINNED_VERSION." >&2
    echo "         Review the diff carefully before committing the output." >&2
    PANDOC_CMD=(pandoc)
else
    echo "ERROR: no pandoc on PATH and no usable docker." >&2
    echo "Install pandoc $PANDOC_PINNED_VERSION, or start docker so $IMG_PANDOC can run." >&2
    exit 1
fi

# ─── Page-name mapping and post-processing ────────────────────────
# Both live in scripts/lib/convert_docs_postprocess.py, and this script asks
# that module for everything: the list of files to convert, the target page
# name for each, and the wikitext cleanup itself.
#
# They used to be here, and the mapping was written out three separate times —
# a bash `case`, an identical Python dict inside the post-processor heredoc,
# and the literal file list in the conversion loop at the bottom. Three copies
# of one table is two chances to update the wrong one.
#
# The post-processor also could not be tested while it was a heredoc: reaching
# it meant running this whole script, which needs docker, a running stack, and
# pandoc inside the haystack container. As a module it has golden files
# (tests/unit/test_convert_docs_postprocess.py) that run on bare Python.
POSTPROCESS_PY="$REPO_ROOT/scripts/lib/convert_docs_postprocess.py"

if [ ! -f "$POSTPROCESS_PY" ]; then
    echo "ERROR: $POSTPROCESS_PY is missing" >&2
    exit 1
fi

target_page_for() {
    python3 "$POSTPROCESS_PY" --page-for "$1"
}

# ─── Post-processor ───────────────────────────────────────────────
# Reads wikitext on stdin, writes cleaned wikitext to stdout. See
# scripts/lib/convert_docs_postprocess.py for what each transform is for; the
# short version is that pandoc emits mermaid blocks, heading anchors and
# relative links in forms this wiki cannot render.
postprocess() {
    REPO_BROWSE_URL="$REPO_BROWSE_URL" python3 "$POSTPROCESS_PY" "$1"
}

# ─── Convert every mapped file ────────────────────────────────────
count=0
# The file list comes from the same module that owns the page mapping, so a doc
# page added there is converted here without a second edit.
while IFS= read -r src
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

    # Convert md → mediawiki, then post-process. The file goes in on stdin and
    # wikitext comes out on stdout, so no shared temp files and no bind mount.
    #
    # The post-processor needs the source path so it can resolve relative
    # markdown links against the correct base directory.
    run_pandoc < "$src_full" | postprocess "$src" > "$out"

    count=$((count + 1))
# Process substitution rather than a pipe, so the loop runs in this shell and
# `count` survives it. A `... | while read` would increment a copy in a
# subshell and report 1 file written.
done < <(python3 "$POSTPROCESS_PY" --list-sources)

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

=== Weiterführend ===

* [[Chatbot-FAQ]] — Endbenutzer-FAQ zum Chatbot
* [[Hauptseite]] — Wiki-Hauptseite
WIKI

count=$((count + 1))

if [ "$CHECK" -eq 0 ]; then
    echo ""
    echo "Wrote $count files to $OUT_DIR/"
    exit 0
fi

# ─── --check: compare against the committed output ─────────────────
echo ""
echo "Comparing $count generated file(s) against $COMMITTED_DIR/"
if diff -ru "$COMMITTED_DIR" "$OUT_DIR"; then
    echo "OK — the committed wikitext matches what this script produces today."
    exit 0
fi

cat >&2 <<EOF

docker/mediawiki/wiki-docs/ is out of date with docs/wiki/.

setup.sh seeds the .wiki files, not the markdown, so the wiki is currently
serving documentation that does not match this repo. Regenerate and commit:

    scripts/convert-docs.sh
    git add docker/mediawiki/wiki-docs/
EOF
exit 1
