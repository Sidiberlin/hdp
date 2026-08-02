"""Fixtures for the integration tier — the one that needs a running stack.

Wave 3's brief is "verify the full stack boots and serves real wiki traffic,
not just individual functions", so everything here talks to a live wiki over
its published port and to live containers over `docker compose exec`.

**Unreachable is a failure, not a skip.** `scripts/check.sh` has said since
Wave 1 that a skipped check is never a passing check, and the same contract
applies at the tier boundary: deciding whether the stack exists is
`scripts/ci/pytest.sh`'s job, and it reports "no stack" as 77 (SKIP) *before*
pytest is ever started. Once pytest is running, the stack is supposed to be
there, so a connection refused is a red test. The alternative — skipping inside
the tests — produces a green run against a wiki that never booted, which is
precisely the outcome T3 exists to make impossible.
"""
import json
import os
import re
import shutil
import subprocess
import time

import pytest
from wikiclient import LoginError, WikiClient

# The wiki as seen from the host. Must be the *published* port: $wgServer is
# http://localhost:8080, so MediaWiki redirects to canonical /wiki/... URLs
# that do not resolve from inside the compose network.
DEFAULT_WIKI_URL = "http://localhost:8080/w"

ADMIN_USER = "Admin"

# Set by scripts/ci/t3-integration.sh. Tests that assert on the install
# sequence read these; see tests/integration/test_install_update.py for what
# happens when they are absent.
ENV_SETUP_LOG = "HDP_SETUP_LOG"
ENV_SETUP_EXIT = "HDP_SETUP_EXIT"


def _read_dotenv(repo_root):
    """The subset of .env this tier needs, parsed without sourcing it.

    `.env` is a compose env-file, not a shell script — it can legally contain
    values that a shell would try to execute. Sourcing it to read one password
    is how a probe script picks up a `command not found` on line 27.
    """
    path = repo_root / ".env"
    values = {}
    if not path.is_file():
        return values
    for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
            value = value[1:-1]
        values[key.strip()] = value
    return values


@pytest.fixture(scope="session")
def dotenv(repo_root):
    return _read_dotenv(repo_root)


@pytest.fixture(scope="session")
def wiki_url():
    return os.environ.get("HDP_WIKI_URL", DEFAULT_WIKI_URL).rstrip("/")


@pytest.fixture(scope="session")
def admin_password(dotenv):
    """The Admin password, from the environment or from .env.

    Never logged, never included in an assertion message. The failure below
    names the two places to set it and nothing else.
    """
    password = os.environ.get("HDP_ADMIN_PASSWORD") or dotenv.get("HDP_ADMIN_PASSWORD")
    if not password:
        pytest.fail(
            "HDP_ADMIN_PASSWORD is not set and .env does not define it. "
            "The integration tier logs in as Admin; export it or create .env "
            "from .env.example."
        )
    return password


@pytest.fixture(scope="session")
def anon(wiki_url):
    """An unauthenticated client, for the handful of anonymous assertions."""
    return WikiClient(wiki_url)


@pytest.fixture(scope="session")
def wiki(wiki_url, admin_password):
    """A logged-in Admin session against the running wiki.

    Session-scoped: the login is a three-request dance through BlueSpice's
    consent UI, and repeating it per test would triple the tier's wall clock
    for no extra coverage. The pages under test are read-only.
    """
    client = WikiClient(wiki_url)
    probe = client.fetch("/")
    if probe.status in (0, 502, 503, 504):
        pytest.fail(
            f"the wiki at {wiki_url}/ answered HTTP {probe.status}. The "
            f"integration tier needs a running stack; start one with "
            f"`docker compose up -d` + `docker compose exec mediawiki bash /setup.sh`, "
            f"or run the whole sequence with scripts/ci/t3-integration.sh."
        )
    try:
        client.login(ADMIN_USER, admin_password)
    except LoginError as exc:
        pytest.fail(f"could not log in as {ADMIN_USER}: {exc}")
    return client


