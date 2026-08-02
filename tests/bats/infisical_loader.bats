#!/usr/bin/env bats
# Behavioural tests for docker/infisical-loader.sh.
#
# This file is sourced — not executed — by both docker/setup.sh and
# docker/haystack/entrypoint.sh, and setup.sh runs under `set -euo pipefail`.
# That combination is what makes it worth testing at this level: a mistake here
# does not produce a failing script, it produces a wiki that never installs, or
# a container whose secrets are readable by anything else running in it.
#
# Two Wave 0 fixes are the reason this exists. Both regress silently:
#
#   a3f505d98  default the four INFISICAL_* vars so `set -u` cannot abort
#              setup.sh. Without the `:-` defaults, sourcing this file under
#              `set -u` with an unconfigured environment kills the caller —
#              no message, no fallback, no wiki.
#
#   2ce40551f  read the bearer token from stdin instead of argv
#   61a1406b3  send the client secret over stdin, not argv
#              Anything in argv is world-readable via /proc/<pid>/cmdline for
#              the lifetime of the request, by any process in the container.
#
# curl is a test double (helpers/bin/curl) that records its own
# /proc/self/cmdline. That is what an attacker actually reads, and unlike
# `ps aux` it cannot miss the window in which the process is alive.
# jq and the loader itself are real.

setup() {
    LOADER="$BATS_TEST_DIRNAME/../../docker/infisical-loader.sh"
    [ -f "$LOADER" ] || {
        echo "loader not found at $LOADER" >&2
        return 1
    }

    export CURL_ARGV_LOG="$BATS_TEST_TMPDIR/argv.log"
    export CURL_STDIN_LOG="$BATS_TEST_TMPDIR/stdin.log"
    export CURL_FIXTURE_DIR="$BATS_TEST_TMPDIR/fixtures"
    mkdir -p "$CURL_FIXTURE_DIR"
    : > "$CURL_ARGV_LOG"
    : > "$CURL_STDIN_LOG"

    # The shim shadows any real curl.
    PATH="$BATS_TEST_DIRNAME/helpers/bin:$PATH"
    export PATH

    CLIENT_SECRET='s3cr3t-client-value-DO-NOT-LEAK'
    TOKEN='bearer-token-value-DO-NOT-LEAK'
    export CLIENT_SECRET TOKEN
}

# Write the happy-path fixture set: a valid token, a secret list, and values.
given_working_infisical() {
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    cat > "$CURL_FIXTURE_DIR/list.json" <<'JSON'
{"secrets": [
  {"secretKey": "HDP_DB_PASSWORD"},
  {"secretKey": "HDP_LLM_API_KEY"},
  {"secretKey": "NOT_HDP_SECRET"}
]}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-HDP_DB_PASSWORD.json" <<'JSON'
{"secret": {"secretValue": "db-password-from-infisical"}}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-HDP_LLM_API_KEY.json" <<'JSON'
{"secret": {"secretValue": "llm-key-from-infisical"}}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-default.json" <<'JSON'
{"secret": {"secretValue": "some-other-value"}}
JSON
}

configured_env() {
    export INFISICAL_URL="https://infisical.example.test"
    export INFISICAL_PROJECT_ID="proj-1234"
    export INFISICAL_CLIENT_ID="client-1234"
    export INFISICAL_CLIENT_SECRET="$CLIENT_SECRET"
    export INFISICAL_ENV="prod"
}

# Source the loader in a child bash under the same options setup.sh uses.
# Sourcing rather than executing is what setup.sh does, and it is the case
# where `return` vs `exit` and `set -u` actually matter.
source_under_set_u() {
    bash -c '
        set -euo pipefail
        source "$1"
        echo "CALLER_SURVIVED"
        echo "DB=${HDP_DB_PASSWORD:-<unset>}"
        echo "LLM=${HDP_LLM_API_KEY:-<unset>}"
        echo "OTHER=${NOT_HDP_SECRET:-<unset>}"
    ' _ "$LOADER"
}

# ─── The set -u abort (Wave 0 fix a3f505d98) ────────────────────────────

