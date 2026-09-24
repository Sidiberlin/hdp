#!/usr/bin/env bats
# `docker compose config` sanity for the ingest-scheduler service across all
# four compose paths (R4/F19): it must resolve to the SAME image as
# haystack on the default and -f prod paths (so `docker compose build` hits
# cache instead of building the same context twice, and so a pulled prod
# image is the one both services actually run), and it must carry an nvidia
# device reservation on the two GPU paths — see docker-compose.gpu.yml's and
# docker-compose.prod-gpu.yml's headers for why a missing reservation here
# is the exact silent-CPU-fallback incident (F5) one override away from
# recurring.
#
# Skips cleanly (not a failure) when docker or `docker compose` is
# unavailable — the existing bats helpers' pattern; scripts/check.sh's own
# `compose` check does the equivalent skip for the same reason.

REPO_ROOT="$BATS_TEST_DIRNAME/../.."

have_compose() {
    command -v docker >/dev/null 2>&1 || return 1
    docker compose version >/dev/null 2>&1 || return 1
    docker info >/dev/null 2>&1 || return 1
}

setup() {
    have_compose || skip "docker / docker compose not available"

    REPO_ROOT="$(cd "$REPO_ROOT" && pwd)"
    cd "$REPO_ROOT" || return 1

    # docker-compose.yml declares `env_file: .env`, so compose refuses to
    # parse without one — materialise from .env.example if the repo has no
    # real .env (mirrors scripts/check.sh's check_compose_run()). Never
    # touches an existing .env.
    MADE_ENV=0
    if [ ! -f "$REPO_ROOT/.env" ]; then
        cp "$REPO_ROOT/.env.example" "$REPO_ROOT/.env"
        MADE_ENV=1
    fi
}

teardown() {
    [ "${MADE_ENV:-0}" -eq 1 ] && rm -f "$REPO_ROOT/.env"
    return 0
}

# service_field <compose-args...> -- <service> <field-path...> — prints one
# field from `docker compose config --format json`, via python (stdlib
# json — same reasoning as scripts/check.sh's own compose check: no PyYAML
# guaranteed on the host).
service_field() {
    local args=() service field
    while [ "$1" != "--" ]; do args+=("$1"); shift; done
    shift  # drop --
    service="$1"; shift
    field="$1"

    docker compose "${args[@]}" config --format json 2>/dev/null \
        | python3 -c "
import json, sys
d = json.load(sys.stdin)
svc = d['services'].get('$service', {})
keys = '$field'.split('.')
v = svc
for k in keys:
    v = v.get(k) if isinstance(v, dict) else None
print(v if v is not None else '')
"
}

# ─── default path: build, same image tag as haystack ──────────────────

@test "default path: ingest-scheduler resolves to the same image as haystack" {
    hay="$(service_field -f docker-compose.yml -- haystack image)"
    sched="$(service_field -f docker-compose.yml -- ingest-scheduler image)"
    [ -n "$hay" ]
    [ "$hay" = "$sched" ]
}

# ─── prod path: pulled, same image tag as haystack ─────────────────────

@test "prod path: ingest-scheduler resolves to the same published image as haystack" {
    hay="$(service_field -f docker-compose.yml -f docker-compose.prod.yml -- haystack image)"
    sched="$(service_field -f docker-compose.yml -f docker-compose.prod.yml -- ingest-scheduler image)"
    [ -n "$hay" ]
    [ "$hay" = "$sched" ]
    case "$hay" in
        ghcr.io/*) ;;
        *) echo "expected a ghcr.io image, got: $hay"; return 1 ;;
    esac
}

# ─── GPU paths: device reservation present ──────────────────────────────

@test "gpu path (source build): ingest-scheduler carries an nvidia device reservation" {
    docker compose -f docker-compose.yml -f docker-compose.gpu.yml \
        config 2>/dev/null > "$BATS_TEST_TMPDIR/gpu.yml"
    awk '/^  ingest-scheduler:/{grab=1} grab && /^  [a-z]/ && !/^  ingest-scheduler:/{grab=0} grab' \
        "$BATS_TEST_TMPDIR/gpu.yml" > "$BATS_TEST_TMPDIR/gpu_block.txt"
    grep -q 'driver: nvidia' "$BATS_TEST_TMPDIR/gpu_block.txt"
    grep -q 'gpu' "$BATS_TEST_TMPDIR/gpu_block.txt"
}

@test "prod-gpu path (published image): ingest-scheduler carries an nvidia device reservation" {
    docker compose -f docker-compose.yml -f docker-compose.prod-gpu.yml \
        config 2>/dev/null > "$BATS_TEST_TMPDIR/prodgpu.yml"
    awk '/^  ingest-scheduler:/{grab=1} grab && /^  [a-z]/ && !/^  ingest-scheduler:/{grab=0} grab' \
        "$BATS_TEST_TMPDIR/prodgpu.yml" > "$BATS_TEST_TMPDIR/prodgpu_block.txt"
    grep -q 'driver: nvidia' "$BATS_TEST_TMPDIR/prodgpu_block.txt"
    grep -q 'gpu' "$BATS_TEST_TMPDIR/prodgpu_block.txt"

    hay="$(service_field -f docker-compose.yml -f docker-compose.prod-gpu.yml -- haystack image)"
    sched="$(service_field -f docker-compose.yml -f docker-compose.prod-gpu.yml -- ingest-scheduler image)"
    [ -n "$hay" ]
    [ "$hay" = "$sched" ]
    case "$hay" in
        *-gpu) ;;
        *) echo "expected a -gpu image, got: $hay"; return 1 ;;
    esac
}

@test "the whole merged config parses on all four paths" {
    docker compose -f docker-compose.yml config --quiet
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config --quiet
    docker compose -f docker-compose.yml -f docker-compose.gpu.yml config --quiet
    docker compose -f docker-compose.yml -f docker-compose.prod-gpu.yml config --quiet
}
