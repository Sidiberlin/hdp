"""The authentication path, and the two patches that hold it up.

Nineteen patches are re-applied on every install and any of them can be
dropped silently. Two of them are different in kind from the other seventeen:

    pluggableauth-service   extensions/PluggableAuth/includes/PluggableAuthService.php
    oidc-client             vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php

Dropping a MultimediaViewer patch is a cosmetic regression somebody notices.
Dropping either of these is an authentication regression that nothing notices,
and `oidc-client` is the one patch in the manifest that lives under
`app/vendor/` — a directory that is gitignored, wiped by `rm -rf vendor/` on
every setup run, and recreated by composer. It has no gitignore-level
protection at all: what protects it is that it is re-applied on every install
and asserted afterwards. This file is that assertion.

Three layers, deliberately, because each catches something the others cannot:

  1. the manifest agrees the patches are in the tree      (verify-patches.sh)
  2. the patched content is in the *installed* file, and
     the vulnerable upstream form is gone                 (grep in the container)
  3. the code still loads and the login path still works  (eval.php, HTTP)

Layer 3 matters because a patch can apply and still leave a file PHP cannot
parse — and a syntax error inside the OIDC client is an outage of the login
path that only shows up when somebody tries to log in.

Unmarked, so this runs in T3 as well as T4: it needs the wiki and the mediawiki
container, nothing else.
"""
import re

# The line the oidc-client patch adds, and the upstream line it replaces.
# Upstream does `in_array($this->clientID, $claims->aud, true)` with `aud`
# possibly a bare string — which in PHP 8 is a TypeError, not a false. The
# patch normalises it to an array first.
OIDC_PATCHED = "is_array( $auds ) ? $auds : [ $auds ]"
OIDC_UPSTREAM = "$claims->aud === $this->clientID"

# The PluggableAuth patch moves the weight lookup inside the `isset` guard and
# falls back to the form's own weight instead of a hardcoded 101.
PA_PATCHED = "$config['weight'] ?? ("
PA_UPSTREAM_SHAPE = re.compile(r"if\s*\(\s*isset\(\s*\$config\['weight'\]\s*\)\s*\)\s*\{")

FATAL_MARKERS = ("Fatal error", "Uncaught Exception", "MWException", "Call to undefined")


def _read_in_container(mw_exec, path):
    proc = mw_exec("cat", path)
    assert proc.returncode == 0, (
        f"{path} could not be read inside the mediawiki container "
        f"(exit {proc.returncode}). For the vendor/ path this usually means "
        f"composer has not run.\n{proc.stderr[-800:]}"
    )
    return proc.stdout


# ─── Layer 1: the manifest ──────────────────────────────────────────

def test_verify_patches_confirms_both_auth_patches(mw_exec):
    """The manifest's own verdict, run where the installed tree actually is.

    Run inside the container rather than on the host because the interesting
    target is under app/vendor/, which composer creates during setup.sh — on
    the host before an install it is legitimately absent.
    """
    for patch_id in ("pluggableauth-service", "oidc-client"):
        proc = mw_exec(
            "env",
            "HDP_PATCH_MANIFEST_DIR=/hdp-patches",
            "HDP_APP_DIR=/var/www/html/w",
            "HDP_PATCH_LIB_DIR=/hdp-scripts/lib",
            "NO_COLOR=1",
            "bash", "/hdp-scripts/verify-patches.sh", "--id", patch_id,
        )
        assert proc.returncode == 0, (
            f"verify-patches.sh reports {patch_id} is NOT in the installed tree. "
            f"That is an authentication regression, not a cosmetic one.\n"
            f"{proc.stdout[-2000:]}"
        )


# ─── Layer 2: the installed files ───────────────────────────────────

def test_the_oidc_audience_fix_is_in_the_installed_library(mw_exec):
    """The patched line is present in the file composer actually installed."""
    source = _read_in_container(
        mw_exec, "/var/www/html/w/vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php")
    assert OIDC_PATCHED in source, (
        "the OIDC audience normalisation is missing from the installed library. "
        "composer reinstalls this package on every run and the patch is re-applied "
        "afterwards; if it is gone, that re-application did not happen."
    )