@test "sourcing under set -u with nothing configured does not kill the caller" {
    # No INFISICAL_* variables exist at all. Before the fix, the bare
    # "${INFISICAL_URL}" expansion aborted setup.sh here with no message.
    unset INFISICAL_URL INFISICAL_PROJECT_ID INFISICAL_CLIENT_ID \
          INFISICAL_CLIENT_SECRET INFISICAL_ENV || true

    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    [[ "$output" == *"credentials not set"* ]]
    [[ "$output" == *"Falling back to .env plaintext values"* ]]
}

@test "each of the four required variables can be missing on its own" {
    # Compose always passes all four (empty when unconfigured), which is the
    # only reason the original bug stayed hidden. Running setup.sh outside
    # compose does not, so every variable needs its own default.
    for missing in INFISICAL_URL INFISICAL_PROJECT_ID INFISICAL_CLIENT_ID INFISICAL_CLIENT_SECRET; do
        configured_env
        unset "$missing"
        run source_under_set_u
        [ "$status" -eq 0 ] || {
            echo "aborted the caller when $missing was unset" >&2
            return 1
        }
        [[ "$output" == *"CALLER_SURVIVED"* ]]
    done
}

@test "an empty client secret falls back rather than attempting login" {
    configured_env
    export INFISICAL_CLIENT_SECRET=""
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"credentials not set"* ]]
    # No HTTP call should have been made at all.
    [ ! -s "$CURL_ARGV_LOG" ]
}

@test "the loader returns rather than exits, so a sourcing caller continues" {
    unset INFISICAL_URL || true
    run bash -c '
        set -euo pipefail
        source "$1"
        echo "STILL_RUNNING"
        exit 7
    ' _ "$LOADER"
    # Exit 7 proves the caller reached its own exit rather than the loader
    # exiting 0 on its behalf.
    [ "$status" -eq 7 ]
    [[ "$output" == *"STILL_RUNNING"* ]]
}

# ─── The argv leak (Wave 0 fixes 61a1406b3 / 2ce40551f) ─────────────────

@test "the client secret never appears in any curl argv" {
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]

    grep -q . "$CURL_ARGV_LOG" || {
        echo "no curl calls recorded — the test proved nothing" >&2
        return 1
    }
    run grep -F "$CLIENT_SECRET" "$CURL_ARGV_LOG"
    [ "$status" -ne 0 ] || {
        echo "client secret found in curl argv / proc cmdline" >&2
        return 1
    }
}

@test "the bearer token never appears in any curl argv" {
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]

    run grep -F "$TOKEN" "$CURL_ARGV_LOG"
    [ "$status" -ne 0 ] || {
        echo "bearer token found in curl argv / proc cmdline" >&2
        return 1
    }
}

@test "the client secret does reach curl, on stdin" {
    # The mirror of the leak tests. Without this, deleting the secret entirely
    # would satisfy both of them.
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    grep -qF "$CLIENT_SECRET" "$CURL_STDIN_LOG"
    grep -qF "clientId=client-1234" "$CURL_STDIN_LOG"
}

@test "the bearer token does reach curl, on stdin as an Authorization header" {
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    grep -qF "Authorization: Bearer $TOKEN" "$CURL_STDIN_LOG"
}

@test "no secret value is written to any file the loader creates" {
    configured_env
    given_working_infisical
    workdir="$BATS_TEST_TMPDIR/work"
    mkdir -p "$workdir"
    run bash -c 'set -euo pipefail; cd "$2"; source "$1"' _ "$LOADER" "$workdir"
    [ "$status" -eq 0 ]
    # The loader's own contract: "No secrets written to disk, logged, or committed."
    [ -z "$(ls -A "$workdir")" ]
}

@test "no secret value is echoed to stdout" {
    configured_env
    given_working_infisical
    # Deliberately NOT source_under_set_u: that helper prints the exported
    # values, which is the whole point of it and would trivially fail this.
    run bash -c 'set -euo pipefail; source "$1"; echo CALLER_SURVIVED' _ "$LOADER"
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    for needle in "$CLIENT_SECRET" "$TOKEN" "db-password-from-infisical" "llm-key-from-infisical"; do
        [[ "$output" != *"$needle"* ]] || {
            echo "secret material appeared in output: $needle" >&2
            return 1
        }
    done
}

