#!/usr/bin/env python3
"""VERSIONS.yml — read it, scan the tree, and compare the two.

`VERSIONS.yml` is this fork's single answer to "what version are we". Before it
existed the tree gave four different answers (MW_VERSION said 1.43.5,
composer.lock said 5.1.4 with one package at 5.1.5, publiccode.yml said 5.1.3,
and the docs said 5.1.3), so nobody could answer "are we affected by CVE-X"
without reading the lockfile by hand.

Subcommands
    check              compare VERSIONS.yml against the tree; exit 1 on drift
    get <dotted.path>  print one declared value (used by the release-watch job)
    scan               print what the tree actually says, as JSON
    emit-extensions    print the `extensions:` block for VERSIONS.yml

Why the hand-rolled YAML reader: the same reason scripts/lib/read-manifest.py
has one. PyYAML is not present everywhere this repo runs python3 — notably the
mediawiki container — and adding a pip install to a gate would make the gate
itself a network dependency. PyYAML is used when available; the fallback
accepts only the block-mapping subset VERSIONS.yml uses and *raises* on
anything else rather than guessing, because a version file parsed wrongly is
worse than one that fails to parse: it would silently report agreement.

Exit: 0 consistent · 1 drift · 2 usage or parse error
"""
import json
import os
import re
import sys
from datetime import date

FROZEN_STALE_DAYS = 183  # ~6 months; a warning, never a failure — see check_frozen

# ─── The tiny YAML subset VERSIONS.yml is written in ────────────────


class VersionsError(Exception):
    pass


def parse_minimal(text, path="VERSIONS.yml"):
    """Parse indented block mappings with scalar leaves. Nothing else.

    Supported: comments, blank lines, `key:` opening a nested mapping,
    `key: scalar`, and `key: >-` folded blocks (the `why:` fields), which fold
    to space-joined text exactly as PyYAML does.

    Indentation is two spaces per level, exactly — every YAML file this repo
    commits is written that way and yamllint enforces it. Anything deeper than
    the open mapping allows is an error rather than a silently reparented key.

    Deliberately unsupported, each raising: lists, flow collections, literal
    `|` blocks (PyYAML keeps their newlines and folding them would quietly
    change the value), anchors, aliases, multi-document files, tabs.
    """
    root = {}
    stack = [(0, root)]  # (indent its children must sit at, mapping)
    lines = text.split("\n")
    i = 0
    while i < len(lines):
        raw, lineno = lines[i], i + 1
        i += 1
        if "\t" in raw.split("#", 1)[0]:
            raise VersionsError(f"{path}:{lineno}: tab in indentation")
        line = raw.rstrip()
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        indent = len(line) - len(line.lstrip(" "))
        if indent % 2:
            raise VersionsError(f"{path}:{lineno}: indentation is not a multiple of two")
        while len(stack) > 1 and indent < stack[-1][0]:
            stack.pop()
        if indent != stack[-1][0]:
            raise VersionsError(
                f"{path}:{lineno}: unexpected indentation — expected {stack[-1][0]} spaces, got {indent}")
        body = line.strip()
        if body.startswith("- "):
            raise VersionsError(f"{path}:{lineno}: lists are not supported in this file")
        if ":" not in body:
            raise VersionsError(f"{path}:{lineno}: not a `key: value` line: {body!r}")
        key, _, val = body.partition(":")
        key, val = key.strip(), _strip_comment(val.strip())
        parent = stack[-1][1]
        if val in (">-", ">"):
            block = []
            while i < len(lines) and (not lines[i].strip() or
                                      len(lines[i]) - len(lines[i].lstrip(" ")) > indent):
                if lines[i].strip():
                    block.append(lines[i].strip())
                i += 1
            parent[key] = " ".join(block)
        elif val == "":
            child = {}
            parent[key] = child
            stack.append((indent + 2, child))
        else:
            if val[:1] in "[{|&*":
                raise VersionsError(
                    f"{path}:{lineno}: unsupported YAML construct in value {val!r}")
            parent[key] = _scalar(val)
    return root


def _strip_comment(val):
    """Drop a trailing `# comment` that is not inside quotes."""
    quote = None
    for i, c in enumerate(val):
        if quote:
            if c == quote:
                quote = None
        elif c in "'\"":
            quote = c
        elif c == "#" and (i == 0 or val[i - 1] in " \t"):
            return val[:i].rstrip()
    return val


