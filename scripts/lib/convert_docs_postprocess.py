#!/usr/bin/env python3
# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
"""The wikitext post-processor behind scripts/convert-docs.sh.

Pandoc turns `docs/wiki/*.md` into MediaWiki markup; this module turns pandoc's
output into markup *this* wiki can render. Three of the four transforms exist
because of a concrete rendering failure, and the mermaid one in particular is
the kind of substitution that is easy to break and impossible to notice without
opening a page in a browser — which is why it now has golden files
(`tests/unit/test_convert_docs_postprocess.py`).

Extracted from a heredoc inside convert-docs.sh, for two reasons:

  * a heredoc cannot be imported, so the only way to test it was to run the
    whole script — which needs docker, a running stack and pandoc inside the
    haystack container. None of that is available where unit tests run.
  * the source-file → wiki-page mapping was written out three times in that
    script (a bash `case`, a Python dict, and the literal file list in the
    conversion loop). Three copies of one table is two chances to update the
    wrong one. It now lives here once, and convert-docs.sh asks for it.

Usage from the shell:

    convert_docs_postprocess.py <src-relpath>   < wikitext > cleaned wikitext
    convert_docs_postprocess.py --list-sources  the markdown files to convert
    convert_docs_postprocess.py --page-for <src-relpath>   the target page name
"""
import html
import os
import re
import sys
from pathlib import PurePosixPath

# Source markdown → target wiki page. The single source of truth; the order is
# the order convert-docs.sh converts them in.
PAGE_MAP = {
    "docs/wiki/README.md": "Help:Technische_Dokumentation",
    "docs/wiki/architecture.md": "Help:Architektur",
    "docs/wiki/getting-started.md": "Help:Erste_Schritte",
    "docs/wiki/modules/chatbot-extension.md": "Help:Modul/ChatBot-Extension",
    "docs/wiki/modules/settings-d.md": "Help:Modul/Settings.d",
    "docs/wiki/modules/docker-services.md": "Help:Modul/Docker-Services",
    "docs/wiki/modules/embedding-providers.md": "Help:Modul/Embedding-Provider",
    "docs/wiki/modules/haystack-pipeline.md": "Help:Modul/Haystack-Pipeline",
    "docs/wiki/modules/ingestion.md": "Help:Modul/Ingestion",
    "docs/wiki/diagrams/sequences.md": "Help:Diagramme/Sequenzen",
    "docs/wiki/diagrams/class-diagram.md": "Help:Diagramme/Klassendiagramm",
}

DEFAULT_REPO_URL = "https://github.com/Sidiberlin/hdp/blob/main"


def mermaid_replace(match):
    """A pandoc-rendered mermaid block → the `{{#mermaid:}}` parser function.

    Two decodings happen here and both matter:

    * pandoc HTML-escapes `<`, `>`, `&` and `"` inside a `<pre>`. Mermaid's
      arrow syntax is `-->`, so leaving them escaped renders a page of
      `--&gt;` instead of a diagram.
    * mermaid's hexagon node shape is `id{{"label"}}`, and `{{...}}` is
      MediaWiki template syntax. The wiki parser expands it *before* the
      Mermaid extension ever sees the content, so the graph arrives corrupted.
      Substituting the visually similar subroutine shape `[[...]]` avoids the
      collision — and `[[...]]` is safe here because the whole block is inside
      a parser-function argument, not article wikitext.
    """
    body = html.unescape(match.group(1))
    body = re.sub(r'(\w+)\{\{("[^"]+")\}\}', r"\1[[\2]]", body)
    body = body.rstrip("\n")
    return "{{#mermaid:" + body + "\n}}"


def resolve_relative(src_relpath, target):
    """Resolve a relative markdown link against its source file's directory.

    Returns `(resolved_posix_path, fragment)`. The `..` collapsing is done by
    hand rather than with `os.path.normpath` so the result is independent of
    the platform separator and of whether the path exists on this machine —
    this runs against repo-relative strings, not against the filesystem.
    """
    frag = ""
    if "#" in target:
        target, frag = target.split("#", 1)
        frag = "#" + frag
    base = PurePosixPath(src_relpath).parent
    resolved = (base / target).as_posix()
    parts = []
    for part in resolved.split("/"):
        if part == "..":
            if parts and parts[-1] != "..":
                parts.pop()
            else:
                parts.append(part)
        elif part and part != ".":
            parts.append(part)
    return "/".join(parts), frag


