"""3.5 — the committed wikitext still matches what convert-docs.sh produces.

`docker/mediawiki/wiki-docs/*.wiki` is a build product that happens to be
committed, and `docker/setup.sh` seeds *those files* — never `docs/wiki/*.md`.
So editing a markdown source, or hand-editing a generated `.wiki`, ships a wiki
whose documentation quietly disagrees with the repo it documents. Nothing
failed anywhere when that happened, and it had: six cross-page links were
committed unconverted, as `[[architecture.md#key-design-decisions|…]]`, and had
been rendering as red links to a page called "architecture.md".

The post-processor's own transforms are covered by golden files that need
neither docker nor pandoc (`tests/unit/test_convert_docs_postprocess.py`).
This adds the half those cannot reach: the real pandoc, and whether the
committed output is current.

It lives in the integration tier because it needs docker, which the unit tier
promises not to. It does *not* need the wiki, so it is the one test here that
still means something under the T3 minimal profile.
"""
import shutil
import subprocess

import pytest


def test_committed_wikitext_is_current(repo_root):
    if shutil.which("docker") is None:
        pytest.skip("no docker CLI; convert-docs.sh runs pandoc from a pinned image")

    proc = subprocess.run(
        ["bash", "scripts/convert-docs.sh", "--check"],
        cwd=str(repo_root),
        capture_output=True,
        text=True,
        timeout=900,
        check=False,
    )
    assert proc.returncode == 0, (
        "convert-docs.sh --check reports drift between docs/wiki/ and the "
        "committed docker/mediawiki/wiki-docs/. Regenerate with "
        "`scripts/convert-docs.sh` and commit the result.\n\n"
        f"{proc.stdout[-6000:]}\n{proc.stderr[-3000:]}"
    )