# ─── Fetching and exporting ─────────────────────────────────────────────

@test "HDP_ secrets are exported into the caller's environment" {
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"DB=db-password-from-infisical"* ]]
    [[ "$output" == *"LLM=llm-key-from-infisical"* ]]
}

@test "non-HDP secrets are neither fetched nor exported" {
    # The loader filters on the HDP_ prefix specifically to avoid polluting the
    # environment of a container that also holds unrelated project secrets.
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"OTHER=<unset>"* ]]
    run grep -F "FETCHED	NOT_HDP_SECRET" "$CURL_ARGV_LOG"
    [ "$status" -ne 0 ]
}

@test "the loaded count reflects only the HDP_ secrets" {
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"Loaded 2 secrets from Infisical"* ]]
}

@test "a secret name that is not a valid shell identifier is skipped, not fatal" {
    # `export "bad name=v"` fails, and under the caller's set -e that aborted
    # setup.sh. Found by this suite; fixed alongside it.
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    cat > "$CURL_FIXTURE_DIR/list.json" <<'JSON'
{"secrets": [{"secretKey": "HDP_WEIRD NAME/WITH+CHARS"}, {"secretKey": "HDP_GOOD"}]}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-default.json" <<'JSON'
{"secret": {"secretValue": "v"}}
JSON
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    [[ "$output" == *"not a valid shell identifier"* ]]
    # The well-named secret alongside it must still load.
    [[ "$output" == *"Loaded 1 secrets"* ]]
}

@test "secret names are URL-encoded in the per-secret request" {
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    cat > "$CURL_FIXTURE_DIR/list.json" <<'JSON'
{"secrets": [{"secretKey": "HDP_WEIRD NAME/WITH+CHARS"}]}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-default.json" <<'JSON'
{"secret": {"secretValue": "v"}}
JSON
    run source_under_set_u
    [ "$status" -eq 0 ]
    # jq -sRr @uri is what encodes it; a raw space in a URL is a malformed request.
    grep -q "HDP_WEIRD%20NAME%2FWITH%2BCHARS" "$CURL_ARGV_LOG"
}

@test "a secret value containing spaces and quotes survives intact" {
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    cat > "$CURL_FIXTURE_DIR/list.json" <<'JSON'
{"secrets": [{"secretKey": "HDP_TRICKY"}]}
JSON
    cat > "$CURL_FIXTURE_DIR/secret-HDP_TRICKY.json" <<'JSON'
{"secret": {"secretValue": "a b \"c\" $d 'e'"}}
JSON
    run bash -c '
        set -euo pipefail
        source "$1"
        printf "VALUE=[%s]\n" "${HDP_TRICKY:-<unset>}"
    ' _ "$LOADER"
    [ "$status" -eq 0 ]
    [[ "$output" == *'VALUE=[a b "c" $d '"'"'e'"'"']'* ]]
}

# ─── Degrade paths ──────────────────────────────────────────────────────

@test "an auth failure falls back without killing the caller" {
    configured_env
    echo '{"error": "invalid credentials"}' > "$CURL_FIXTURE_DIR/login.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"Authentication failed"* ]]
    [[ "$output" == *"Falling back to .env plaintext values"* ]]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
}

@test "an unreachable Infisical falls back without killing the caller" {
    # curl exits 7 when it cannot connect. Under the caller's `set -euo
    # pipefail` that aborted setup.sh with exit 7 and no output at all — the
    # single most likely way to hit this file's failure path, since a mistyped
    # INFISICAL_URL is enough. Found by this suite; fixed alongside it.
    configured_env
    PATH="$BATS_TEST_DIRNAME/helpers/failbin:$PATH"
    export PATH
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    [[ "$output" == *"Authentication failed"* ]]
    [[ "$output" == *"Falling back to .env plaintext values"* ]]
}

