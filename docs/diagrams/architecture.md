# Architecture Diagrams

## System Architecture (Service Topology)

High-level view of all services, their ports, and data flow directions.

```mermaid
flowchart TD
    subgraph Browser["User Browser"]
        ChatUI["Chat Widget<br/>(Vue.js)"]
        WikiUI["Wiki Interface<br/>(BlueSpice Discovery)"]
    end

    subgraph Docker["Docker Compose Stack (network: hdp)"]
        Apache["mediawiki-web<br/>Apache 2.4 :8080"]
        MW["mediawiki<br/>PHP-FPM 8.3<br/>MediaWiki 1.43 + BlueSpice 4.5"]
        JR["mediawiki-jobrunner<br/>PHP 8.3"]
        OS["opensearch<br/>OpenSearch 2.18 :9200"]
        HS["haystack<br/>hayhooks :1416"]
    end

    subgraph Data["Storage"]
        SQLite[("SQLite<br/>app/cache/sqlite")]
        IndexTable[("bmbf_index_pages")]
        OSIndex[("hdp_wiki index<br/>1024-dim cosine")]
    end

    subgraph External["External Services"]
        Azure["Azure OpenAI<br/>GPT-4o"]
        HF["HuggingFace Hub<br/>(model weights)"]
    end

    ChatUI -->|"SSE /w/rest.php/bmbf/chat"| Apache
    WikiUI -->|"HTTP :8080"| Apache
    Apache -->|"FastCGI :9000"| MW
    
    MW --> SQLite
    MW --> IndexTable
    MW -.->|"chat proxy"| HS
    JR -->|"index job (5 min)"| MW
    JR --> IndexTable
    
    MW -->|"wiki search"| OS
    JR -->|"index documents"| OS
    HS -->|"BM25 + vector retrieval"| OS
    
    MW --> OSIndex
    HS --> OSIndex
    
    HS -->|"query reform + answer gen"| Azure
    HS -.->|"download models"| HF
```

## Extension Loading Order

How `settings.d/` files map to feature tiers.

```mermaid
flowchart LR
    subgraph Core["MediaWiki Core 1.43"]
        MWCore["includes/ + vendor/"]
    end

    subgraph Settings["settings.d/ (loaded in order)"]
        S1["010: Logging + MWStake"]
        S2["020: DefaultSettings<br/>+ MW Distribution"]
        S3["030: BlueSpiceFree<br/>(~25 extensions)"]
        S4["040: BlueSpicePro<br/>(~70 extensions)"]
        S5["050: Farm + Fixes"]
        S6["080: Discovery Skin"]
        S7["090: GovTech<br/>→ ChatBot"]
    end

    subgraph Tiers["Feature Tiers"]
        Free["BlueSpice Free"]
        Pro["BlueSpice Pro"]
        HDP["HDP Edition"]
    end

    MWCore --> S1 --> S2 --> S3 --> S4 --> S5 --> S6 --> S7
    S3 --> Free
    S4 --> Pro
    S7 --> HDP
```

## RAG Pipeline Internal Flow

The Haystack pipeline's component graph.

```mermaid
flowchart TD
    Input([Input: query + session + path]) --> CSPB["chat_summary_prompt_builder"]
    CSPB --> CSLLM["chat_summary_llm<br/>AzureOpenAIGenerator"]
    CSLLM --> RTQ["replies_to_query<br/>OutputAdapter"]
    RTQ --> BM25["bm25_retriever<br/>top_k=30"]
    RTQ --> QE["query_embedder<br/>mxbai-embed-de<br/>1024-dim"]
    QE --> ER["embedding_retriever<br/>top_k=40"]
    BM25 --> DJ["document_joiner<br/>concatenate"]
    ER --> DJ
    DJ --> RK["ranker<br/>msmarco_bert-base_german<br/>top_k=14"]
    RK --> CR{"conditional_router<br/>path selection"}
    
    CR -->|"rag"| QAP["qa_prompt_builder<br/>(detailed)"]
    CR -->|"followup_short"| FUS["followup_short<br/>(concise)"]
    CR -->|"followup_elaborate"| FUE["followup_elaborate<br/>(more detail)"]
    CR -->|"followup_bulletpoints"| FUB["followup_bulletpoints"]
    CR -->|"followup_onlytext"| FUO["followup_onlytext"]
    CR -->|"followup_citations"| FUC["followup_citations<br/>(quotes only)"]
    
    QAP --> SJ["string_joiner"]
    FUS --> SJ
    FUE --> SJ
    FUB --> SJ
    FUO --> SJ
    FUC --> SJ
    
    SJ --> OA["output_adapter"]
    OA --> ALLM["answerllm<br/>AzureOpenAIGenerator<br/>temp=0"]
    ALLM --> AB["answer_builder"]
    RK --> AB
    AB --> AJ["answer_joiner"]
    AJ --> Output([Output: answers + sources])
```