def test_the_vulnerable_upstream_audience_check_is_gone(mw_exec):
    """The anti-assertion, and the one that survives a partial re-application.

    A patch that applies into changed context can leave both forms in the file.
    Asserting only that the fix is present would pass on that.
    """
    source = _read_in_container(
        mw_exec, "/var/www/html/w/vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php")
    assert OIDC_UPSTREAM not in source, (
        f"the unpatched upstream audience check {OIDC_UPSTREAM!r} is back in the "
        f"installed OIDC client. On PHP 8 that path raises a TypeError for a "
        f"string `aud` claim rather than validating it."
    )


def test_the_pluggableauth_weight_fix_is_in_the_installed_extension(mw_exec):
    source = _read_in_container(
        mw_exec, "/var/www/html/w/extensions/PluggableAuth/includes/PluggableAuthService.php")
    assert PA_PATCHED in source, (
        "the PluggableAuth weight fix is missing from the installed extension"
    )
    assert not PA_UPSTREAM_SHAPE.search(source), (
        "the unpatched upstream weight branch is back in PluggableAuthService.php"
    )


# ─── Layer 3: does any of it still run ──────────────────────────────

def test_the_patched_oidc_client_still_parses_and_autoloads(mw_exec):
    """A patch can apply cleanly and leave a file PHP cannot parse.

    `class_exists` with autoloading is the cheapest proof that the file both
    parses and is reachable through composer's autoloader — the two things a
    bad patch breaks, and neither of which a grep can see.
    """
    proc = mw_exec(
        "php", "-r",
        "require_once '/var/www/html/w/vendor/autoload.php';"
        "echo class_exists('Jumbojett\\\\OpenIDConnectClient') ? 'YES' : 'NO';",
    )
    assert proc.returncode == 0, (
        f"PHP failed while autoloading the OIDC client — a parse error in the "
        f"patched file looks exactly like this.\n{proc.stdout[-1500:]}\n{proc.stderr[-1500:]}"
    )
    assert "YES" in proc.stdout, (
        f"Jumbojett\\OpenIDConnectClient does not autoload from the installed "
        f"vendor tree.\n{proc.stdout[-1500:]}"
    )


def test_the_php_linter_accepts_both_patched_files(mw_exec):
    """`php -l` on each patched file. Explicit, and it names the file."""
    for path in (
        "/var/www/html/w/extensions/PluggableAuth/includes/PluggableAuthService.php",
        "/var/www/html/w/vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php",
    ):
        proc = mw_exec("php", "-l", path)
        assert proc.returncode == 0, (
            f"php -l rejects {path} after patching:\n{proc.stdout}\n{proc.stderr}"
        )


def test_pluggableauth_and_openidconnect_are_loaded(wiki):
    """Both extensions are registered with MediaWiki, not merely on disk."""
    # siteinfo() already unwraps the "query" envelope.
    names = {
        (e.get("name") or e.get("namemsg") or "")
        for e in wiki.siteinfo("extensions").get("extensions", [])
    }
    for wanted in ("PluggableAuth", "OpenID Connect"):
        assert any(wanted.lower() in n.lower() for n in names), (
            f"{wanted} is not among the loaded extensions. The patches above are "
            f"then guarding code the wiki never runs: {sorted(names)[:40]}"
        )


def test_local_login_still_works(wiki):
    """The session fixture logged in — assert it explicitly, here.

    Every other test in this tier depends on this having happened, which means
    a broken auth path shows up as forty confusing failures. This one names it.
    """
    assert wiki.logged_in_as == "Admin", (
        f"the integration client is authenticated as {wiki.logged_in_as!r}, not Admin"
    )


def test_the_login_form_renders_for_an_anonymous_visitor(anon):
    """The page that runs the patched PluggableAuth hook.

    `PluggableAuthService::onAuthChangeFormFields` — the method the
    pluggableauth-service patch rewrites — runs while MediaWiki builds this
    form. A fatal there takes login away from everyone, including the local
    login this deployment uses, so it is worth one HTTP request.
    """
    r = anon.fetch("/index.php/Special:UserLogin")
    assert r.status == 200, (
        f"Special:UserLogin returned HTTP {r.status} to an anonymous visitor — "
        f"nobody can log in.\nFirst 500 bytes: {r.text[:500]!r}"
    )
    found = [m for m in FATAL_MARKERS if m in r.text]
    assert not found, f"the login form rendered with PHP error markers: {found}"
    assert 'name="wpName"' in r.text or "wpName1" in r.text, (
        "Special:UserLogin returned 200 with no username field in it"
    )