def _scalar(val):
    if len(val) >= 2 and val[0] == val[-1] == "'":
        return val[1:-1].replace("''", "'")
    if len(val) >= 2 and val[0] == val[-1] == '"':
        return val[1:-1].replace('\\"', '"').replace("\\\\", "\\")
    if val in ("null", "~"):
        return None
    return val


def load_versions(path):
    text = open(path, encoding="utf-8").read()
    try:
        import yaml
    except ImportError:
        return parse_minimal(text, path)
    data = yaml.safe_load(text)
    if not isinstance(data, dict):
        raise VersionsError(f"{path}: top level is not a mapping")
    # Normalise to strings so the two backends cannot disagree about typing:
    # PyYAML reads `php: 8.3` as a float, the fallback as the string "8.3", and
    # a comparison against a tag would then pass on one and fail on the other.
    return _stringify(data)


def _stringify(node):
    if isinstance(node, dict):
        return {str(k): _stringify(v) for k, v in node.items()}
    if node is None or isinstance(node, str):
        return node
    if isinstance(node, bool):
        return "true" if node else "false"
    return str(node)


# ─── What the tree actually says ────────────────────────────────────


def _read(root, rel):
    return open(os.path.join(root, rel), encoding="utf-8").read()


def scan(root):
    """Every version fact this repo states about itself, read from source."""
    obs = {}

    m = re.search(r"define\(\s*'MW_VERSION'\s*,\s*'([^']+)'", _read(root, "app/includes/Defines.php"))
    obs["mw_core"] = m.group(1) if m else None

    lock = json.loads(_read(root, "app/composer.lock"))
    packages = lock.get("packages", []) + lock.get("packages-dev", [])
    obs["composer"] = {p["name"]: p["version"] for p in packages}

    obs["extensions"] = {}
    ext_root = os.path.join(root, "app", "extensions")
    for name in sorted(os.listdir(ext_root)):
        manifest = os.path.join(ext_root, name, "extension.json")
        if not os.path.isfile(manifest):
            continue
        try:
            data = json.loads(open(manifest, encoding="utf-8").read())
        except ValueError as e:
            raise VersionsError(f"app/extensions/{name}/extension.json: {e}") from e
        # 24 bundled extensions carry no `version` at all: they ship with core
        # and are versioned by it. Recording that as the literal `core` keeps
        # them in the inventory, so upstream *adding* a version is drift we see
        # rather than a field we silently ignore.
        obs["extensions"][name] = str(data.get("version") or "core")

    compose = _read(root, "docker-compose.yml")
    obs["images"] = sorted(set(re.findall(r"^\s*image:\s*(\S+)\s*$", compose, re.M)))

    m = re.search(r"^FROM\s+opensearchproject/opensearch:(\S+)", _read(root, "docker/opensearch/Dockerfile"), re.M)
    obs["opensearch_base"] = m.group(1) if m else None

    hay = _read(root, "docker/haystack/Dockerfile")
    m = re.search(r'"haystack-ai==([^"]+)"', hay)
    obs["haystack"] = m.group(1) if m else None
    m = re.search(r"^FROM\s+python:(\d+\.\d+)", hay, re.M)
    obs["python"] = m.group(1) if m else None

    m = re.search(r"^softwareVersion:\s*(\S+)", _read(root, "publiccode.yml"), re.M)
    obs["publiccode"] = _scalar(m.group(1)) if m else None

    # The frozen packages are stripped from composer.lock by docker/setup.sh at
    # install time. Reading the strip list back is what ties the declaration in
    # VERSIONS.yml to the code that implements it.
    setup = _read(root, "docker/setup.sh")
    obs["stripped"] = sorted(set(re.findall(r'"(hallowelt/[\w-]+|mediawiki/[\w-]+)"', setup)))

    return obs


# ─── The gate ───────────────────────────────────────────────────────


class Report:
    def __init__(self):
        self.failures = []
        self.warnings = []
        self.lines = []

    def ok(self, msg):
        self.lines.append(f"  ok    {msg}")

    def warn(self, msg, detail=""):
        self.warnings.append(msg)
        self.lines.append(f"  warn  {msg}")
        if detail:
            self.lines.append(detail)

    def fail(self, msg, detail=""):
        self.failures.append(msg)
        self.lines.append(f"  DRIFT {msg}")
        if detail:
            self.lines.append(detail)


