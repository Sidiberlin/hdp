#!/bin/bash
# Helper script to load useful tools
# Copyright: 2024
# License: GPLv3
#
# HDP: pinned to release tags with SHA-256 verification.
#
# Upstream fetched both binaries from `releases/latest/download/` with no
# integrity check and `chmod +x` regardless of the outcome. Three problems,
# all of which fire on every user install and — once the patch-integrity CI
# tier lands — on every CI run:
#
#   1. `latest` is a moving target. The artifact can change under you with no
#      signal, and nothing records which build a given install actually got.
#   2. Nothing verifies what came back. An unauthenticated fetch of a binary
#      that is then marked executable trusts the network end to end.
#   3. `wget -q -O "$target"` truncates the target *before* it connects, so a
#      failed download leaves a 0-byte file that the next line marks
#      executable — and upstream's unconditional `chmod +x` hides it.
#
# Fixed by pinning to tags, downloading to a temp file, verifying the digest,
# and only then installing. A mismatch leaves any previously installed copy
# untouched rather than replacing it with an unverified one.
#
# To bump: change the tag, run the fetch by hand, `sha256sum` the result, and
# update the digest in the same commit. Never update the tag alone.
#
# Recorded 2026-08-01. Both artifacts are PHP phar archives.
#   mediawiki-adm            1.2.3  1622758 bytes  (released 2026-06-30)
#   parallel-runjobs-service 2.0.1  1405982 bytes  (released 2025-01-08)

set -uo pipefail

targetdir="_bluespice/tools"

MEDIAWIKI_ADM_TAG="1.2.3"
MEDIAWIKI_ADM_SHA256="1e5609d76f0c3ade1a33fd0bf204463747c8b99d86a49004ce00d50d8886666f"

PARALLEL_RUNJOBS_TAG="2.0.1"
PARALLEL_RUNJOBS_SHA256="ec8ea7e8a79242baba862448bcba952376267c0db19785d6266ab9a01a29e241"

_rc=0

# Fetch one pinned binary and install it only if the digest matches.
# Args: <repo> <tag> <artifact name> <expected sha256>
fetch_pinned() {
	_repo="$1"
	_tag="$2"
	_name="$3"
	_want="$4"

	_url="https://github.com/${_repo}/releases/download/${_tag}/${_name}"
	_tmp="${targetdir}/.${_name}.download.$$"

	if ! wget -q -O "$_tmp" "$_url"; then
		echo "10-add_tools.sh: ERROR: download failed: $_url" >&2
		rm -f "$_tmp"
		_rc=1
		return 1
	fi

	_got="$(sha256sum "$_tmp" | cut -d' ' -f1)"
	if [ "$_got" != "$_want" ]; then
		echo "10-add_tools.sh: ERROR: SHA-256 mismatch for ${_name} @ ${_tag}" >&2
		echo "  expected: ${_want}" >&2
		echo "  got     : ${_got}" >&2
		echo "  url     : ${_url}" >&2
		echo "  Refusing to install. Either the release was re-cut upstream or" >&2
		echo "  the download was tampered with. Verify by hand before bumping" >&2
		echo "  the digest in this file." >&2
		rm -f "$_tmp"
		_rc=1
		return 1
	fi

	chmod +x "$_tmp"
	mv -f "$_tmp" "${targetdir}/${_name}"
	echo "10-add_tools.sh: ${_name} ${_tag} verified and installed."
}

if [ ! -d "$targetdir" ]
then
	mkdir -p "$targetdir"
fi

if ! command -v sha256sum >/dev/null 2>&1; then
	echo "10-add_tools.sh: ERROR: sha256sum not found; refusing to install" >&2
	echo "  unverified binaries. Install coreutils in the image." >&2
	exit 1
fi

fetch_pinned "hallowelt/misc-mediawiki-adm" \
	"$MEDIAWIKI_ADM_TAG" "mediawiki-adm" "$MEDIAWIKI_ADM_SHA256"

fetch_pinned "hallowelt/misc-parallel-runjobs-service" \
	"$PARALLEL_RUNJOBS_TAG" "parallel-runjobs-service" "$PARALLEL_RUNJOBS_SHA256"

# Exit non-zero on any failure. The current caller (_bluespice/pre-autoload-dump.sh)
# runs each script bare and ignores the status, so this does not abort the
# install today — which is the right outcome, because nothing in the HDP stack
# consumes either binary. It is a truthful signal for anything that does check.
exit "$_rc"
