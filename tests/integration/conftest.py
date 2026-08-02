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
import shutil
import subprocess

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