def _declared(decl, key, rep):
    val = decl.get(key)
    if val is None:
        rep.fail(f"VERSIONS.yml declares no '{key}'")
    return val


def check(decl, obs):
    rep = Report()
    exceptions = decl.get("exceptions") or {}
    if not isinstance(exceptions, dict):
        raise VersionsError("VERSIONS.yml: 'exceptions' must be a mapping")

    # 1 — MediaWiki core. The version is compiled into the tree, not declared
    #     in a manifest, so this is the one number an upgrade cannot forget.
    mw = _declared(decl, "mw_core", rep)
    if mw and obs["mw_core"] != mw:
        rep.fail(f"mw_core: VERSIONS.yml says {mw}, app/includes/Defines.php says {obs['mw_core']}")
    elif mw:
        rep.ok(f"mw_core {mw} matches MW_VERSION in app/includes/Defines.php")

    # 2 — every bluespice/* package in the lockfile.
    bs = _declared(decl, "bluespice", rep)
    if bs:
        off = {n: v for n, v in obs["composer"].items()
               if n.startswith("bluespice/") and v != bs and exceptions.get(n) != v}
        if off:
            rep.fail(
                f"{len(off)} bluespice/* package(s) in app/composer.lock are neither {bs} nor a declared exception",
                "\n".join(f"          {n} = {v}" for n, v in sorted(off.items())))
        else:
            n_bs = sum(1 for n in obs["composer"] if n.startswith("bluespice/"))
            rep.ok(f"{n_bs} bluespice/* packages in composer.lock are {bs} or a declared exception")

    # 3 — the installed extension inventory. BlueSpice* directories follow the
    #     baseline so a BlueSpice bump stays a two-line edit; everything else is
    #     declared one by one, because those versions move independently and a
    #     silent change is exactly what this file exists to surface.
    declared_ext = decl.get("extensions") or {}
    if not isinstance(declared_ext, dict):
        raise VersionsError("VERSIONS.yml: 'extensions' must be a mapping")
    bs_off, mismatched, undeclared, vanished = {}, {}, {}, {}
    for name, ver in obs["extensions"].items():
        if name.startswith("BlueSpice"):
            if bs and ver != bs and exceptions.get(name) != ver:
                bs_off[name] = ver
            continue
        if name not in declared_ext:
            undeclared[name] = ver
        elif declared_ext[name] != ver:
            mismatched[name] = (declared_ext[name], ver)
    for name, ver in declared_ext.items():
        if name not in obs["extensions"]:
            vanished[name] = ver
    if bs_off:
        rep.fail(
            f"{len(bs_off)} BlueSpice extension(s) do not carry the {bs} baseline",
            "\n".join(f"          app/extensions/{n}/extension.json = {v}" for n, v in sorted(bs_off.items())))
    if mismatched:
        rep.fail(
            f"{len(mismatched)} extension version(s) differ from VERSIONS.yml",
            "\n".join(f"          {n}: declared {d}, installed {i}" for n, (d, i) in sorted(mismatched.items())))
    if undeclared:
        rep.fail(
            f"{len(undeclared)} installed extension(s) are not declared in VERSIONS.yml",
            "\n".join(f"          {n} = {v}" for n, v in sorted(undeclared.items())))
    if vanished:
        rep.fail(
            f"{len(vanished)} declared extension(s) are not installed",
            "\n".join(f"          {n} = {v}" for n, v in sorted(vanished.items())))
    if not (bs_off or mismatched or undeclared or vanished):
        rep.ok(f"{len(obs['extensions'])} installed extensions match VERSIONS.yml "
               f"({len(obs['extensions']) - len(declared_ext)} BlueSpice on the baseline, "
               f"{len(declared_ext)} declared individually)")

    # 4 — publiccode.yml. This is what the openCode catalogue is told, and it
    #     was the furthest out of date of the four sources.
    if bs:
        if obs["publiccode"] != bs:
            rep.fail(f"publiccode.yml softwareVersion is {obs['publiccode']}, expected {bs}")
        else:
            rep.ok(f"publiccode.yml softwareVersion is {bs}")

    # 5 — the service versions, read from the compose file and the Dockerfiles
    #     rather than from a comment.
    for key, pattern, where in (
        ("mariadb", r"^mariadb:(\S+)$", "docker-compose.yml"),
        ("opensearch", r"^hdp-opensearch:(\S+)$", "docker-compose.yml"),
    ):
        want = _declared(decl, key, rep)
        if not want:
            continue
        found = [m.group(1) for m in (re.match(pattern, i) for i in obs["images"]) if m]
        if not found:
            rep.fail(f"{key}: no image matching {pattern} in {where}")
        elif any(v != want for v in found):
            rep.fail(f"{key}: VERSIONS.yml says {want}, {where} says {', '.join(sorted(set(found)))}")
        else:
            rep.ok(f"{key} {want} matches {where}")

    want = decl.get("opensearch")
    if want and obs["opensearch_base"] != want:
        rep.fail(f"opensearch: docker/opensearch/Dockerfile builds FROM "
                 f"opensearchproject/opensearch:{obs['opensearch_base']}, VERSIONS.yml says {want}")
    elif want:
        rep.ok(f"opensearch {want} matches docker/opensearch/Dockerfile")

    # PHP is not a tag of its own anywhere — it is encoded in the wikimedia
    # image names (bookworm-php83-fpm, bookworm-php83-jobrunner). Read it back
    # from every image that states one, so a half-finished bump is caught.
    php = _declared(decl, "php", rep)
    if php:
        want_tag = "php" + php.replace(".", "")
        stated = sorted({m.group(1) for m in (re.search(r"(php\d{2,3})", i) for i in obs["images"]) if m})
        if not stated:
            rep.fail("php: no image in docker-compose.yml states a PHP version")
        elif stated != [want_tag]:
            rep.fail(f"php: VERSIONS.yml says {php} ({want_tag}), docker-compose.yml images say {', '.join(stated)}")
        else:
            rep.ok(f"php {php} matches the {want_tag} images in docker-compose.yml")

    for key, label in (("haystack", "haystack-ai pin in docker/haystack/Dockerfile"),
                       ("python", "python base image in docker/haystack/Dockerfile")):
        want = _declared(decl, key, rep)
        if not want:
            continue
        if obs[key] != want:
            rep.fail(f"{key}: VERSIONS.yml says {want}, {label} says {obs[key]}")
        else:
            rep.ok(f"{key} {want} matches the {label}")

    check_frozen(decl, obs, rep)
    return rep


