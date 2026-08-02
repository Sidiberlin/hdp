"""A minimal MediaWiki HTTP/API client for the integration tier.

Standard library only, and that is a deliberate constraint rather than an
aesthetic one. The T3 job's whole cost model is "docker plus the python3 the
runner already has" — adding `requests` would mean a pip install on a runner
whose entire job is to boot a 7-container wiki, and a pinned dependency to keep
in step for a client that needs a cookie jar and form-encoded POSTs.

Three things here are not obvious and each of them cost a debugging cycle
somewhere in Waves 0-2:

1. **This must run from the host, not from inside a container.** ``$wgServer``
   is ``http://localhost:8080``, so MediaWiki answers ``/w/index.php/Foo`` with
   a redirect to the canonical ``/wiki/Foo`` — a URL that only resolves through
   the published port mapping. Run 1 of the Wave 2 clean-box validation tripped
   on exactly that.

2. **The API requires a login even to read.** An anonymous
   ``action=query&meta=siteinfo`` on this wiki returns ``readapidenied``, so
   every assertion in this tier goes through an authenticated session. A test
   that forgets to log in fails with a JSON error rather than a 401, which
   reads like a broken assertion instead of a missing cookie.

3. **``clientlogin`` is a multi-step flow here.** BlueSpicePrivacy interposes a
   terms-of-use / privacy-consent form, so the first call returns
   ``status: "UI"`` with a set of checkbox fields rather than ``PASS``. The
   continuation needs a *fresh* login token — reusing the first one fails with
   ``badtoken``.
"""
import http.cookiejar
import json
import urllib.error
import urllib.parse
import urllib.request

DEFAULT_TIMEOUT = 120


class LoginError(RuntimeError):
    """Raised when the clientlogin flow does not end in status PASS."""


class Response:
    """A fetched page: status code, final URL, headers and body.

    urllib raises HTTPError for any status >= 400, which would turn "the page
    under test returned 500" — the exact thing QA Bug 4 is about — into an
    exception in the client rather than a failed assertion in the test. So the
    error is caught and normalised into an ordinary response object.
    """

    __slots__ = ("status", "url", "headers", "body")

    def __init__(self, status, url, headers, body):
        self.status = status
        self.url = url
        self.headers = headers
        self.body = body

    @property
    def text(self):
        return self.body.decode("utf-8", errors="replace")

    @property
    def kib(self):
        return len(self.body) // 1024

    def __repr__(self):
        return f"<Response {self.status} {self.url} {self.kib} KiB>"


