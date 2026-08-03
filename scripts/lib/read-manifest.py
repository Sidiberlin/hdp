#!/usr/bin/env python3
"""Read docker/patches/*.yaml and emit one \x1f-delimited record per patch.

Shared by scripts/verify-patches.sh and scripts/apply-patches.sh so the two
cannot disagree about what the manifest says.

Why not just `import yaml`: apply-patches.sh runs inside the mediawiki
container (docker-registry.wikimedia.org/dev/bookworm-php83-fpm), which ships
python3 but no PyYAML, and adding a pip install to the install path would mean
a network fetch during setup — a new failure mode on the one code path that
most needs to be reliable.

So: use PyYAML when it is available (host, CI), and otherwise fall back to a
deliberately strict reader for the small subset of YAML these sidecars use —
flat `key: value` scalars plus one `key: >-` folded block. The fallback refuses
anything it does not recognise instead of guessing, because a manifest parsed
wrongly is worse than one that fails to parse: it would silently report patches
as fine.

\x1f (ASCII unit separator) rather than TAB, because `IFS=$'\t' read` in bash
collapses runs of tabs — tab is IFS whitespace — which silently drops empty
fields and shifts every column after them.
"""
import glob
import os
import sys

FIELDS = ("id", "class", "mode", "target", "patch", "anchor", "marker",
          "anti", "stale", "title", "why", "applied_by", "group",
          "upstream_version", "hunks", "regex")

REQUIRED = ("id", "title", "class", "mode", "target", "stale", "why")
VALID_MODES = {"insert", "diff", "create", "delete"}


def _strip_quotes(v):
    """Unquote a YAML scalar the way PyYAML would.

    The two quote styles are not interchangeable and getting this wrong is
    silent: a double-quoted anchor like "\\$foo" means the regex \\$foo, and a
    parser that only strips the quotes yields \\\\$foo, which matches nothing.
    That produced a manifest the container would have read differently from CI.

    Single-quoted: literal, except '' means one '.
    Double-quoted: C-style escapes.
    """
    v = v.strip()
    if len(v) >= 2 and v[0] == v[-1] == "'":
        return v[1:-1].replace("''", "'")
    if len(v) >= 2 and v[0] == v[-1] == '"':
        body = v[1:-1]
        out, i = [], 0
        while i < len(body):
            c = body[i]
            if c == "\\" and i + 1 < len(body):
                nxt = body[i + 1]
                out.append({"n": "\n", "t": "\t", "r": "\r", "0": "\0",
                            "\\": "\\", '"': '"', "/": "/"}.get(nxt, "\\" + nxt))
                i += 2
            else:
                out.append(c)
                i += 1
        return "".join(out)
    return v


def _normalise(key, value):
    """Match PyYAML's typing for the fields the callers branch on.

    Only `stale` is compared as a value by the shell scripts, and they accept
    both cases — but emitting a different string from each backend is exactly
    the kind of drift that makes a fallback untrustworthy.
    """
    if key == "stale" and isinstance(value, str):
        low = value.strip().lower()
        if low in ("true", "yes", "on"):
            return True
        if low in ("false", "no", "off"):
            return False
    return value


def parse_minimal(path):
    """Parse the flat subset of YAML these sidecars use.

    Supported: comments, blank lines, `key: scalar`, and `key: >-` followed by
    an indented block. Anything else raises.
    """
    data = {}
    lines = open(path, encoding="utf-8").read().split("\n")
    i = 0
    while i < len(lines):
        raw = lines[i]
        i += 1
        line = raw.rstrip()
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        if raw[:1] in (" ", "\t"):
            raise ValueError(f"{path}: unexpected indented line outside a block: {line!r}")
        if ":" not in line:
            raise ValueError(f"{path}: not a key: value line: {line!r}")
        key, _, val = line.partition(":")
        key = key.strip()
        val = val.strip()
        if val in (">-", ">", "|", "|-"):
            block = []
            while i < len(lines) and (not lines[i].strip() or lines[i][:1] in (" ", "\t")):
                if lines[i].strip():
                    block.append(lines[i].strip())
                i += 1
            data[key] = _normalise(key, " ".join(block))
        else:
            data[key] = _normalise(key, _strip_quotes(val))
    return data


def load(path):
    try:
        import yaml
    except ImportError:
        return parse_minimal(path), "minimal"
    return yaml.safe_load(open(path, encoding="utf-8")), "pyyaml"


def main():
    if len(sys.argv) < 2:
        print("usage: read-manifest.py <manifest-dir>", file=sys.stderr)
        return 2
    manifest_dir = sys.argv[1]
    rows, errors, seen = [], [], set()
    backend = None

    files = sorted(glob.glob(os.path.join(manifest_dir, "*.yaml")))
    if not files:
        print(f"read-manifest: no sidecars in {manifest_dir}", file=sys.stderr)
        return 2

    for f in files:
        try:
            d, backend = load(f)
        except Exception as e:
            errors.append(f"{f}: cannot parse: {e}")
            continue
        if not isinstance(d, dict):
            errors.append(f"{f}: top level is not a mapping")
            continue
        for k in REQUIRED:
            if k not in d:
                errors.append(f"{f}: missing required key '{k}'")
        if d.get("mode") not in VALID_MODES:
            errors.append(f"{f}: mode '{d.get('mode')}' is not one of {sorted(VALID_MODES)}")
        if d.get("mode") == "insert" and not d.get("marker"):
            errors.append(f"{f}: mode 'insert' requires a marker")
        if d.get("mode") == "diff" and not d.get("patch"):
            errors.append(f"{f}: mode 'diff' requires a patch path")
        if d.get("id") in seen:
            errors.append(f"{f}: duplicate id '{d.get('id')}'")
        seen.add(d.get("id"))
        rows.append("\x1f".join(
            str(d.get(k, "")).replace("\x1f", " ").replace("\n", " ") for k in FIELDS))

    if errors:
        for e in errors:
            print("MANIFEST_ERROR: " + e, file=sys.stderr)
        return 2

    if os.environ.get("HDP_MANIFEST_DEBUG"):
        print(f"read-manifest: {len(rows)} sidecars via {backend}", file=sys.stderr)
    print("\n".join(rows))
    return 0


if __name__ == "__main__":
    sys.exit(main())