def check_frozen(decl, obs, rep):
    """Track C — the two packages no monitoring will ever see.

    hallowelt/chatbot and mediawiki/page-header point at a private GitLab we
    cannot reach, so docker/setup.sh strips them from composer.lock and the
    vendored source is used instead. Their security posture is frozen at
    whatever was vendored: Renovate cannot see them, the release-watch job
    cannot see them, and `composer audit` cannot see them either.

    All this can honestly check is that the declaration still matches the code
    (the package is in the lockfile and named in the strip list) and that a
    human looked at it recently. The staleness result is a warning and not a
    failure on purpose: a date passing in the night is not a reason to turn
    everyone's pipeline red, and a gate that fails for a reason nobody can fix
    by editing code is a gate people learn to bypass.
    """
    frozen = decl.get("frozen") or {}
    if not isinstance(frozen, dict):
        raise VersionsError("VERSIONS.yml: 'frozen' must be a mapping")
    if not frozen:
        rep.warn("VERSIONS.yml declares no frozen packages — see SECURITY.md, there are two")
        return
    for name, meta in sorted(frozen.items()):
        if not isinstance(meta, dict):
            rep.fail(f"frozen/{name}: entry is not a mapping")
            continue
        # Presence in the lockfile is NOT asserted, and the reason is the one
        # thing about these two packages that surprises everybody:
        # docker/setup.sh rewrites app/composer.lock *in place* to remove them
        # before running composer. So the file contains them on a pristine
        # checkout and does not contain them on any tree that has been
        # installed — and both are correct.
        #
        # Requiring presence made `./scripts/check.sh` turn red after every
        # install, for a reason the operator cannot fix by editing anything.
        # Found on the clean box, running the gate after T4.
        in_lock = name in obs["composer"]
        declared_version = meta.get("version")
        if in_lock and declared_version and obs["composer"][name] != declared_version:
            rep.fail(f"frozen/{name}: VERSIONS.yml says {declared_version}, "
                     f"app/composer.lock says {obs['composer'][name]}")
        if name not in obs["stripped"]:
            rep.fail(f"frozen/{name}: declared frozen but docker/setup.sh does not strip it from composer.lock",
                     "          a frozen package left in the lockfile makes composer install reach a "
                     "private GitLab and fail")
        for field in ("vendored_from", "owner", "last_reviewed", "why"):
            if not meta.get(field):
                rep.fail(f"frozen/{name}: missing '{field}'")
        reviewed = meta.get("last_reviewed")
        if reviewed:
            try:
                y, m, d = (int(x) for x in str(reviewed).split("-"))
                age = (date.today() - date(y, m, d)).days
            except ValueError:
                rep.fail(f"frozen/{name}: last_reviewed '{reviewed}' is not a YYYY-MM-DD date")
                continue
            if age > FROZEN_STALE_DAYS:
                rep.warn(f"frozen/{name}: last reviewed {age} days ago ({reviewed}) — "
                         f"diff it against the vendored source and update the date")
            else:
                where = "still in composer.lock" if in_lock else "already stripped from composer.lock"
                rep.ok(f"frozen/{name} reviewed {age} days ago, named in setup.sh's strip list, {where}")


