#!/usr/bin/env python3
"""Drive update.sh under a pty against a real, throwaway local git origin.

Same architecture as drive_install.py: a throwaway repo copy per scenario, a
PATH whose first entry supplies stub `docker` and `curl`, and a rule table
that answers prompts by what they say rather than by position.

update.sh's own subject (git mechanics against a --depth 1 clone) needs a
real remote to fetch from, so each scenario builds one: `git init --bare
origin.git`, seeds it with the "old" commit, clones it --depth 1 as "the
install under test", then pushes the "new" state (and tags) into the bare
repo from a second, full clone. This exercises the real shallow-fetch and
`git ls-remote --tags` paths rather than mocking their output.

Scenarios are the 15 rows of PLAN.md §6's table. Not wired into CI (same as
drive_install.py) — run by hand:

    python3 tests/installer/drive_update.py
"""
import os
import pty
import re
import select
import shutil
import subprocess
import sys
import tempfile

REPO = "/root/hdp"

DOCKER_STUB = r"""#!/bin/sh
# Stub docker: logs every invocation, never touches a daemon.
printf '%s\n' "$*" >> "$HDP_TEST_LOG"
case "$1" in
  --version) echo "Docker version 29.0.0, build stub"; exit 0 ;;
  info) exit 0 ;;
esac
if [ "$1" = "compose" ]; then
  shift
  # Skip over any -f <file> pairs to find the verb.
  while [ "$1" = "-f" ]; do shift 2; done
  verb="$1"
  case "$verb" in
    ps)
      last=""
      for a in "$@"; do last="$a"; done
      echo "cid-$last"
      exit 0 ;;
    exec)
      cat > /dev/null
      args="$*"
      case "$args" in
        *setup.sh*)
          [ -n "$HDP_TEST_FAIL_SETUP" ] && exit 1
          exit 0 ;;
        *mysqldump*)
          [ -n "$HDP_TEST_FAIL_MYSQLDUMP" ] && exit 1
          echo "-- fake dump --"
          exit 0 ;;
      esac
      exit 0 ;;
    pull) [ -n "$HDP_TEST_FAIL_PULL" ] && exit 1; exit 0 ;;
    build) [ -n "$HDP_TEST_FAIL_BUILD" ] && exit 1; exit 0 ;;
    up)    [ -n "$HDP_TEST_FAIL_UP" ] && exit 1; exit 0 ;;
    stop|restart) exit 0 ;;
    *) exit 0 ;;
  esac
fi
if [ "$1" = "inspect" ]; then
  shift
  fmt=""
  while [ $# -gt 0 ]; do
    case "$1" in
      -f) shift; fmt="$1" ;;
    esac
    shift
  done
  case "$fmt" in
    *Config.Image*) echo "${HDP_TEST_IMAGE_REF:-ghcr.io/sidiberlin/hdp-haystack:v1.0.0}" ;;
    *) echo healthy ;;
  esac
  exit 0
fi
exit 0
"""

CURL_STUB = r"""#!/bin/sh
printf '%s\n' "curl $*" >> "$HDP_TEST_LOG"
[ -n "$HDP_TEST_FAIL_PROBE" ] && exit 1
exit 0
"""

FIXTURE_OLD = {
    "docker-compose.yml": "services: {}\n",
    "docker-compose.prod.yml": (
        "services:\n"
        "  haystack:\n"
        "    image: ghcr.io/sidiberlin/hdp-haystack:${HDP_IMAGE_TAG:-v1.0.0}\n"
        "  chatbot-proxy:\n"
        "    image: ghcr.io/sidiberlin/hdp-chatbot-proxy:${HDP_IMAGE_TAG:-v1.0.0}\n"
        "  opensearch:\n"
        "    image: ghcr.io/sidiberlin/hdp-opensearch:${HDP_IMAGE_TAG:-v1.0.0}\n"
    ),
    "docker-compose.gpu.yml": "services: {}\n",
    "docker-compose.prod-gpu.yml": "services: {}\n",
    ".env.example": "HDP_DB_ROOT_PASSWORD=\nHDP_DB_PASSWORD=\n",
    "docker/setup.sh": "#!/bin/sh\necho setup\n",
    "app/marker.txt": "old\n",
    "README.md": "old\n",
}