class WikiClient:
    """Authenticated HTTP + Action API access to a running HDP wiki.

    ``base`` is the script path root as seen from the host, e.g.
    ``http://localhost:8080/w``.
    """

    def __init__(self, base, timeout=DEFAULT_TIMEOUT):
        self.base = base.rstrip("/")
        self.api_url = self.base + "/api.php"
        self.timeout = timeout
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar)
        )
        self.logged_in_as = None

    # ─── raw HTTP ───────────────────────────────────────────────────
    def fetch(self, path_or_url, timeout=None):
        """GET a page. Relative paths are resolved against the script path."""
        url = (
            path_or_url
            if path_or_url.startswith("http")
            else self.base + "/" + path_or_url.lstrip("/")
        )
        try:
            with self.opener.open(url, timeout=timeout or self.timeout) as r:
                return Response(r.status, r.url, dict(r.headers), r.read())
        except urllib.error.HTTPError as e:
            # See Response's docstring: a 500 is data, not an exception.
            return Response(e.code, url, dict(e.headers or {}), e.read())

    # ─── Action API ─────────────────────────────────────────────────
    def api(self, **params):
        params.setdefault("format", "json")
        params.setdefault("formatversion", "2")
        url = self.api_url + "?" + urllib.parse.urlencode(params)
        resp = self.fetch(url)
        return self._decode(resp, params)

    def api_post(self, data):
        data = dict(data)
        data.setdefault("format", "json")
        data.setdefault("formatversion", "2")
        req = urllib.request.Request(
            self.api_url, data=urllib.parse.urlencode(data).encode()
        )
        try:
            with self.opener.open(req, timeout=self.timeout) as r:
                resp = Response(r.status, r.url, dict(r.headers), r.read())
        except urllib.error.HTTPError as e:
            resp = Response(e.code, self.api_url, dict(e.headers or {}), e.read())
        return self._decode(resp, data)

    @staticmethod
    def _decode(resp, params):
        try:
            payload = json.loads(resp.body.decode("utf-8"))
        except (ValueError, UnicodeDecodeError) as exc:
            raise RuntimeError(
                f"API did not return JSON (HTTP {resp.status}) for "
                f"{ {k: v for k, v in params.items() if 'pass' not in k} }: "
                f"{resp.body[:400]!r}"
            ) from exc
        if isinstance(payload, dict) and "error" in payload:
            code = payload["error"].get("code")
            if code == "readapidenied":
                raise RuntimeError(
                    "API returned readapidenied — this wiki requires a login "
                    "even to read. Use the logged-in `wiki` fixture, not a "
                    "fresh WikiClient."
                )
            raise RuntimeError(f"API error {code}: {payload['error'].get('info')}")
        return payload

    def _login_token(self):
        return self.api(action="query", meta="tokens", type="login")["query"]["tokens"][
            "logintoken"
        ]

    @staticmethod
    def _ui_fields(result):
        """Field names to fill for a clientlogin `status: "UI"` step.

        The shape depends on formatversion, and this client asks for 2:

            fv1:  {"request":  {"fields": {...}}}
            fv2:  {"requests": [{"fields": {...}}, ...]}

        Both are read, because reading only the fv1 shape is a bug that hides
        itself: the consent step is skipped entirely for an account that has
        already consented, so on any wiki that has been logged into before,
        `login()` returns PASS on the first call and never reaches this code.
        It surfaces only on a genuinely fresh install — which is exactly the
        wiki T3 creates, and exactly the wiki a first-time user gets.
        """
        fields = {}
        request = result.get("request")
        if isinstance(request, dict):
            fields.update(request.get("fields") or {})
        for entry in result.get("requests") or []:
            if isinstance(entry, dict):
                fields.update(entry.get("fields") or {})
        return list(fields)

    # ─── login ──────────────────────────────────────────────────────
    def login(self, username, password):
        """Log in via clientlogin, continuing through any consent UI step.

        Returns the list of UI field names that had to be accepted, which is
        empty on a wiki with no consent step. Tests assert on the outcome, not
        on that list — BlueSpicePrivacy's field set is upstream's to change.
        """
        result = self.api_post(
            {
                "action": "clientlogin",
                "loginreturnurl": self.base + "/",
                "logintoken": self._login_token(),
                "username": username,
                "password": password,
            }
        ).get("clientlogin", {})

        accepted = []
        # A bounded loop, not `while`: a wiki that keeps answering UI would
        # otherwise hang the job until the CI timeout with no diagnosis.
        for _ in range(4):
            if result.get("status") != "UI":
                break
            fields = self._ui_fields(result)
            if not fields:
                raise LoginError(
                    f"clientlogin returned UI with no fields to fill: {result!r}"
                )
            accepted.extend(fields)
            data = {
                "action": "clientlogin",
                "logincontinue": "1",
                # A *fresh* token. Replaying the first one gives badtoken.
                "logintoken": self._login_token(),
            }
            data.update({f: "1" for f in fields})
            result = self.api_post(data).get("clientlogin", {})

        if result.get("status") != "PASS":
            raise LoginError(
                f"clientlogin ended in status {result.get('status')!r}: "
                f"{result.get('message', result)!r}"
            )
        self.logged_in_as = result.get("username", username)
        return accepted

    # ─── convenience ────────────────────────────────────────────────
    def siteinfo(self, siprop):
        return self.api(action="query", meta="siteinfo", siprop=siprop)["query"]

    def all_pages(self, namespace):
        """Every page title in a namespace, following continuation."""
        titles = []
        cont = {}
        while True:
            params = {
                "action": "query",
                "list": "allpages",
                "apnamespace": str(namespace),
                "aplimit": "max",
            }
            params.update(cont)
            data = self.api(**params)
            titles.extend(p["title"] for p in data.get("query", {}).get("allpages", []))
            cont = data.get("continue")
            if not cont:
                return titles
