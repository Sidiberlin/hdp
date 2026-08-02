"""The clientlogin consent-step parsing, tested without a wiki.

This exists because of a specific failure. The first T3 run on GitHub Actions
came back 14 passed, 27 errors, every error the same line — "clientlogin
returned UI with no fields to fill" — because `_ui_fields` read only the
formatversion 1 shape (`request`, a dict) and BlueSpicePrivacy answers
formatversion 2 (`requests`, a list).

What made it expensive is that it is invisible everywhere except a fresh
install. The consent step is skipped for an account that has already consented,
so any wiki that has been logged into before returns PASS on the first call and
never reaches this code. It passed local runs, passed against a stack from an
earlier wave, and failed the moment CI installed a wiki from empty volumes.

The parsing is pure, so it does not need a wiki at all — only the payloads.
Those are recorded below verbatim from the failing run and from
`docker/haystack/ingest_hdp_wiki.py`'s older, working implementation of the
same flow. A regression here now costs two seconds in the unit tier instead of
a five-minute CI job that boots four containers first.
"""
import pytest
from wikiclient import WikiClient

# Recorded verbatim from the failing GitHub Actions run (formatversion 2).
# Labels and help text trimmed; structure and field types are exact.
BLUESPICE_CONSENT_FV2 = {
    "status": "UI",
    "requests": [
        {
            "id": "BlueSpicePrivacyConsentAuthenticationRequest",
            "metadata": {},
            "required": "required",
            "provider": "BlueSpice\\Privacy\\Auth\\Request\\ConsentAuthenticationRequest",
            "account": "BlueSpicePrivacyConsentAuthenticationRequest",
            "fields": {
                "privacy-policy": {
                    "type": "checkbox",
                    "label": "Ich akzeptiere die Datenschutzrichtlinie dieser Website",
                    "optional": True,
                    "sensitive": False,
                },
                "terms-of-service": {
                    "type": "checkbox",
                    "label": "Ich akzeptiere die Servicebedingungen dieser Website",
                    "optional": True,
                    "sensitive": False,
                },
            },
        }
    ],
    "message": "Bitte akzeptiere die folgenden Nutzungsbedingungen",
    "messagecode": "bs-privacy-consent-auth-step",
}

# The same step as formatversion 1 renders it. Still parsed, because the shape
# is a function of a request parameter rather than of the wiki, and a caller
# that omits formatversion gets this one.
BLUESPICE_CONSENT_FV1 = {
    "status": "UI",
    "request": {
        "id": "BlueSpicePrivacyConsentAuthenticationRequest",
        "fields": {
            "privacy-policy": {"type": "checkbox", "optional": True},
            "terms-of-service": {"type": "checkbox", "optional": True},
        },
    },
}


def test_reads_the_formatversion_2_requests_array():
    """The exact regression. This is the assertion CI needed and did not have."""
    assert sorted(WikiClient._ui_fields(BLUESPICE_CONSENT_FV2)) == [
        "privacy-policy",
        "terms-of-service",
    ]


def test_reads_the_formatversion_1_request_object():
    assert sorted(WikiClient._ui_fields(BLUESPICE_CONSENT_FV1)) == [
        "privacy-policy",
        "terms-of-service",
    ]


def test_collects_fields_across_several_requests():
    """Several providers can interpose at once; all of them must be answered.

    Answering only the first leaves the step incomplete, and clientlogin
    returns UI again — which the caller's bounded loop turns into a failure
    rather than a hang.
    """
    payload = {
        "status": "UI",
        "requests": [
            {"fields": {"privacy-policy": {"type": "checkbox"}}},
            {"fields": {"terms-of-service": {"type": "checkbox"}}},
            {"fields": {"some-other-consent": {"type": "checkbox"}}},
        ],
    }
    assert sorted(WikiClient._ui_fields(payload)) == [
        "privacy-policy",
        "some-other-consent",
        "terms-of-service",
    ]


def test_ignores_non_checkbox_fields():
    """Only checkboxes are answered, matching ingest_hdp_wiki.mw_api_login().

    The caller answers every returned field with "1", which means "ticked" for
    a checkbox and nothing coherent for a password or a text box. A future
    provider that asks for a one-time code must not be sent "1" and told the
    step was satisfied.
    """
    payload = {
        "status": "UI",
        "requests": [
            {
                "fields": {
                    "privacy-policy": {"type": "checkbox"},
                    "OATHToken": {"type": "string", "label": "One-time code"},
                    "password": {"type": "password", "sensitive": True},
                }
            }
        ],
    }
    assert WikiClient._ui_fields(payload) == ["privacy-policy"]


@pytest.mark.parametrize(
    "payload",
    [
        {"status": "UI"},
        {"status": "UI", "requests": []},
        {"status": "UI", "requests": [{}]},
        {"status": "UI", "requests": [{"fields": {}}]},
        {"status": "UI", "request": {}},
        {"status": "UI", "requests": None, "request": None},
        {"status": "UI", "requests": [{"fields": {"code": {"type": "string"}}}]},
    ],
)
def test_returns_nothing_rather_than_raising_on_shapes_it_cannot_fill(payload):
    """An unfillable step yields no fields, never an exception.

    `login()` turns the empty result into a LoginError naming the payload it
    could not handle — which is how the original bug reported itself, and is a
    far better failure than a KeyError or a TypeError from inside the parser.
    """
    assert WikiClient._ui_fields(payload) == []


def test_field_order_is_stable_for_a_single_request():
    """Deterministic order, so a failure message reads the same twice."""
    payload = {
        "status": "UI",
        "requests": [
            {
                "fields": {
                    "b-consent": {"type": "checkbox"},
                    "a-consent": {"type": "checkbox"},
                }
            }
        ],
    }
    assert WikiClient._ui_fields(payload) == ["b-consent", "a-consent"]