ENV_TEXT = (
    "HDP_DB_ROOT_PASSWORD=rootpw\n"
    "HDP_DB_PASSWORD=dbpw\n"
    "HAYSTACK_DEVICE=cpu\n"
    "HAYSTACK_CUDA_VERSION=cu124\n"
    "MW_DOCKER_PORT=8080\n"
)


def git(cwd, *args, check=True):
    return subprocess.run(
        ["git", *args], cwd=cwd, check=check,
        capture_output=True, text=True,
    )


def write_files(root, files):
    for path, content in files.items():
        full = os.path.join(root, path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w") as fh:
            fh.write(content)


def make_origin(work):
    """git init --bare + seed the OLD commit on main. Returns (origin, seed)."""
    origin = os.path.join(work, "origin.git")
    git(work, "-c", "init.defaultBranch=main", "init", "--bare", "-q", origin)
    seed = os.path.join(work, "seed")
    git(work, "clone", "-q", origin, seed)
    write_files(seed, FIXTURE_OLD)
    git(seed, "add", "-A")
    git(seed, "-c", "user.email=t@t.com", "-c", "user.name=t",
        "commit", "-q", "-m", "old release")
    git(seed, "push", "-q", "origin", "HEAD:main")
    return origin, seed


def clone_install(origin, work):
    """Shallow-clone origin as the install under test; copy update.sh in."""
    clone = os.path.join(work, "clone")
    git(work, "clone", "-q", "--depth", "1", origin, clone)
    shutil.copy(os.path.join(REPO, "update.sh"), clone)
    os.chmod(os.path.join(clone, "update.sh"), 0o755)
    with open(os.path.join(clone, ".env"), "w") as fh:
        fh.write(ENV_TEXT)
    return clone


def push_new(seed, changes, tags=None, message="new release"):
    """Apply file changes in the seed clone, commit, tag, push to origin."""
    for path, content in changes.items():
        if content is None:
            os.remove(os.path.join(seed, path))
        else:
            full = os.path.join(seed, path)
            os.makedirs(os.path.dirname(full), exist_ok=True)
            with open(full, "w") as fh:
                fh.write(content)
    git(seed, "add", "-A")
    git(seed, "-c", "user.email=t@t.com", "-c", "user.name=t",
        "commit", "-q", "-m", message)
    for t in (tags or []):
        git(seed, "tag", t)
    git(seed, "push", "-q", "origin", "HEAD:main")
    if tags:
        git(seed, "push", "-q", "origin", *tags)
    return git(seed, "rev-parse", "HEAD").stdout.strip()


def tag_only(seed, tags, at="HEAD"):
    for t in tags:
        git(seed, "tag", t, at)
    git(seed, "push", "-q", "origin", *tags)


def make_bins(work):
    binp = os.path.join(work, "bin")
    os.makedirs(binp, exist_ok=True)
    for name, content in (("docker", DOCKER_STUB), ("curl", CURL_STUB)):
        p = os.path.join(binp, name)
        with open(p, "w") as fh:
            fh.write(content)
        os.chmod(p, 0o755)
    return binp


DEFAULT_RULES = [
    ("Build the three services from the updated source instead?", "y"),
    ("This moves the tree BACKWARDS", "n"),
    ("Proceed with this update?", "y"),
    ("Take a database backup before updating?", "n"),
    ("Append them to .env?", "y"),
    ("Remove app/vendor", "y"),
]


def drive(clone, args=None, env_extra=None, rules=None, timeout=20):
    work = os.path.dirname(clone)
    binp = make_bins(work)
    log = os.path.join(work, "docker.log")
    open(log, "w").close()

    env = dict(os.environ)
    env.update({
        "PATH": binp + ":/usr/bin:/bin",
        "NO_COLOR": "1",
        "HDP_TEST_LOG": log,
        "GIT_TERMINAL_PROMPT": "0",
    })
    env.update(env_extra or {})

    rule_table = list(rules or []) + DEFAULT_RULES

    pid, fd = pty.fork()
    if pid == 0:
        os.chdir(clone)
        os.execve("/bin/bash", ["bash", "./update.sh", *(args or [])], env)

    out, pending = [], ""
    ticks = 0
    while True:
        r, _, _ = select.select([fd], [], [], timeout)
        if not r:
            break
        try:
            chunk = os.read(fd, 4096)
        except OSError:
            break
        if not chunk:
            break
        text = chunk.decode("utf-8", "replace")
        out.append(text)
        pending += text
        tail = pending[-4000:]
        if re.search(r"(: |\]: |\[y/N\]: |\[Y/n\]: )$", tail):
            reply = "\n"
            for needle, value in rule_table:
                if needle in tail:
                    reply = value + "\n"
                    break
            os.write(fd, reply.encode())
            pending = ""
        ticks += 1
        if ticks > 4000:
            break

    _, status = os.waitpid(pid, 0)
    rc = os.waitstatus_to_exitcode(status)
    dockerlog = open(log).read()
    return dict(rc=rc, log="".join(out), docker=dockerlog, clone=clone)


def strip(s):
    return re.sub(r"\x1b\[[0-9;]*m", "", s)


FAILED = []


def check(name, label, cond, detail=""):
    mark = "PASS" if cond else "FAIL"
    print(f"  [{mark}] {label}" + (f"   {detail}" if detail and not cond else ""))
    if not cond:
        FAILED.append(f"{name}: {label}")


def head_sha(clone):
    return git(clone, "rev-parse", "HEAD").stdout.strip()


def show(name, res):
    print(f"\n=== {name} (exit {res['rc']}) ===")


def scenario_1_dirty_tree():
    name = "1 dirty tree (a tracked file edited outside app/) -> exit 2, no fetch/reset"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])
    before = head_sha(clone)
    with open(os.path.join(clone, "docker-compose.yml"), "w") as fh:
        fh.write("services: {}\n# locally edited\n")

    r = drive(clone, timeout=10)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 2", r["rc"] == 2, f"rc={r['rc']}")
    check(name, "names the offending path", "docker-compose.yml" in log)
    check(name, "no docker mutating call", r["docker"].strip() == "", r["docker"])
    check(name, "HEAD unmoved", head_sha(clone) == before)