# ─── container access ───────────────────────────────────────────────
@pytest.fixture(scope="session")
def compose(repo_root):
    """Run `docker compose` subcommands against the stack under test.

    Invoked with cwd=repo_root so the compose project name matches the one the
    stack was started under — compose derives it from the directory name, so a
    stack started elsewhere is a *different* project and these calls would
    silently address nothing.
    """
    if shutil.which("docker") is None:
        pytest.fail(
            "docker is not on PATH. The integration tier reads container state "
            "(table counts, error logs) through `docker compose exec`."
        )

    def _run(*args, timeout=600, check=False):
        proc = subprocess.run(
            ["docker", "compose", *args],
            cwd=str(repo_root),
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False,
        )
        if check and proc.returncode != 0:
            raise AssertionError(
                f"`docker compose {' '.join(args)}` exited {proc.returncode}\n"
                f"stdout:\n{proc.stdout[-4000:]}\nstderr:\n{proc.stderr[-4000:]}"
            )
        return proc

    return _run


@pytest.fixture(scope="session")
def mw_exec(compose):
    """Run a command in the mediawiki (PHP-FPM) container."""

    def _exec(*argv, timeout=900, check=False):
        return compose("exec", "-T", "mediawiki", *argv, timeout=timeout, check=check)

    return _exec


@pytest.fixture(scope="session")
def mw_globals(repo_root):
    """Read `$wg*` configuration globals out of the running wiki.

    This is the literal "wg-prefix readback" Wave 3 asks for: the values are
    taken from a PHP process that has loaded LocalSettings.php and every
    `settings.d/*.php` file, so it reports what MediaWiki ended up with rather
    than what any one config file asked for. Several of this fork's fixes are
    *overrides* applied after the Wikimedia dev image's own settings — the only
    honest way to check those is to look at the final value.

    `maintenance/run.php eval.php` reads PHP from stdin, so the snippet is
    piped rather than passed in argv.
    """

    def _globals(names):
        keys = ", ".join(f'"{n}" => $GLOBALS["{n}"] ?? null' for n in names)
        snippet = f"echo json_encode([{keys}]);"
        proc = subprocess.run(
            ["docker", "compose", "exec", "-T", "mediawiki",
             "php", "maintenance/run.php", "eval.php"],
            cwd=str(repo_root),
            input=snippet,
            capture_output=True,
            text=True,
            timeout=300,
            check=False,
        )
        assert proc.returncode == 0, (
            f"eval.php exited {proc.returncode}\n{proc.stdout[-2000:]}\n{proc.stderr[-2000:]}"
        )
        # eval.php echoes a banner before the result on some builds; take the
        # last line that parses as JSON.
        for line in reversed(proc.stdout.strip().splitlines()):
            try:
                return json.loads(line)
            except ValueError:
                continue
        raise AssertionError(f"eval.php returned no JSON:\n{proc.stdout[-2000:]}")

    return _globals


@pytest.fixture(scope="session")
def mw_sql(mw_exec):
    """Run one SQL statement through maintenance/sql.php and return raw stdout.

    sql.php is used rather than a direct `mariadb` client so the query runs
    against whatever database LocalSettings.php actually points at, with the
    credentials MediaWiki itself uses. A test that reaches past MediaWiki to
    the DB can pass against a database the wiki is not using.
    """

    def _sql(query, timeout=300):
        proc = mw_exec(
            "php", "maintenance/run.php", "sql.php", "--query", query, timeout=timeout
        )
        assert proc.returncode == 0, (
            f"sql.php exited {proc.returncode} for query {query!r}\n"
            f"stdout:\n{proc.stdout[-2000:]}\nstderr:\n{proc.stderr[-2000:]}"
        )
        return proc.stdout

    return _sql


# ─── the setup.sh run under test ────────────────────────────────────
@pytest.fixture(scope="session")
def setup_record():
    """The exit code and log of the `setup.sh` run that produced this stack.

    Populated by scripts/ci/t3-integration.sh via HDP_SETUP_EXIT and
    HDP_SETUP_LOG. Returns None when the tier is pointed at a stack somebody
    else installed — the developer inner loop — and the tests that need it say
    so explicitly rather than silently asserting nothing.
    """
    exit_code = os.environ.get(ENV_SETUP_EXIT)
    log_path = os.environ.get(ENV_SETUP_LOG)
    if exit_code is None or not log_path:
        return None
    text = ""
    if os.path.isfile(log_path):
        text = open(log_path, encoding="utf-8", errors="replace").read()
    return {"exit": int(exit_code), "log": text, "path": log_path}


