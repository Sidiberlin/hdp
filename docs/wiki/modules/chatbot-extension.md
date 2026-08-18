# Module: ChatBot Extension

[`app/extensions/ChatBot/`](../../../app/extensions/ChatBot) is the
MediaWiki extension that provides the chat widget, its REST API surface,
and (unused in this deployment — see below) a built-in wiki-content
indexing pipeline. It was originally built against **Deepset Cloud**'s
hosted RAG API; in this deployment it talks to the self-hosted Haystack
pipeline through [`chatbot-proxy`](docker-services.md#chatbot-proxy)
instead, with no PHP code changes — only configuration
([`100-ChatBot.php`](../../../app/settings.d/100-ChatBot.php)).

## Responsibilities

- **Chat UI** — a TypeScript/webpack-bundled widget
  (`resources/ts/`, built to `resources/js/dist/bmbf.chat.bundle.js`) with
  SSE streaming, six answer modes, session persistence, feedback, and
  export.
- **REST API** — MediaWiki REST endpoints under `/bmbf/*` for chat,
  session creation, history, and feedback.
- **Deepset API connector** — a Guzzle-based HTTP client
  (`src/DeepsetApi/`) that talks to whatever URLs `$wgBmbfDeepsetApi*` point
  at — in this deployment, `chatbot-proxy`.
- **Built-in content indexing** (present in code, **inert** here) — hooks
  into BlueSpiceExtendedSearch to queue wiki content, and a background job
  pushes it to a Deepset-shaped index API. See
  [Notable Patterns / Gotchas](#notable-patterns--gotchas).
- **Feedback & admin** — collects per-answer user feedback, exposes an
  admin stats dashboard (`src/AdminModule/Stats.php`).
- **PDF/ODF export** — exports chat transcripts via the `PDFCreator`
  extension.

## Key Files

- [`extension.json`](../../../app/extensions/ChatBot/extension.json) — manifest: REST routes, hooks, config schema, the `Ministerium`/`Projektträger` namespaces (5000/5002), resource modules
- [`includes/ServiceWiring.php`](../../../app/extensions/ChatBot/includes/ServiceWiring.php) — DI container definitions for every `Deepset*Api` service
- [`src/DeepsetApi/Connector.php`](../../../app/extensions/ChatBot/src/DeepsetApi/Connector.php) — base HTTP client (GET/POST/PATCH/DELETE/stream via Guzzle), reads `BmbfDeepsetApi*` config
- [`src/DeepsetApi/ChatApi.php`](../../../app/extensions/ChatBot/src/DeepsetApi/ChatApi.php) — `request()` streams `POST {apiUrl}/chat-stream` and echoes the raw SSE body straight through to the browser
- [`src/DeepsetApi/SessionApi.php`](../../../app/extensions/ChatBot/src/DeepsetApi/SessionApi.php) — `GET {apiUrl}` for `pipeline_id`, then `POST {sessionApiUrl}` for `search_session_id`
- [`src/Rest/Chat.php`](../../../app/extensions/ChatBot/src/Rest/Chat.php) — `GET /bmbf/chat` handler (params: `query`, `sessionId`, `followUpType`)
- [`src/Rest/Session.php`](../../../app/extensions/ChatBot/src/Rest/Session.php) — `GET /bmbf/session` handler
- [`src/Rest/History.php`](../../../app/extensions/ChatBot/src/Rest/History.php), [`src/Rest/ChatFeedback.php`](../../../app/extensions/ChatBot/src/Rest/ChatFeedback.php) — history and feedback handlers
- [`src/ExternalIndex/UpdateIndexTable.php`](../../../app/extensions/ChatBot/src/ExternalIndex/UpdateIndexTable.php) — ExtendedSearch hook; queues page changes into `bmbf_index_pages`
- [`src/RunJobsTriggerHandler/IndexDeepset.php`](../../../app/extensions/ChatBot/src/RunJobsTriggerHandler/IndexDeepset.php) — every-5-minute job draining that queue and pushing to `IndexApi`
- [`resources/ts/api/DeepsetApi.ts`](../../../app/extensions/ChatBot/resources/ts/api/DeepsetApi.ts) — frontend client calling the `/bmbf/*` REST routes

## Public API / Interfaces

### REST endpoints (from `extension.json`)

| Method | Path | Handler | Notes |
|---|---|---|---|
| GET | `/bmbf/chat` | `ChatBot\Rest\Chat` | `text/event-stream` response; requires read access — anonymous requests get HTTP 403 `rest-read-denied` before the proxy/LLM pipeline is reached |
| GET | `/bmbf/session` | `ChatBot\Rest\Session` | Creates a session via `chatbot-proxy`'s `/session` |
| POST | `/bmbf/history` | `ChatBot\Rest\History` | |
| POST | `/bmbf/feedback/{id}` | `ChatBot\Rest\ChatFeedback` | |
| POST | `/bmbf-feedback-mail/{id}` | `ChatBot\Rest\SendFeedbackMail` | |
| POST | `/bmbf-export-chat` | `ChatBot\Rest\CreateChatPdf` | |
| POST | `/bmbf-odf-export-chat` | `ChatBot\Rest\CreateChatOdf` | |

### Configuration (`$wg`-prefixed MediaWiki globals)

MediaWiki's default `GlobalVarConfig` reads `$wg`-prefixed globals because
`extension.json` declares no `config_prefix` override — see the gotcha in
[settings-d](settings-d.md#notable-patterns--gotchas). Set in
[`100-ChatBot.php`](../../../app/settings.d/100-ChatBot.php):

| Global | Value in this deployment | Read by |
|---|---|---|
| `$wgBmbfDeepsetApiChatUrl` | `http://chatbot-proxy:8080` | `ChatApi`, `SessionApi` |
| `$wgBmbfDeepsetApiSearchSessionsUrl` | `http://chatbot-proxy:8080/session` | `SessionApi` |
| `$wgBmbfDeepsetApiKey` | `not-needed` (proxy ignores auth) | `Connector::executeRequest()` (sent as `Bearer` header) |
| `$wgBmbfDeepsetApiIndexUrl` | `''` (empty — see gotchas below) | `IndexApi` |
| `$wgBmbfDeepsetApiFeedbackUrl`, `$wgBmbfDeepsetApiTagUrl`, `$wgBmbfDeepsetPipelineStatsUrl` | `''` | `FeedbackApi`, admin stats |

## Internal Structure

```
ChatBot/
├── extension.json              # routes, hooks, config schema, namespaces
├── includes/ServiceWiring.php  # DI for Deepset*Api services
├── src/
│   ├── DeepsetApi/              # HTTP clients (Connector base + 6 subclasses)
│   ├── Rest/                    # MediaWiki REST handlers (thin, delegate to DeepsetApi/*)
│   ├── ExternalIndex/           # BlueSpiceExtendedSearch hook (queues bmbf_index_pages)
│   ├── RunJobsTriggerHandler/   # IndexDeepset background job (every 5 min)
│   ├── Hook/, HookHandler/      # permissions, tags, UI slot registration
│   ├── Model/                   # ChatMessage model + factory
│   ├── PdfExport/                # PDF/ODF export integration
│   └── Util/RoleLookup.php      # namespace/role-based filtering for RAG queries
├── resources/
│   ├── ts/                      # TypeScript source for the chat widget
│   └── js/dist/                 # webpack bundle actually shipped to the browser
├── i18n/                        # de, en, qqq message files
└── db/bmbf_index_pages.sql      # index queue table schema
```

## Dependencies

- **Uses:** `BlueSpiceFoundation` (config registry, services),
  `BlueSpiceExtendedSearch` (`ExternalIndexRegistry` attribute for
  `UpdateIndexTable`), `PDFCreator` (chat export), MWStake
  `RunJobsTrigger` component (`IndexDeepset` scheduling), `ContentDroplets`
  (the `chatbotmeta` page-metadata droplet), GuzzleHttp (HTTP client).
- **Used by:** nothing else in the wiki — top-level extension consumed
  directly by the browser chat widget.
- **Requires (at request time):** `chatbot-proxy` reachable at
  `$wgBmbfDeepsetApiChatUrl` — if unreachable, `ChatApi::request()` throws
  and `Chat::execute()` returns `{"errors": [...]}`.

## Notable Patterns / Gotchas

- **SSE passthrough, not re-encoding.** `ChatApi::request()` disables PHP
  output buffering (`sendSSEHeaders()`), opens a streaming Guzzle request to
  `{apiUrl}/chat-stream`, and echoes each line of the upstream SSE response
  straight to the browser via `ob_flush(); flush();` — it does not parse or
  re-shape the event stream. This means `chatbot-proxy`'s SSE event format
  (`{"type": "delta", ...}` / `{"type": "result", ...}`) is what the
  frontend actually consumes; see
  [haystack-pipeline](haystack-pipeline.md) and
  [diagrams/sequences.md](../diagrams/sequences.md).
- **`ob_flush()` without a buffer can warn.** Under PHP-FPM there isn't
  always an active output buffer when `ob_flush()` runs
  (`ChatApi.php` line 80), producing harmless log noise — a known,
  documented, non-blocking issue (see `docs/QA-REPORT.md`).
- **"Deepset" naming is historical.** The extension still calls its config
  keys and classes `BmbfDeepsetApi*` / `DeepsetApi\*`, but nothing here
  talks to Deepset Cloud in this deployment — every URL points at
  `chatbot-proxy`, which itself is a hand-rolled Python service, not
  Haystack's own hayhooks REST surface directly (see
  [haystack-pipeline](haystack-pipeline.md) for why).
- **The extension's built-in indexing pipeline is inert here.**
  `UpdateIndexTable` (an `ExternalIndexRegistry` hook) still queues page
  changes into the `bmbf_index_pages` table on every edit, and
  `IndexDeepset::run()` still fires every 5 minutes via MWStake's
  `RunJobsTrigger`/`EveryFiveMinutes` interval, draining that queue and
  calling `IndexApi::pushPage()`/`batchDelete()` against
  `$wgBmbfDeepsetApiIndexUrl`. Since that URL is configured empty in
  [`100-ChatBot.php`](../../../app/settings.d/100-ChatBot.php), those calls
  have nowhere to go. **The actual index population path in this
  deployment is the separate [`ingest_hdp_wiki.py`](ingestion.md) script**,
  run manually against OpenSearch directly — see
  [architecture.md](../architecture.md#key-design-decisions).
- **Role-based RAG filtering is built but not wired through.**
  `ChatApi::getFilter()` builds an OpenSearch metadata filter excluding the
  `Ministerium` (`NS_BMBF`, 5000) and/or `Projektträger` (`NS_PT`, 5002)
  namespaces from retrieval results for users who aren't in the
  corresponding BlueSpice role (`RoleLookup::isBMBF()` /
  `isProjectSponsor()`), unless the user is a sysop or maintainer, and
  sends it as a `filters` field in the request body to `chatbot-proxy`. But
  `chatbot-proxy`'s `_handle_chat_stream()`/`call_hayhooks()` (see
  [haystack-pipeline](haystack-pipeline.md)) only reads `body["query"]` from
  that request — it does not read or forward `filters`, so this
  access-control filter is currently **not enforced end-to-end** in this
  deployment.
- **`followUpType` (the six answer modes) is likewise not forwarded.**
  `ChatApi::request()` sends `params.ConditionalRouter.path` set to the
  user-selected mode — `rag`, `followup_short`, `followup_elaborate`,
  `followup_bulletpoints`, `followup_onlytext`, or `followup_citations` (see
  its docblock) — but `chatbot-proxy`'s `call_hayhooks()` hardcodes
  `"path": "rag"` in the body it sends to the pipeline regardless of what
  the extension sent. In this deployment, every chat request is answered in
  the default `rag` style even if the UI's follow-up mode selector says
  otherwise.