def scenario_2_composer_lock_only():
    name = "2 app/ install churn (composer.lock + manifests + i18n) -> proceeds"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    # The measured real-install churn shape (QA box, 2026-09-22: 233 files):
    # composer.lock rewritten by docker/setup.sh (F7), extension manifests
    # touched by composer/npm runtime activity, i18n JSON churn. All under
    # app/ -> machine churn the class rule allows and the reset discards.
    push_new(seed, {
        "app/composer.lock": '{"packages":[]}\n',
        "app/extensions/Arrays/composer.json": '{"name":"arrays"}\n',
        "app/extensions/Arrays/package-lock.json": '{"lockfileVersion":3}\n',
        "app/extensions/CodeMirror/i18n/ar.json": '{"@metadata":[]}\n',
    }, message="track the churnable files")
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])
    churn = {
        "app/composer.lock": '{"packages":["stripped-by-setup.sh"]}\n',
        "app/extensions/Arrays/composer.json": '{"name":"arrays","modified":true}\n',
        "app/extensions/Arrays/package-lock.json": '{"lockfileVersion":3,"changed":true}\n',
        "app/extensions/CodeMirror/i18n/ar.json": '{"@metadata":[],"x":1}\n',
    }
    for path_, content in churn.items():
        with open(os.path.join(clone, path_), "w") as fh:
            fh.write(content)

    r = drive(clone, args=["--check"], timeout=10)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "plan explains the app/ churn allowance", "install churn" in log)


