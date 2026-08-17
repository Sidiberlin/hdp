"""The mediawiki-web healthcheck must probe the in-container URL.

A CMD-SHELL healthcheck runs inside the container, where Apache answers on
:8080 no matter which host port the service is published on. ${MW_DOCKER_PORT}
is the HOST-side port — compose interpolates it at parse time and hands the
probe a URL for a port nothing listens on in-container, so every
MW_DOCKER_PORT != 8080 deployment reported mediawiki-web permanently
unhealthy (QoL1 release QA Finding 6).

MW_DOCKER_PORT legitimately appears elsewhere in docker-compose.yml (MW_SERVER
default, service env, the ports mapping), so these assertions are scoped to
the mediawiki-web service block's `test:` lines only — a whole-file
MW_DOCKER_PORT ban is unfixably red.

Standard library only, no repo state beyond docker-compose.yml itself.
"""
import os
import re

import pytest

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))


def _mediawiki_web_block(text):
    """The `  mediawiki-web:` service block, from its header to the next
    two-space-indented service key."""
    m = re.search(r"^  mediawiki-web:.*?(?=^  \w|\Z)", text, re.M | re.S)
    assert m, (
        "no `  mediawiki-web:` service definition found in docker-compose.yml — "
        "the service was renamed or the file was restructured, and this pin "
        "needs its block-anchored regex rewritten to follow it."
    )
    return m.group(0)


def _assert_in_container_probe(block):
    """The assertion behind the pin, factored out so the goes-red control
    below can exercise it against a block that violates it."""
    test_lines = [line for line in block.splitlines() if "test:" in line]
    assert test_lines, (
        "mediawiki-web has no healthcheck `test:` line — the healthcheck "
        "itself went missing, and with it everything this file pins."
    )
    for line in test_lines:
        assert "http://localhost:8080/w/" in line, (
            f"mediawiki-web healthcheck no longer probes the in-container URL:\n"
            f"    {line}\n"
            f"A CMD-SHELL probe runs inside the container, where only :8080 "
            f"listens — see docker-compose.yml's ports mapping."
        )
        assert "MW_DOCKER_PORT" not in line, (
            f"mediawiki-web healthcheck interpolates the HOST port again:\n"
            f"    {line}\n"
            f"That is Finding 6 verbatim: on MW_DOCKER_PORT=8090 the probe "
            f"asked in-container for :8090 and the service went unhealthy. "
            f"Probe http://localhost:8080/w/ instead."
        )


def test_healthcheck_uses_the_in_container_port():
    path = os.path.join(REPO, "docker-compose.yml")
    text = open(path, encoding="utf-8").read()
    _assert_in_container_probe(_mediawiki_web_block(text))


def test_the_pin_actually_goes_red():
    """A pin that cannot fail proves nothing.

    This is the pre-fix probe verbatim — the exact line Finding 6 was filed
    against. If the assertion above were scoped wrong (say, matching no lines
    at all), this control would be the only thing that noticed.
    """
    broken = (
        "  mediawiki-web:\n"
        "    healthcheck:\n"
        '      test: ["CMD-SHELL", "curl -sf -o /dev/null '
        'http://localhost:${MW_DOCKER_PORT:-8080}/w/ || exit 1"]\n'
    )
    with pytest.raises(AssertionError, match="MW_DOCKER_PORT"):
        _assert_in_container_probe(broken)


def test_the_pin_still_passes_a_correct_block():
    """And the control's mirror: a correct probe must survive the assertion.

    Guards against an over-broad regex that fails every block it reads.
    """
    fixed = (
        "  mediawiki-web:\n"
        "    healthcheck:\n"
        '      test: ["CMD-SHELL", "curl -sf -o /dev/null '
        'http://localhost:8080/w/ || exit 1"]\n'
    )
    _assert_in_container_probe(fixed)