def postprocess(text, src_relpath, repo_url=DEFAULT_REPO_URL):
    """Clean one pandoc-produced wikitext document.

    `src_relpath` is the repo-relative path of the markdown file the text came
    from; relative links are resolved against its directory, so passing the
    wrong one silently produces wrong links rather than an error.
    """

    # 1. mermaid fenced code blocks. Pandoc emits one of two forms depending on
    #    its version, and this repo has seen both.
    text = re.sub(
        r'<pre class="mermaid">(.*?)</pre>', mermaid_replace, text, flags=re.DOTALL
    )
    text = re.sub(
        r'<syntaxhighlight lang="mermaid">(.*?)</syntaxhighlight>',
        mermaid_replace,
        text,
        flags=re.DOTALL,
    )

    # 2. Pandoc's per-heading `<span id="...">` anchors. BlueSpice generates its
    #    own TOC anchors; these bare spans only add visual noise.
    text = re.sub(r'<span id="[^"]*"></span>\n?', "", text)

    # 3+4. Links. Pandoc renders a markdown `[label](target)` whose target has
    #      no URL scheme as `[[target|label]]`, so everything matched here was
    #      a relative path in the source document.
    def rewrite_link(match):
        target = match.group(1).strip()
        label = match.group(2)
        if target.startswith("#"):
            # A same-page anchor. `[[#section|label]]` is already correct
            # wikitext and must be left alone: resolving it as a relative path
            # gives the source file's *directory*, which is not a page, so it
            # used to come out as an external link into the repo browser —
            # a link to `docs/wiki/modules#anchor`, which is a directory.
            return match.group(0)
        resolved, frag = resolve_relative(src_relpath, target)
        if resolved in PAGE_MAP:
            return "[[" + PAGE_MAP[resolved] + frag + "|" + label + "]]"
        if resolved.endswith(".md"):
            # An .md file that is not one of the converted pages. Emitting a
            # wiki link would create a redlink to a page that will never exist,
            # so degrade to plain text — visible in review, harmless in prod.
            return label
        # Anything else is a real file in the repo: link out to the browser.
        return "[" + repo_url + "/" + resolved + frag + " " + label + "]"

    text = re.sub(r"\[\[([^\[\]|]+)\|([^\[\]]+)\]\]", rewrite_link, text)

    def rewrite_bare(match):
        target = match.group(1).strip()
        if target.startswith("#"):
            return match.group(0)  # same-page anchor; see rewrite_link
        if "|" in target or ":" in target and target.split(":")[0] in (
            "http",
            "https",
            "mailto",
        ):
            return match.group(0)
        resolved, frag = resolve_relative(src_relpath, target)
        if resolved in PAGE_MAP:
            return "[[" + PAGE_MAP[resolved] + frag + "]]"
        if resolved.endswith(".md"):
            return target
        return "[" + repo_url + "/" + resolved + frag + "]"

    # Deliberately conservative: only tokens that look like a relative path, so
    # a legitimate wiki-internal link such as [[Hauptseite]] is left alone.
    text = re.sub(
        r"\[\[((?:\.\./|[A-Za-z0-9_./-]+\.md|[A-Za-z0-9_./-]+/[A-Za-z0-9_./-]+)"
        r"[^\[\]|]*)\]\]",
        rewrite_bare,
        text,
    )

    return text


def page_for(src_relpath):
    """The wiki page a source markdown file becomes, or None."""
    return PAGE_MAP.get(src_relpath)


def main(argv):
    if len(argv) == 2 and argv[1] == "--list-sources":
        print("\n".join(PAGE_MAP))
        return 0
    if len(argv) == 3 and argv[1] == "--page-for":
        page = page_for(argv[2])
        if page is None:
            print(f"no target page for {argv[2]!r}", file=sys.stderr)
            return 1
        print(page)
        return 0
    if len(argv) != 2:
        print(__doc__.strip().splitlines()[-3], file=sys.stderr)
        print(
            "usage: convert_docs_postprocess.py <src-relpath> | --list-sources "
            "| --page-for <src-relpath>",
            file=sys.stderr,
        )
        return 2

    sys.stdout.write(
        postprocess(
            sys.stdin.read(),
            argv[1],
            os.environ.get("REPO_BROWSE_URL") or DEFAULT_REPO_URL,
        )
    )
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