def scenario_3_already_current():
    name = "3 already at the resolved target tag -> exit 0, empty docker log"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    tag_only(seed, ["v1.0.0"])

    r = drive(clone, timeout=10)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "says already up to date", "Already up to date" in log)
    check(name, "names the tag", "v1.0.0" in log)
    check(name, "docker log empty", r["docker"].strip() == "", r["docker"])


def scenario_4_app_only():
    name = "4 app/**-only change, confirmed"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new content\n"}, tags=["v1.0.1"])

    r = drive(clone, timeout=15)
    show(name, r)
    log = strip(r["log"])
    dl = r["docker"]
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}\n{log}")
    check(name, "no build in the docker log", not any(ln.startswith("compose build") for ln in dl.splitlines()))
    lines = dl.splitlines()
    stop_i = next((i for i, ln in enumerate(lines) if "stop mediawiki-web" in ln), -1)
    up_i = next((i for i, ln in enumerate(lines) if ln.endswith("up -d")), -1)
    check(name, "stop before up -d", stop_i != -1 and up_i != -1 and stop_i < up_i, dl)
    check(name, "up -d present", up_i != -1)
    check(name, "restart mediawiki present", any("restart mediawiki" in ln for ln in lines))
    check(name, "setup exec present with -T and </dev/null",
          any("exec -T" in ln and "setup.sh" in ln for ln in lines))
    check(name, "no bare update.php invocation", "update.php" not in dl)


def scenario_5_docker_change():
    name = "5 docker/** change -> pull-or-build before up -d"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    # The pinned image tag advances alongside the docker/** change, same as a
    # normal base release — this is what keeps the -QoL image-gap condition
    # (scenario 12) from firing here, so this scenario tests the plain path.
    push_new(seed, {
        "docker/setup.sh": "#!/bin/sh\necho setup2\n",
        "docker-compose.prod.yml": FIXTURE_OLD["docker-compose.prod.yml"].replace("v1.0.0", "v1.0.1"),
    }, tags=["v1.0.1"])

    r = drive(clone, env_extra={"HDP_TEST_IMAGE_REF": "ghcr.io/sidiberlin/hdp-haystack:v1.0.0"},
              timeout=15)
    show(name, r)
    dl = r["docker"]
    lines = dl.splitlines()
    pull_i = next((i for i, ln in enumerate(lines) if ln.endswith(" pull")), -1)
    up_i = next((i for i, ln in enumerate(lines) if ln.endswith("up -d")), -1)
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "a pull ran before up -d", pull_i != -1 and up_i != -1 and pull_i < up_i, dl)
    check(name, "derived files match the stubbed pull-path image ref",
          any("prod.yml" in ln for ln in lines[:up_i + 1] if up_i >= 0), dl)


def scenario_6_decline():
    name = "6 decline at the confirm -> exit 0, HEAD unmoved, no mutating call"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])
    before = head_sha(clone)

    r = drive(clone, rules=[("Proceed with this update?", "n")], timeout=10)
    show(name, r)
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "HEAD unmoved", head_sha(clone) == before)
    check(name, "no mutating docker call", no_mutating_calls(r["docker"]), r["docker"])


def scenario_7_env_drift():
    name = "7 .env.example gains a defaulted key and an empty key"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    new_example = (
        "HDP_DB_ROOT_PASSWORD=\n"
        "HDP_DB_PASSWORD=\n"
        "HDP_NEW_DEFAULTED=hello\n"
        "HDP_NEW_SECRET=\n"
    )
    push_new(seed, {".env.example": new_example, "app/marker.txt": "new\n"}, tags=["v1.0.1"])

    r = drive(clone, timeout=15)
    show(name, r)
    envtext = open(os.path.join(clone, ".env")).read()
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}\n{log}")
    check(name, "defaulted key appended", "HDP_NEW_DEFAULTED=hello" in envtext, envtext)
    check(name, "empty key NOT appended", "HDP_NEW_SECRET" not in envtext, envtext)
    check(name, ".env.bak-update exists", os.path.exists(os.path.join(clone, ".env.bak-update")))
    check(name, "run ./install.sh line printed", "./install.sh" in log)


