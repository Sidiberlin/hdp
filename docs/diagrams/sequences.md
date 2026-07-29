# Sequence Diagrams

## Workflow: Chat Query (User → RAG Answer)

The primary user-facing workflow: a user asks a question in the chat widget and receives a grounded, source-cited answer streamed in real-time.

```mermaid
sequenceDiagram
    participant Browser as Chat Widget
    participant Apache as Apache :8080
    participant MW as MediaWiki REST<br/>ChatBot\Rest\Chat
    participant ChatApi as ChatBot\DeepsetApi\ChatApi
    participant Hayhooks as hayhooks :1416
    participant OS as OpenSearch
    participant LLM as Azure OpenAI<br/>GPT-4o

    Browser->>Apache: GET /w/rest.php/bmbf/chat<br/>?query=Was ist ein Zuwendungsberechtigter?<br/>&sessionId=abc123&followUpType=rag
    Apache->>MW: FastCGI dispatch
    MW->>ChatApi: request(query, sessionId, "rag")
    
    Note over ChatApi: Set SSE headers<br/>Disable output buffering
    
    ChatApi->>Hayhooks: POST /hdp_pipeline/chat-stream<br/>{query, session, path: "rag", filters}
    
    Note over Hayhooks: Step 1: Query reformulation<br/>chat_summary_llm (GPT-4o)
    Hayhooks->>LLM: Reformulate query with history
    LLM-->>Hayhooks: Reformulated query
    
    Note over Hayhooks: Step 2: Hybrid retrieval
    par BM25 retrieval
        Hayhooks->>OS: Search top 30 (BM25)
        OS-->>Hayhooks: 30 documents
    and Embedding retrieval
        Hayhooks->>Hayhooks: Embed query (mxbai-embed-de)
        Hayhooks->>OS: Search top 40 (vector)
        OS-->>Hayhooks: 40 documents
    end
    
    Note over Hayhooks: Step 3: Cross-encoder ranking
    Hayhooks->>Hayhooks: Rank with bi-encoder_german<br/>→ top 14
    
    Note over Hayhooks: Step 4: Prompt assembly<br/>(rag mode → qa_prompt_builder)
    
    Hayhooks->>LLM: Generate answer (temp=0)<br/>with strict German grounded prompt
    LLM-->>Hayhooks: Answer tokens (streamed)
    
    Hayhooks-->>ChatApi: SSE stream (token by token)
    ChatApi-->>Browser: SSE stream (passthrough)
    Note over Browser: Render answer with [N] citations
```

### Walkthrough

1. **User input** — Browser chat widget sends GET with query, session ID, and answer mode
2. **SSE setup** — `ChatApi::sendSSEHeaders()` sets `Content-Type: text/event-stream`, disables buffering
3. **Pipeline execution** — hayhooks runs the deployed `hdp_pipeline` with the provided parameters
4. **Query reformulation** — GPT-4o rewrites the query incorporating chat history
5. **Hybrid retrieval** — BM25 (top 30) + embedding (top 40) from OpenSearch `hdp_wiki` index in parallel
6. **Cross-encoder ranking** — German bi-encoder model re-ranks to top 14 most relevant documents
7. **Answer generation** — GPT-4o generates grounded answer with `[N]` citation format, streamed via SSE

---

## Workflow: Wiki Content Indexing (Edit → OpenSearch)

When an editor saves a wiki page, the content is asynchronously indexed into OpenSearch for RAG retrieval. This is decoupled via a queue table and a 5-minute background job.

```mermaid
sequenceDiagram
    participant Editor as Wiki Editor
    participant MW as MediaWiki
    participant ES as BlueSpiceExtendedSearch
    participant UpdateIndex as ChatBot<br/>UpdateIndexTable
    participant Queue as bmbf_index_pages<br/>(SQLite table)
    participant JR as Job Runner
    participant IndexDeepset as ChatBot<br/>IndexDeepset
    participant IndexApi as DeepsetApi<br/>IndexApi
    participant OS as OpenSearch

    Editor->>MW: Save page "Zuwendungsberechtigter"
    MW->>ES: Fire UpdateJob
    ES->>UpdateIndex: doPush(mappedFields, UPDATE)
    
    Note over UpdateIndex: Check namespace + file type
    UpdateIndex->>Queue: INSERT (action=update,<br/>page=Zuwendungsberechtigter,<br/>data=serialized_fields)
    
    Note over Queue: ...5 minutes later...
    
    JR->>IndexDeepset: Run trigger (every 5 min)
    IndexDeepset->>Queue: SELECT * WHERE action != null
    Queue-->>IndexDeepset: Queued records
    
    loop For each queued page
        IndexDeepset->>MW: Render wiki page → extract text
        Note over IndexDeepset: Build metadata:<br/>title_level_1..5, sections,<br/>categories, chatbotmeta, display_title
        IndexDeepset->>IndexApi: Push document
        IndexApi->>OS: Index into hdp_wiki<br/>(text + 1024-dim embedding)
        OS-->>IndexApi: Success
    end
    
    IndexDeepset->>Queue: DELETE processed records
```

### Walkthrough

1. **Page save** — Editor saves a page; ExtendedSearch fires an update job
2. **Queue insertion** — ChatBot's `UpdateIndexTable` (registered as ExtendedSearch `ExternalIndex`) intercepts the update and inserts a record into `bmbf_index_pages` with the action type and page data
3. **Background processing** — Every 5 minutes, the `IndexDeepset` run-jobs trigger reads queued records
4. **Content rendering** — For each page, the handler renders the wiki content, extracts section hierarchy (`title_level_1` through `title_level_5`), and builds metadata (categories, sections, chatbotmeta tags, display title)
5. **Indexing** — Documents are pushed to OpenSearch's `hdp_wiki` index with both text and 1024-dimensional embeddings
6. **Cleanup** — Processed records are deleted from the queue table

---

## Workflow: Chat Session Lifecycle

How a chat session is created, used, and optionally exported.

```mermaid
sequenceDiagram
    participant Browser
    participant MW as MediaWiki REST
    participant SessionApi as ChatBot<br/>SessionApi
    participant Hayhooks as hayhooks
    participant Feedback as ChatBot<br/>ChatFeedback

    Browser->>MW: GET /bmbf/session
    MW->>SessionApi: create session
    SessionApi->>Hayhooks: POST /hdp_pipeline/search-session
    Hayhooks-->>SessionApi: session_id
    SessionApi-->>Browser: { sessionId }

    Note over Browser: User asks multiple questions<br/>using the same sessionId
    
    loop Each question
        Browser->>MW: GET /bmbf/chat?sessionId=...&query=...
        MW-->>Browser: SSE answer stream
    end

    opt User gives feedback
        Browser->>MW: POST /bmbf/feedback/{id}<br/>{rating, problem, cause}
        MW->>Feedback: submit feedback
        Feedback->>Hayhooks: POST feedback to pipeline
    end

    opt User exports chat
        Browser->>MW: POST /bmbf-export-chat<br/>{messages}
        MW-->>Browser: PDF download
    end
```