def emit_extensions(obs):
    """The `extensions:` block, ready to paste into VERSIONS.yml.

    BlueSpice* directories are omitted: they follow the `bluespice` baseline,
    which is what keeps a BlueSpice bump to a two-line edit.
    """
    out = ["extensions:"]
    for name, ver in sorted(obs["extensions"].items()):
        if name.startswith("BlueSpice"):
            continue
        out.append(f"  {name}: {'core' if ver == 'core' else chr(39) + ver + chr(39)}")
    return "\n".join(out)


# ─── CLI ────────────────────────────────────────────────────────────


def repo_root():
    here = os.path.dirname(os.path.abspath(__file__))
    return os.path.abspath(os.path.join(here, "..", ".."))


def main(argv):
    root = os.environ.get("HDP_REPO_ROOT") or repo_root()
    path = os.path.join(root, "VERSIONS.yml")
    cmd = argv[0] if argv else "check"

    if cmd == "scan":
        print(json.dumps(scan(root), indent=2, sort_keys=True))
        return 0
    if cmd == "emit-extensions":
        print(emit_extensions(scan(root)))
        return 0

    decl = load_versions(path)

    if cmd == "get":
        if len(argv) < 2:
            print("usage: versions.py get <dotted.path>", file=sys.stderr)
            return 2
        node = decl
        for part in argv[1].split("."):
            if not isinstance(node, dict) or part not in node:
                print(f"versions.py: no such key '{argv[1]}' in VERSIONS.yml", file=sys.stderr)
                return 2
            node = node[part]
        print(node if not isinstance(node, dict) else json.dumps(node))
        return 0

    if cmd != "check":
        print(__doc__, file=sys.stderr)
        return 2

    obs = scan(root)
    rep = check(decl, obs)
    print("\n".join(rep.lines))
    print("")
    if rep.failures:
        print(f"  {len(rep.failures)} inconsistenc{'y' if len(rep.failures) == 1 else 'ies'} between "
              f"VERSIONS.yml and the tree.")
        print("  VERSIONS.yml is the declaration; the tree is the fact. Fix whichever is wrong,")
        print("  and see docs/dev/upgrade-runbook.md step 7 — declaring the new version is part")
        print("  of the upgrade, not paperwork after it.")
        return 1
    print(f"  VERSIONS.yml agrees with the tree ({len(rep.lines)} checks"
          f"{f', {len(rep.warnings)} warning' if rep.warnings else ''}"
          f"{'s' if len(rep.warnings) > 1 else ''}).")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main(sys.argv[1:]))
    except VersionsError as exc:
        print(f"versions.py: {exc}", file=sys.stderr)
        sys.exit(2)
    except FileNotFoundError as exc:
        print(f"versions.py: {exc}", file=sys.stderr)
        sys.exit(2)