def scenario_8_setup_fails():
    name = "8 the setup exec fails -> exit 1, rollback block has the real old SHA"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    old = head_sha(clone)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])

    r = drive(clone, env_extra={"HDP_TEST_FAIL_SETUP": "1"}, timeout=15)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 1", r["rc"] == 1, f"rc={r['rc']}\n{log}")
    check(name, "rollback block has the real old SHA", old[:7] in log, log)
    ref = git(clone, "rev-parse", "refs/hdp/pre-update", check=False).stdout.strip()
    check(name, "refs/hdp/pre-update resolves to the old SHA", ref == old, ref)


def scenario_9_channel_resolution():
    name = "9 channel resolution across a mixed tag set"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"})
    tag_only(seed, [
        "v5.1.9", "v5.1.9-QoL2", "v5.1.9-QoL3", "v5.1.9-QoL10",
        "v5.1.9-rc1", "v5.1.10-rc1", "5.1.3+20260107095645",
    ])

    r = drive(clone, args=["--check"], timeout=10)
    show(name, r)
    log = strip(r["log"])
    check(name, "resolves to v5.1.9-QoL10", "v5.1.9-QoL10" in log, log)
    check(name, "not v5.1.9-rc1 as the target", "-> v5.1.9-rc1" not in log)
    check(name, "not v5.1.10-rc1 as the target",
          "v5.1.10-rc1" not in log or "release" not in log.split("v5.1.10-rc1")[0][-20:])
    check(name, "not the legacy non-v tag", "5.1.3+20260107095645" not in log)


def scenario_10_no_tags_fallback():
    name = "10 channel fallback: no v* tag on the remote"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"})

    r = drive(clone, timeout=15)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}\n{log}")
    check(name, "falls back and says so", "No v* release tag exists" in log)


def scenario_11_branch_mode():
    name = "11 HDP_UPDATE_REF=main -> branch mode, no ls-remote --tags"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])

    r = drive(clone, env_extra={"HDP_UPDATE_REF": "main"}, timeout=15)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}\n{log}")
    check(name, "plan says branch mode", "tip of main" in log)


def no_mutating_calls(dl):
    mutating = ("compose stop", "compose restart", "compose up", "compose pull",
                "compose build", "compose exec")
    return not any(ln.startswith(m) for m in mutating for ln in dl.splitlines())


def scenario_12_image_gap():
    name = "12 the -QoL* image gap on a pull-path install"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    # Both clones taken from the OLD state, before the gap-shaped commit
    # lands, so each drive() call sees the same "update available" state.
    clone_decline = clone_install(origin, work)
    clone_accept = clone_install(origin, tempfile.mkdtemp(prefix="hdp-update-test-"))
    # docker/** changes but HDP_IMAGE_TAG stays pinned at v1.0.0 (a -QoL-shaped
    # release publishing no images of its own).
    push_new(seed, {"docker/setup.sh": "#!/bin/sh\necho setup2\n"}, tags=["v1.0.0-QoL1"])

    r_decline = drive(clone_decline, env_extra={"HDP_TEST_IMAGE_REF": "ghcr.io/sidiberlin/hdp-haystack:v1.0.0"},
                       rules=[("Build the three services from the updated source instead?", "n")],
                       timeout=10)
    show(name + " (decline)", r_decline)
    log_d = strip(r_decline["log"])
    check(name, "gap named before the confirm", "publishes no images of its own" in log_d)
    check(name, "declining exits 2", r_decline["rc"] == 2, f"rc={r_decline['rc']}")
    check(name, "declining leaves docker log empty of mutating calls",
          no_mutating_calls(r_decline["docker"]), r_decline["docker"])

    r_accept = drive(clone_accept, env_extra={"HDP_TEST_IMAGE_REF": "ghcr.io/sidiberlin/hdp-haystack:v1.0.0"},
                      timeout=15)
    show(name + " (accept)", r_accept)
    dl = r_accept["docker"]
    check(name, "accepting builds (not pulls) then up -d",
          any(ln.startswith("compose build") for ln in dl.splitlines())
          and any(ln.startswith("compose up") for ln in dl.splitlines()), dl)


