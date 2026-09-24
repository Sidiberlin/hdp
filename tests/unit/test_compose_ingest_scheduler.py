"""Pins on the `ingest-scheduler` service block across all four compose
files, in the style of test_compose_healthcheck.py — block-anchored regexes
against the committed YAML text, no docker, no running stack.

The specific failure this guards against (F4): docker/haystack/Dockerfile
has no `CMD`, so a compose `command:` override on this image is appended as
arguments to `entrypoint.sh`, which ignores them and boots the whole RAG
stack (hayhooks + the query API) a second time instead of the scheduler
loop. `entrypoint:` is the only override that actually replaces PID 1.

Standard library only, no repo state beyond the four compose files.
"""
import os
import re

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

COMPOSE_FILES = (
    "docker-compose.yml",
    "docker-compose.prod.yml",
    "docker-compose.gpu.yml",
    "docker-compose.prod-gpu.yml",
)


def _read(name):
    with open(os.path.join(REPO, name), encoding="utf-8") as f:
        return f.read()


def _service_block(text, service):
    """The `  <service>:` block, from its header to the next
    two-space-indented service key (or EOF)."""
    m = re.search(rf"^  {re.escape(service)}:.*?(?=^  \w|\Z)", text, re.M | re.S)
    assert m, (
        f"no `  {service}:` service definition found — the service was "
        f"renamed or the file was restructured, and this pin needs its "
        f"block-anchored regex rewritten to follow it."
    )
    return m.group(0)


# ─── docker-compose.yml: the full service definition ──────────────────


def test_ingest_scheduler_overrides_entrypoint_not_command():
    block = _service_block(_read("docker-compose.yml"), "ingest-scheduler")
    assert "entrypoint:" in block, (
        "ingest-scheduler must override entrypoint: — docker/haystack/Dockerfile "
        "has no CMD, so a command: override is appended as arguments to "
        "entrypoint.sh (F4), which boots the whole RAG stack instead of the "
        "scheduler loop."
    )
    assert not re.search(r"^\s*command:", block, re.M), (
        "ingest-scheduler must NOT set command: — see F4. This is the exact "
        "failure mode that silently starts a second full RAG stack."
    )


def test_ingest_scheduler_has_a_healthcheck():
    block = _service_block(_read("docker-compose.yml"), "ingest-scheduler")
    assert "healthcheck:" in block, (
        "ingest-scheduler has no healthcheck (F14) — a service with none is "
        "treated as unhealthy by scripts/ci/t4-smoke.sh's not_healthy(), which "
        "would hang T4 (if this service is ever added to HDP_T4_SERVICES) "
        "until its budget expires."
    )


def test_ingest_scheduler_mounts_haystack_state():
    block = _service_block(_read("docker-compose.yml"), "ingest-scheduler")
    assert "haystack_state:/var/lib/hdp-ingest" in block, (
        "ingest-scheduler must mount the haystack_state volume at "
        "/var/lib/hdp-ingest (D4) — without it, the scheduler's flock is "
        "local to its own container and never sees a concurrent ingestion "
        "in the haystack container."
    )


def test_ingest_scheduler_shares_haystacks_image_tag():
    text = _read("docker-compose.yml")
    haystack = _service_block(text, "haystack")
    scheduler = _service_block(text, "ingest-scheduler")
    hay_image = re.search(r"^\s*image:\s*(\S+)", haystack, re.M)
    sched_image = re.search(r"^\s*image:\s*(\S+)", scheduler, re.M)
    assert hay_image and sched_image, "both haystack and ingest-scheduler must set image:"
    assert hay_image.group(1) == sched_image.group(1), (
        "haystack and ingest-scheduler must build the identical image tag "
        "(R4) — otherwise `docker compose build` builds the same context "
        "twice under two different implicit names instead of hitting cache."
    )


# ─── appears in all four compose files ─────────────────────────────────


def test_ingest_scheduler_present_in_all_four_compose_files():
    for name in COMPOSE_FILES:
        text = _read(name)
        assert re.search(r"^  ingest-scheduler:", text, re.M), (
            f"{name} has no `  ingest-scheduler:` service — F19: every built "
            f"service needs an entry in all four compose files to behave on "
            f"all four install paths."
        )


def test_prod_paths_pull_instead_of_build():
    """docker-compose.prod.yml and docker-compose.prod-gpu.yml must pull the
    published image rather than building — mirrors haystack's own
    build: !reset null in the same files."""
    for name in ("docker-compose.prod.yml", "docker-compose.prod-gpu.yml"):
        block = _service_block(_read(name), "ingest-scheduler")
        assert "build: !reset null" in block, (
            f"{name}'s ingest-scheduler must reset build: to null, like "
            f"haystack's override in the same file — otherwise it is built, "
            f"not pulled, on a host without the image (see check.sh's compose "
            f"check for the equivalent assertion on haystack/chatbot-proxy/opensearch)."
        )
        assert "ghcr.io/" in block, f"{name}'s ingest-scheduler must pull from ghcr.io"


def test_gpu_paths_reserve_a_device_and_set_the_device_env():
    """docker-compose.gpu.yml and docker-compose.prod-gpu.yml must reserve
    the GPU for ingest-scheduler too — otherwise it inherits the CPU
    default from docker-compose.yml while haystack gets the GPU, which is
    the exact silent-CPU-fallback incident F5 documents, one override away
    from recurring here."""
    for name in ("docker-compose.gpu.yml", "docker-compose.prod-gpu.yml"):
        block = _service_block(_read(name), "ingest-scheduler")
        assert "driver: nvidia" in block and "capabilities: [gpu]" in block, (
            f"{name}'s ingest-scheduler must reserve an nvidia GPU device"
        )
        assert re.search(r"HAYSTACK_DEVICE:\s*gpu", block), (
            f"{name}'s ingest-scheduler must set HAYSTACK_DEVICE=gpu"
        )
