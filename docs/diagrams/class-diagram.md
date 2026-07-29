# Class Diagram: ChatBot Extension

Core classes in the ChatBot extension showing inheritance, composition, and service relationships.

```mermaid
classDiagram
    class Connector {
        <<DeepsetApi base>>
        +string apiKey
        +string apiUrl
        +string indexUrl
        +string feedbackUrl
        #get(url) array
        #post(url, opts) array
        #stream(url, opts) ResponseInterface
        #patch(url, opts) array
        #delete(url, opts) array
        #executeRequest(url, opts, method) ResponseInterface
    }

    class ChatApi {
        +RoleLookup roleLookup
        +request(query, sessionId, followUpType) void
        +sendSSEHeaders() void
        +getFilter() array
    }

    class SessionApi {
        +create() array
    }

    class IndexApi {
        +indexDocument(data) array
    }

    class FeedbackApi {
        +submit(id, data) array
    }

    class HistoryApi {
        +get(sessionId) array
    }

    class ExternalIndex {
        <<BlueSpiceExtendedSearch base>>
        #doPush(fields, action) Status
    }

    class UpdateIndexTable {
        +SOURCEKEY_WIKIPAGE
        +SOURCEKEY_REPOFILE
        -array supportedFileExtensions
        -array supportedNamespaces
        +doPush(fields, action) Status
        +factory(services, config, doc) UpdateIndexTable
    }

    class IndexDeepset {
        <<RunJobsTrigger IHandler>>
        +BMBF_INDEX_TABLE
        +run(trigger, services) Status
        -processRecord(record) void
        -buildMetadata(page) array
    }

    class SimpleHandler {
        <<MediaWiki Rest>>
    }

    class Chat {
        +ChatApi chatApi
        +execute() array
        +getSupportedRequestTypes() array
        +getParamSettings() array
    }

    class Session {
        +SessionApi sessionApi
        +execute() array
    }

    class ChatFeedback {
        +FeedbackApi feedbackApi
        +execute() array
    }

    Connector <|-- ChatApi
    Connector <|-- SessionApi
    Connector <|-- IndexApi
    Connector <|-- FeedbackApi
    Connector <|-- HistoryApi

    ExternalIndex <|-- UpdateIndexTable

    SimpleHandler <|-- Chat
    SimpleHandler <|-- Session
    SimpleHandler <|-- ChatFeedback

    ChatApi --> Chat : uses
    SessionApi --> Session : uses
    FeedbackApi --> ChatFeedback : uses

    UpdateIndexTable ..> IndexDeepset : "queues records for"
    IndexDeepset ..> IndexApi : "pushes docs via"
```

## Notes

- **`Connector`** is the base HTTP client for all Deepset API communication. It wraps Guzzle with Bearer token auth, 120s timeout, and JSON parsing.
- **`ChatApi`** extends `Connector` with SSE streaming support (`stream()` method) and role-based document filtering (`getFilter()`).
- **`UpdateIndexTable`** extends BlueSpice ExtendedSearch's `ExternalIndex` base class — this is the integration point where ChatBot hooks into the wiki's search indexing pipeline.
- **`IndexDeepset`** implements MWStake's `IHandler` interface for periodic job execution. It consumes records from `bmbf_index_pages` and pushes them to OpenSearch via `IndexApi`.
- **REST handlers** all extend MediaWiki's `SimpleHandler` and receive their API service via dependency injection (ServiceWiring).