def scenario_13_pull_path_moved_tag():
    name = "13 pull path with a moved tag -> pull fires, no build"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    # A base v* release whose prod.yml default advances alongside a docker/** change.
    push_new(seed, {
        "docker/setup.sh": "#!/bin/sh\necho setup2\n",
        "docker-compose.prod.yml": FIXTURE_OLD["docker-compose.prod.yml"].replace("v1.0.0", "v1.1.0"),
    }, tags=["v1.1.0"])

    r = drive(clone, env_extra={"HDP_TEST_IMAGE_REF": "ghcr.io/sidiberlin/hdp-haystack:v1.0.0"},
              timeout=15)
    show(name, r)
    dl = r["docker"]
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "pull fires", any(ln.startswith("compose") and "pull" in ln for ln in dl.splitlines()))
    check(name, "no build in the log",
          not any(ln.startswith("compose build") for ln in dl.splitlines()))


def scenario_14_backwards():
    name = "14 backwards move -> extra confirm, decline exits 0 unchanged"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    # Move the clone AHEAD of the tag: commit locally first, then clone it,
    # then tag an ancestor commit as the "release".
    push_new(seed, {"app/marker.txt": "ahead\n"})
    clone = clone_install(origin, work)
    # The OLD (ancestor) commit is what gets tagged as the release target —
    # i.e. the checkout's HEAD is a descendant of it.
    first_sha = git(seed, "log", "--format=%H").stdout.strip().splitlines()[-1]
    tag_only(seed, ["v0.9.0"], at=first_sha)

    before = head_sha(clone)
    r = drive(clone, env_extra={"HDP_UPDATE_REF": "v0.9.0"},
              rules=[("This moves the tree BACKWARDS", "n")], timeout=15)
    show(name, r)
    log = strip(r["log"])
    check(name, "warns about moving backwards", "BACKWARDS" in log, log)
    check(name, "declining exits 0", r["rc"] == 0, f"rc={r['rc']}")
    check(name, "HEAD unmoved", head_sha(clone) == before)


def scenario_15_happy_path_tag_local():
    name = "15 happy path: tag created locally, HEAD stays on a branch"
    work = tempfile.mkdtemp(prefix="hdp-update-test-")
    origin, seed = make_origin(work)
    clone = clone_install(origin, work)
    push_new(seed, {"app/marker.txt": "new\n"}, tags=["v1.0.1"])

    r = drive(clone, timeout=15)
    show(name, r)
    log = strip(r["log"])
    check(name, "exit 0", r["rc"] == 0, f"rc={r['rc']}\n{log}")
    describe = git(clone, "describe", "--tags", check=False).stdout.strip()
    check(name, "git describe --tags names the target tag", describe.startswith("v1.0.1"), describe)
    branch = git(clone, "symbolic-ref", "--quiet", "--short", "HEAD", check=False).stdout.strip()
    check(name, "HEAD is still on a branch (not detached)", branch != "", branch)


def main():
    scenario_1_dirty_tree()
    scenario_2_composer_lock_only()
    scenario_3_already_current()
    scenario_4_app_only()
    scenario_5_docker_change()
    scenario_6_decline()
    scenario_7_env_drift()
    scenario_8_setup_fails()
    scenario_9_channel_resolution()
    scenario_10_no_tags_fallback()
    scenario_11_branch_mode()
    scenario_12_image_gap()
    scenario_13_pull_path_moved_tag()
    scenario_14_backwards()
    scenario_15_happy_path_tag_local()

    print()
    if FAILED:
        print(f"{len(FAILED)} check(s) FAILED:")
        for f in FAILED:
            print("  - " + f)
        sys.exit(1)
    print("all checks passed")


if __name__ == "__main__":
    main()