# ════════════════════════════════════════════════════════════════════
# Wave 4 / T4 — the full seven-container stack
#
# Everything below is used only by tests carrying the `smoke` marker, which
# `scripts/ci/pytest.sh --tier integration` deselects. The T3 profile omits
# mediawiki-jobrunner, haystack and chatbot-proxy, so these fixtures would fail
# there for the honest reason that the containers are not running — and a test
# that skips in that situation is the "green run against a stack that never
# booted" this tier's docstring exists to forbid. The marker draws the line at
# collection time instead, where it is visible in the tier's name.
# ════════════════════════════════════════════════════════════════════

# The seven containers T4 asserts healthy.
#
# The Wave 4 brief names `pdf-generator` as one of them. There is no such
# service: docker-compose.yml has seven, and what the brief means by
# "pdf-generator" is the haystack container's *second port* (HDP_PDF_PORT,
# 1417) — the FastAPI server in docker/haystack/hdp_api_server.py that serves
# /health, /ready and the RAG query API alongside hayhooks on 1416. The seventh
# container is mediawiki-web, which the brief's list omits. Counting
# pdf-generator separately gives eight names for seven containers and a smoke
# test that can never pass.
FULL_STACK_SERVICES = (
    "mariadb",
    "mediawiki",
    "mediawiki-web",
    "mediawiki-jobrunner",
    "opensearch",
    "haystack",
    "chatbot-proxy",
)

# Job types this wiki keeps queued *permanently*, by design.
#
# `invokeRunner` is the job of `mwstake/mediawiki-component-runjobstrigger`,
# wired up in app/extensions/BlueSpiceFoundation/src/Foundation.php (see
# mwsgRunJobsTriggerOptions). Every execution schedules the next one, so the
# queue is self-replenishing: on a healthy, fully indexed wiki it sits in the
# hundreds and climbs, while `showJobs.php --group` reports
# `invokeRunner: 630 queued; 0 claimed (0 active, 0 abandoned)` and the
# jobrunner log fills with `... t=11 good`.
#
# **"Wait for the job queue to reach zero" is therefore an assertion that can
# never pass here**, and believing otherwise is an easy mistake to make: Wave
# 3's /qa recorded "500 jobs queued, bluespice_wikipage empty" under the T3
# profile and the natural reading is that the 500 *are* the indexing backlog.
# They are not. The index was empty under T3 because there was no jobrunner at
# all; those 500 were these same perpetual triggers. Measured on the Wave 4
# clean box: 600 queued right after setup.sh, 630 ten minutes later, all of
# them `invokeRunner`, with `bluespice_wikipage` already holding its full 808
# documents the whole time.
#
# So what T4 waits for and asserts on is the queue *minus* these — the work
# that is supposed to finish.
PERPETUAL_JOB_TYPES = ("invokeRunner",)

# How long to let mediawiki-jobrunner finish the non-perpetual work. A fresh
# install enqueues one ExtendedSearch index write per page and the runner is a
# bash loop calling runJobs.php. Overridable because a cold CI runner is slower
# than the validation box.
ENV_JOBQUEUE_TIMEOUT = "HDP_JOBQUEUE_TIMEOUT"
DEFAULT_JOBQUEUE_TIMEOUT = 900

# The non-perpetual queue depth *before* anything drained it, recorded by
# scripts/ci/t4-smoke.sh. Without it the fixture below can only measure the
# queue it finds, and the runner has already worked it down by then.
ENV_JOBQUEUE_START = "HDP_JOBQUEUE_START"

# Set by scripts/ci/t4-smoke.sh once it has run ingest_hdp_wiki.py. Ingestion
# takes ~8 minutes and belongs to the job that builds the stack, not to an
# assertion; the tests read this to tell "ingestion ran and produced the wrong
# number" apart from "nobody ran ingestion".
ENV_INGEST_RAN = "HDP_INGEST_RAN"


@pytest.fixture(scope="session")
def full_stack_services():
    """FULL_STACK_SERVICES as a fixture.

    Exposed this way rather than imported from the test module, because
    `from conftest import ...` inside a test file depends on how pytest
    happens to have named this module — which differs between import modes and
    is not something a test should have an opinion about.
    """
    return FULL_STACK_SERVICES