@test "an unreachable Infisical leaves existing .env values in place" {
    configured_env
    PATH="$BATS_TEST_DIRNAME/helpers/failbin:$PATH"
    export PATH
    run bash -c '
        set -euo pipefail
        HDP_DB_PASSWORD="from-dotenv"
        export HDP_DB_PASSWORD
        source "$1"
        echo "DB=${HDP_DB_PASSWORD}"
    ' _ "$LOADER"
    [ "$status" -eq 0 ]
    [[ "$output" == *"DB=from-dotenv"* ]]
}

@test "a non-JSON secret list falls back without killing the caller" {
    # jq exits 5 on input that is not JSON. Same crash, second call site.
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    printf '<html><body>502 Bad Gateway</body></html>' > "$CURL_FIXTURE_DIR/list.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    [[ "$output" == *"No HDP_ secrets found"* ]]
}

@test "a non-JSON per-secret response skips that secret without killing the caller" {
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    echo '{"secrets": [{"secretKey": "HDP_DB_PASSWORD"}]}' > "$CURL_FIXTURE_DIR/list.json"
    printf 'not json' > "$CURL_FIXTURE_DIR/secret-HDP_DB_PASSWORD.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
    [[ "$output" == *"Loaded 0 secrets"* ]]
}

@test "an unparseable login response falls back without killing the caller" {
    configured_env
    printf 'this is not json at all' > "$CURL_FIXTURE_DIR/login.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"Authentication failed"* ]]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
}

@test "an empty response body falls back without killing the caller" {
    configured_env
    : > "$CURL_FIXTURE_DIR/login.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"Authentication failed"* ]]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
}

@test "no HDP_ secrets in the project warns and returns cleanly" {
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    echo '{"secrets": [{"secretKey": "UNRELATED"}]}' > "$CURL_FIXTURE_DIR/list.json"
    run source_under_set_u
    [ "$status" -eq 0 ]
    [[ "$output" == *"No HDP_ secrets found"* ]]
    [[ "$output" == *"CALLER_SURVIVED"* ]]
}

@test "a secret whose value fetch returns nothing is skipped, not exported empty" {
    # Exporting an empty HDP_DB_PASSWORD would be worse than leaving the .env
    # value in place — it silently overrides a working configuration.
    configured_env
    cat > "$CURL_FIXTURE_DIR/login.json" <<JSON
{"accessToken": "$TOKEN"}
JSON
    echo '{"secrets": [{"secretKey": "HDP_DB_PASSWORD"}]}' > "$CURL_FIXTURE_DIR/list.json"
    echo '{"secret": {}}' > "$CURL_FIXTURE_DIR/secret-HDP_DB_PASSWORD.json"
    run bash -c '
        set -euo pipefail
        HDP_DB_PASSWORD="from-dotenv"
        export HDP_DB_PASSWORD
        source "$1"
        echo "DB=${HDP_DB_PASSWORD}"
    ' _ "$LOADER"
    [ "$status" -eq 0 ]
    [[ "$output" == *"DB=from-dotenv"* ]]
    [[ "$output" == *"Loaded 0 secrets"* ]]
}

@test "every secret in the list is fetched, not just the first" {
    # The list is iterated with a here-string while each fetch pipes into curl.
    # If curl's stdin were left attached to the loop's here-string instead of
    # the pipe, the first fetch would swallow the remaining names.
    configured_env
    given_working_infisical
    run source_under_set_u
    [ "$status" -eq 0 ]
    grep -qF "FETCHED	HDP_DB_PASSWORD" "$CURL_ARGV_LOG"
    grep -qF "FETCHED	HDP_LLM_API_KEY" "$CURL_ARGV_LOG"
}

# ─── Executed directly rather than sourced ──────────────────────────────

@test "running the file directly exits 0 instead of returning" {
    # `return 0 2>/dev/null || exit 0` has to work both ways: entrypoint.sh and
    # setup.sh source it, but a human debugging will just run it.
    unset INFISICAL_URL || true
    run bash "$LOADER"
    [ "$status" -eq 0 ]
    [[ "$output" == *"credentials not set"* ]]
}
