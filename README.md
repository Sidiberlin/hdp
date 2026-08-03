# HDP QoL Fork — Self-Hosted Enterprise Wiki with AI Q&A

A Docker-deployable enterprise wiki (BlueSpice / MediaWiki) with a
self-hosted, AI-powered chatbot that answers natural-language questions
grounded in your wiki content — no external cloud dependency required.

## TL;DR

**What is it?** This project packages BlueSpice HDP Edition — a full
enterprise wiki built on MediaWiki with 167 extensions for document
workflows, access control, and semantic content — together with a
retrieval-augmented generation (RAG) chatbot that answers questions from
your wiki pages, citing its sources. Everything runs in Docker on your own
infrastructure.

**Who is it for?** Organisations that need a professional knowledge base —
structured content, page-level permissions, review workflows, LDAP/OIDC
login — and want an AI assistant layered on top that actually knows their
documentation, without sending that documentation to a third-party SaaS.

**What problem does it solve?** Large wikis are hard to search. Users don't
know which page has the answer, or the answer is scattered across several
pages. The chatbot lets people ask a question in plain language and get a
grounded answer with links back to the source pages, instead of guessing at
search terms.

**Why this fork?** The original BlueSpice HDP Edition depends on
[deepset Cloud](https://www.deepset.ai/), a paid external SaaS, for the AI
ingestion and retrieval pipeline. This fork replaces that dependency with a
fully self-hosted [Haystack](https://haystack.deepset.ai/) RAG pipeline and
adds a one-command Docker deployment (`docker compose up -d --build`), plus
the setup scripts, health checks, and documentation needed to actually stand
the thing up. The self-hosted ingestion is simpler than what deepset Cloud
offers and won't match it feature-for-feature — but it's free, it's yours,
and your data never leaves your infrastructure.

## Feature Comparison

### Enterprise Wiki Features

| Feature | MediaWiki (vanilla) | BlueSpice (HDP Edition) | HDP QoL Fork (this project) |
|---|---|---|---|
| Visual Editor / WYSIWYG editing | ✗ | ✅ | ✅ |
| Document review workflows & content stabilization | ✗ | ✅ | ✅ |
| Page-level access control & permission management | ✗ | ✅ | ✅ |
| Bookshelf / manual organisation | ✗ | ✅ | ✅ |
| Advanced search (faceted, file content) | ✗ | ✅ | ✅ |
| Checklists, ratings, reminders on pages | ✗ | ✅ | ✅ |
| User management & group permissions | Basic | ✅ | ✅ |
| RSS feeds, tag clouds | ✗ | ✅ | ✅ |
| Statistics & analytics | Basic | ✅ | ✅ |
| Multi-language / translation support | ✗ | ✅ | ✅ |
| Export (PDF, Word, Excel) | ✗ | ✅ | ✅ |
| Draw.io / diagram editing | ✗ | ✅ | ✅ |
| Semantic data (Semantic MediaWiki + result formats) | ✗ | ✅ | ✅ |
| Blog / comment streams | ✗ | ✅ | ✅ |
| File uploads with versioning & namespace restrictions | Basic | ✅ | ✅ |
| LDAP / OIDC / SAML authentication | ✗ | ✅ | ✅ |

### AI / RAG Capabilities

| Feature | MediaWiki (vanilla) | BlueSpice (HDP Edition) | HDP QoL Fork (this project) |
|---|---|---|---|
| Chatbot in the wiki UI | ✗ | ✅ (requires deepset Cloud) | ✅ (self-hosted) |
| Self-hosted RAG pipeline, no external cloud dependency | ✗ | ✗ | ✅ |
| Hybrid retrieval (BM25 keyword + semantic embedding search) | ✗ | ✅ (via deepset Cloud) | ✅ (self-hosted) |
| Cross-encoder reranking (German-optimized) | ✗ | ✅ (via deepset Cloud) | ✅ (self-hosted) |
| Source citation on every answer | ✗ | ✅ | ✅ |
| Configurable LLM backend (OpenAI-compatible: z.ai GLM, OpenAI GPT, local models) | ✗ | ✗ | ✅ |
| Configurable embedding provider (local CPU / remote API / HF Space bulk ingestion) | ✗ | ✗ | ✅ |
| Incremental ingestion (re-index only new/changed pages) | ✗ | ✅ (via deepset Cloud) | ✅ (self-hosted) |
| Feedback mechanism (rate chatbot answers from the UI) | ✗ | ✅ | ✅ |

### DevOps / Deployment

| Feature | MediaWiki (vanilla) | BlueSpice (HDP Edition) | HDP QoL Fork (this project) |
|---|---|---|---|
| One-command Docker deployment | ✗ | ✗ | ✅ (`docker compose up -d --build`) |
| Fully orchestrated services (wiki + search + RAG pipeline) | ✗ | ✗ | ✅ (7 services) |
| Infisical secret management (optional) | ✗ | ✗ | ✅ |
| `.env.example` with documented configuration | ✗ | ✗ | ✅ |
| Automated first-boot setup | ✗ | ✗ | ✅ (`setup.sh`) |
| Healthchecks on all services | ✗ | ✗ | ✅ |
| Idempotent ingestion (safe to re-run) | ✗ | ✗ | ✅ |
| CI (lint, secret scan, schema, fresh-clone gate) | ✗ | ✗ | ✅ (16 gates, GitHub + GitLab) |
| Integration tests against a live wiki | ✗ | ✗ | ✅ (T3: `scripts/ci/t3-integration.sh`) |
| Full-stack smoke: search results + chatbot, nightly | ✗ | ✗ | ✅ (T4: `scripts/ci/t4-smoke.sh`) |
| Upgrade tested, not just install (`update.php` on real data) | ✗ | ✗ | ✅ (T5: `scripts/ci/t5-migration.sh`) |
| One-command local gate matching CI | ✗ | ✗ | ✅ (`./scripts/check.sh`, ~30s) |
| Patch manifest + integrity verification | ✗ | ✗ | ✅ (19 patches, `scripts/verify-patches.sh`) |
| One declared version, gated against the tree | ✗ | ✗ | ✅ (`VERSIONS.yml`) |
| CVE monitoring: composer audit, Renovate, release watch | ✗ | ✗ | ✅ (see [`SECURITY.md`](SECURITY.md)) |
| Documented upgrade + rollback procedure | ✗ | ✗ | ✅ ([`upgrade-runbook.md`](docs/dev/upgrade-runbook.md)) |

*Note: the table groups related capabilities rather than listing all 167
bundled extensions individually — see [`docs/dev/AGENTS.md`](docs/dev/AGENTS.md)
for the full extension inventory.*

## Architecture at a Glance

The wiki (BlueSpice/MediaWiki) talks to a chatbot proxy that translates
between BlueSpice's expected chat API format and Haystack's `hayhooks` API.
Haystack runs the RAG pipeline — hybrid BM25 + embedding retrieval, German
cross-encoder reranking, and LLM generation with citations — backed by
OpenSearch as the combined vector and keyword store. All of it runs as
Docker services alongside MariaDB and the MediaWiki job runner. See
[README-DOCKER.md](README-DOCKER.md) for the full setup and
[`docs/dev/AGENTS.md`](docs/dev/AGENTS.md) for the complete architecture
documentation.

## Getting Started

See [README-DOCKER.md](README-DOCKER.md) for hardware requirements,
configuration (`.env.example`), and the one-command deployment flow.

## License

GPL-3.0-only.

---

## Original Project Description / Ursprüngliche Projektbeschreibung

*The following section is the original German-language project description
from the upstream BMBF-funded project, kept here for historical context.*

**Original repository:** [gitlab.opencode.de/bmbf/teamdigital/hdp](https://gitlab.opencode.de/bmbf/teamdigital/hdp)

### Chatbot für Handbuch der Projektförderung

Der Ordner `/app` enthält ein erweitertes Open-Source-Enterprise-Wiki BlueSpice (BlueSpice HDP Edition), die zugehörige KI-Pipeline findet sich im Ordner `/pipeline`.

Die Edition besteht aus BlueSpice pro, der BlueSpice-Vollversion zum Betrieb von Einzelwikis. BlueSpice pro bündelt die wichtigsten Funktionen für ein Produktivsystem, das den Ansprüchen eines professionell geführten Unternehmens gerecht wird.

Zusätzlich verfügt diese Edition über ein Chat-UI, eine Anbindung an ein mit Haystack bereitgestelltes Large-Language Modell und RAG-System, Maintainer-Funktionen (Ansehen und Bearbeiten von Nutzer-Feedback, Prüfung gefundener Quellen, Ergänzung von Metadaten) sowie die Möglichkeit zur Zugriffsbeschränkung auf bestimmte Dokumente.

Durch die Einbindung eines Large Language Models über Haystack (D-Stack Standard für KI) wird der Zugriff auf Inhalte eines Wikis personenunabhängiger, benutzerfreundlicher und inklusiver.

Der enthaltene Haystack Code umfasst die vollständige KI-Pipeline. Das Deployment der Pipeline erfolgt via hayhooks und muss individuell eingerichtet werden. Die Einbindung des gewünschten Large Language Models erfolgt über eine API.

Die Lösung wurde gemeinsam entwickelt vom Bundesministerium für Bildung und Forschung, der deepset GmbH, dem GovTech Campus Deutschland e.V., der Hallo Welt! GmbH sowie dem Fraunhofer IVV. Sie wurde im KI Transarenzregister als Best Practice ausgezeichnet.

Bei Test-Installationen, Fragen zur Weiterentwicklung oder zu Subskriptionsmodellen unterstützen Sie gerne:

Hallo Welt! GmbH bzw. GovTech Campus Deutschland e.V.

Kontakt: https://bluespice.com/de/kontakt/

Weitere Informationen:
-	BlueSpice Website [offizielle Website] (https://bluespice.com)
-	Haystack / KI Orchestrierung [offizielle Website] (https://haystack.deepset.ai/)
-	KI Transparenzregister (https://maki.kimarktplatz.bund.de/a/bmi-makimo-app/steckbriefe/BD20249356528?kiosk)