@pytest.fixture(scope="session")
def compose_ps(compose):
    """`docker compose ps` for every service, as {service: {state, health}}.

    `--format json` is asked for explicitly and parsed line by line: compose
    v2 emits one JSON object per line rather than a JSON array, and older
    patch releases of v2 emitted an array. Both are handled, because the
    alternative is a smoke test whose headline assertion depends on a compose
    point release.
    """

    def _ps():
        proc = compose("ps", "--all", "--format", "json", timeout=120)
        assert proc.returncode == 0, (
            f"`docker compose ps` exited {proc.returncode}\n{proc.stderr[-2000:]}"
        )
        entries = []
        text = proc.stdout.strip()
        if text.startswith("["):
            entries = json.loads(text)
        else:
            for line in text.splitlines():
                line = line.strip()
                if line:
                    entries.append(json.loads(line))
        out = {}
        for entry in entries:
            service = entry.get("Service") or entry.get("Name")
            out[service] = {
                "state": entry.get("State", ""),
                "health": entry.get("Health", ""),
                "name": entry.get("Name", ""),
            }
        return out

    return _ps


@pytest.fixture(scope="session")
def full_stack(compose_ps):
    """Assert once, for the whole tier, that all seven containers are running.

    Session-scoped and used by every smoke test, so a stack missing haystack
    reports one readable failure naming the absent service rather than a dozen
    connection errors from tests that were never going to work.
    """
    state = compose_ps()
    missing = [s for s in FULL_STACK_SERVICES if s not in state]
    assert not missing, (
        f"these services are not part of the running stack: {missing}. "
        f"The smoke tier needs all seven; the T3 profile deliberately omits "
        f"mediawiki-jobrunner, haystack and chatbot-proxy. Start the full "
        f"stack with scripts/ci/t4-smoke.sh, or `docker compose up -d`.\n"
        f"running: {sorted(state)}"
    )
    return state


@pytest.fixture(scope="session")
def os_json(compose):
    """GET a path on OpenSearch from inside the opensearch container.

    The password never appears in argv or in a failure message: the container
    already holds it as OPENSEARCH_INITIAL_ADMIN_PASSWORD (compose sets it
    there), so the shell inside expands it and this process never sees it.
    Same shape as the existing backend probe in test_site_config.py.
    """

    def _get(path, timeout=180, method="GET", body=None):
        cmd = (
            'curl -sk -u "admin:$OPENSEARCH_INITIAL_ADMIN_PASSWORD" '
            f'-X {method} '
        )
        if body is not None:
            cmd += "-H 'Content-Type: application/json' -d '" + body.replace("'", "'\"'\"'") + "' "
        cmd += f'"https://localhost:9200{path}"'
        proc = compose("exec", "-T", "opensearch", "sh", "-c", cmd, timeout=timeout)
        assert proc.returncode == 0, (
            f"could not reach OpenSearch for {path!r} (exit {proc.returncode})\n"
            f"{proc.stderr[-2000:]}"
        )
        try:
            return json.loads(proc.stdout)
        except ValueError as exc:
            raise AssertionError(
                f"OpenSearch returned non-JSON for {path!r}: {proc.stdout[:600]!r}"
            ) from exc

    return _get


@pytest.fixture(scope="session")
def os_count(os_json):
    """Document count of an OpenSearch index, or None if it does not exist."""

    def _count(index):
        payload = os_json(f"/{index}/_count")
        if "count" not in payload:
            return None
        return payload["count"]

    return _count


@pytest.fixture(scope="session")
def http_in(compose):
    """Make an HTTP request from inside a container, using its own python3.

    chatbot-proxy is `python:3.12-slim` and has no curl, wget or nc — and it
    publishes no port, so the host cannot reach it at all. `docker compose
    exec` plus the python that is already the container's reason for existing
    is the only way in, and it is what the Wave 4 brief means by "compose exec
    for the proxy".

    Returns (status, body). A non-2xx is data, not an exception, for the same
    reason wikiclient.Response normalises HTTPError: `/ready` answering 503 is
    the assertion, not an error.
    """
    script = r"""
import json, sys, urllib.error, urllib.request
url, method, payload = sys.argv[1], sys.argv[2], sys.argv[3]
data = payload.encode() if payload else None
req = urllib.request.Request(url, data=data, method=method)
if data:
    req.add_header("Content-Type", "application/json")
try:
    with urllib.request.urlopen(req, timeout=120) as r:
        status, body = r.status, r.read().decode("utf-8", "replace")
except urllib.error.HTTPError as e:
    status, body = e.code, e.read().decode("utf-8", "replace")
except Exception as e:
    status, body = 0, f"{type(e).__name__}: {e}"
print(json.dumps({"status": status, "body": body}))
"""

    def _request(service, url, method="GET", payload="", timeout=300):
        proc = compose(
            "exec", "-T", service, "python3", "-c", script, url, method, payload,
            timeout=timeout,
        )
        assert proc.returncode == 0, (
            f"`compose exec {service}` failed (exit {proc.returncode}) for {url}\n"
            f"stdout:\n{proc.stdout[-2000:]}\nstderr:\n{proc.stderr[-2000:]}"
        )
        for line in reversed(proc.stdout.strip().splitlines()):
            try:
                decoded = json.loads(line)
            except ValueError:
                continue
            return decoded["status"], decoded["body"]
        raise AssertionError(f"no JSON from the probe in {service}:\n{proc.stdout[-2000:]}")

    return _request


@pytest.fixture(scope="session")
def sse_in(compose):
    """Consume a Server-Sent Events response the way `EventSource` does.

    Returns (status, [frame, ...]) where each frame is the decoded JSON of one
    `data: ` line, stopping at the terminating `result` (or `error`) frame.

    This exists because `http_in` cannot be used for `/chat-stream`: an SSE
    response carries no Content-Length and is not chunked, so `read()` returns
    only when the connection closes. The Wave 4 box measured a server that had
    finished writing after 19s against a client still blocked 600s later —
    while the chat UI worked perfectly, because a browser's EventSource acts on
    each frame as it arrives and never waits for the body to end.

    Reading frame by frame is therefore not a workaround for a slow endpoint;
    it is the only shape of client this endpoint has. A test that called
    `.read()` would be asserting on a response no EventSource ever waits for.
    """
    script = r"""
import json, sys, time, urllib.error, urllib.request
url, payload, budget = sys.argv[1], sys.argv[2], float(sys.argv[3])
req = urllib.request.Request(url, data=payload.encode(), method="POST")
req.add_header("Content-Type", "application/json")
req.add_header("Accept", "text/event-stream")
frames, status, err = [], 0, ""
deadline = time.time() + budget
try:
    resp = urllib.request.urlopen(req, timeout=budget)
    status = resp.status
    while time.time() < deadline:
        line = resp.readline()
        if not line:
            break
        line = line.decode("utf-8", "replace").strip()
        if not line.startswith("data: "):
            continue
        try:
            frame = json.loads(line[6:])
        except ValueError:
            continue
        frames.append(frame)
        # The frontend settles on either of these; so does this reader,
        # rather than waiting for a connection close that may never come.
        if frame.get("type") in ("result", "error"):
            break
    resp.close()
except urllib.error.HTTPError as e:
    status = e.code
    for raw in e.read().decode("utf-8", "replace").splitlines():
        if raw.startswith("data: "):
            try:
                frames.append(json.loads(raw[6:]))
            except ValueError:
                pass
except Exception as e:
    err = f"{type(e).__name__}: {e}"
print("@@SSE@@" + json.dumps({"status": status, "frames": frames, "error": err}))
"""

    def _stream(service, url, payload, budget=240, timeout=420):
        proc = compose(
            "exec", "-T", service, "python3", "-c", script, url, payload, str(budget),
            timeout=timeout,
        )
        assert proc.returncode == 0, (
            f"`compose exec {service}` failed (exit {proc.returncode}) for {url}\n"
            f"stdout:\n{proc.stdout[-2000:]}\nstderr:\n{proc.stderr[-2000:]}"
        )
        for line in reversed(proc.stdout.splitlines()):
            if line.startswith("@@SSE@@"):
                decoded = json.loads(line[len("@@SSE@@"):])
                assert not decoded["error"], (
                    f"the SSE probe against {url} failed: {decoded['error']}"
                )
                return decoded["status"], decoded["frames"]
        raise AssertionError(
            f"no SSE result from the probe in {service}:\n{proc.stdout[-2000:]}"
        )

    return _stream


@pytest.fixture(scope="session")
def job_queue_groups(mw_exec):
    """Pending jobs broken down by type, as {type: queued}.

    `showJobs.php --group` prints one line per type:

        invokeRunner: 630 queued; 0 claimed (0 active, 0 abandoned); 0 delayed

    The breakdown, not the bare total, is what T4 needs — see
    PERPETUAL_JOB_TYPES for why a total is not an answerable question on this
    wiki. Read through maintenance/run.php so it uses the wiki's own
    configuration, the same reason mw_sql goes through sql.php.
    """
    line_re = re.compile(r"^\s*(\S+):\s+(\d+)\s+queued")

    def _groups(timeout=300):
        proc = mw_exec(
            "php", "maintenance/run.php", "showJobs.php", "--group", timeout=timeout
        )
        assert proc.returncode == 0, (
            f"showJobs.php --group exited {proc.returncode}\n"
            f"{proc.stdout[-2000:]}\n{proc.stderr[-2000:]}"
        )
        groups = {}
        for line in proc.stdout.splitlines():
            match = line_re.match(line)
            if match:
                groups[match.group(1)] = int(match.group(2))
        # An empty queue prints nothing at all, which is a legitimate result
        # and must not be confused with a parse failure — hence no assertion
        # that `groups` is non-empty.
        return groups

    return _groups


@pytest.fixture(scope="session")
def pending_work(job_queue_groups):
    """Queued jobs excluding the perpetual triggers — the work meant to finish."""

    def _pending(timeout=300):
        groups = job_queue_groups(timeout=timeout)
        return sum(
            count
            for kind, count in groups.items()
            if kind not in PERPETUAL_JOB_TYPES
        )

    return _pending


@pytest.fixture(scope="session")
def drained_job_queue(full_stack, pending_work, job_queue_groups):
    """Wait for mediawiki-jobrunner to finish the non-perpetual work.

    Returns {"start", "end", "seconds", "timeout", "groups"}. This is the
    fixture the whole search leg hangs off: on a fresh install the
    ExtendedSearch index is written entirely by background jobs, so *every*
    full-text assertion is a statement about that work having completed first.
    Wave 3's T3 profile has no jobrunner, which is why its search coverage
    stops at the configuration.

    It waits on `pending_work`, not on the total. See PERPETUAL_JOB_TYPES: the
    total never reaches zero on this wiki and waiting for it burns the whole
    budget on a perfectly healthy stack.

    Waiting is not asserting — the assertions live in test_search.py, so a
    stack whose indexing never finishes fails as a named test rather than as a
    fixture error.
    """
    deadline_seconds = int(
        os.environ.get(ENV_JOBQUEUE_TIMEOUT, DEFAULT_JOBQUEUE_TIMEOUT)
    )
    recorded_start = os.environ.get(ENV_JOBQUEUE_START)
    start_count = int(recorded_start) if recorded_start else pending_work()
    started = time.monotonic()
    pending = pending_work()
    while pending > 0 and (time.monotonic() - started) < deadline_seconds:
        time.sleep(10)
        pending = pending_work()
    return {
        "start": start_count,
        "end": pending,
        "seconds": int(time.monotonic() - started),
        "timeout": deadline_seconds,
        "groups": job_queue_groups(),
    }


@pytest.fixture(scope="session")
def jobrunner_log(full_stack, compose):
    """The mediawiki-jobrunner container's log.

    Direct evidence that the container is doing work, which the queue depth
    cannot give: a perpetually non-empty queue looks identical whether the
    runner is executing jobs at two a second or is a no-op. Its healthcheck
    cannot tell them apart either — it only confirms PID 1 is still bash.
    """
    proc = compose(
        "logs", "--no-color", "--tail", "400", "mediawiki-jobrunner", timeout=180
    )
    assert proc.returncode == 0, (
        f"could not read the jobrunner log (exit {proc.returncode})\n"
        f"{proc.stderr[-2000:]}"
    )
    return proc.stdout


@pytest.fixture(scope="session")
def ingest_ran():
    """Whether the harness ran ingest_hdp_wiki.py before the tests.

    See ENV_INGEST_RAN. `scripts/ci/t4-smoke.sh` sets it; a developer pointing
    the tier at a stack of their own has probably not, and the hdp_wiki
    assertion says so with the command to fix it rather than failing as though
    ingestion were broken.
    """
    return os.environ.get(ENV_INGEST_RAN) == "1"
