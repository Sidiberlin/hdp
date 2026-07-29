# Module: BlueSpice ExtendedSearch

BlueSpice ExtendedSearch (`app/extensions/BlueSpiceExtendedSearch/`) is the search backend that powers the wiki's search UI and provides the `ExternalIndex` extension point that ChatBot uses to push content to OpenSearch for RAG.

## Responsibilities

- Full-text search over wiki pages, files, and external sources
- OpenSearch/Elasticsearch integration with configurable backends
- `ExternalIndex` hook system — allows other extensions to push documents to external search indices
- Search result post-processing and relevance tuning
- Maintenance scripts for index building and updates

## Key Files

- [`extension.json`](../../app/extensions/BlueSpiceExtendedSearch/extension.json) — Extension manifest
- [`src/Backend.php`](../../app/extensions/BlueSpiceExtendedSearch/src/Backend.php) — Search backend abstraction
- [`src/ExternalIndex.php`](../../app/extensions/BlueSpiceExtendedSearch/src/ExternalIndex.php) — Base class for external index integrations (extended by ChatBot)
- [`src/IExternalIndex.php`](../../app/extensions/BlueSpiceExtendedSearch/src/IExternalIndex.php) — External index interface
- [`src/SourceFactory.php`](../../app/extensions/BlueSpiceExtendedSearch/src/SourceFactory.php) — Creates search sources (wiki pages, files, etc.)
- [`src/Lookup.php`](../../app/extensions/BlueSpiceExtendedSearch/src/Lookup.php) — Search query builder
- [`maintenance/rebuildIndex.php`](../../app/extensions/BlueSpiceExtendedSearch/maintenance/rebuildIndex.php) — Full index rebuild
- [`maintenance/updateWikiPageIndex.php`](../../app/extensions/BlueSpiceExtendedSearch/maintenance/updateWikiPageIndex.php) — Incremental index update

## ExternalIndex Integration (ChatBot hook)

ChatBot registers itself as an ExternalIndex provider via `extension.json`:

```json
"BlueSpiceExtendedSearch": {
    "ExternalIndexRegistry": {
        "bmbf-update-index-table": "ChatBot\\ExternalIndex\\UpdateIndexTable::factory"
    }
}
```

When ExtendedSearch processes a document update (page edit, file upload), it calls all registered ExternalIndex providers. ChatBot's `UpdateIndexTable` receives the mapped document fields and pushes them to the `bmbf_index_pages` queue table for later processing by `IndexDeepset`.

## Dependencies

- **Uses:** OpenSearch (as search backend), BlueSpiceFoundation (services, config)
- **Used by:** ChatBot (ExternalIndex hook), BlueSpiceInterwikiSearch, wiki search UI
- **Config:** Enabled in `030-BlueSpiceFree.php`; `$GLOBALS['wgHiddenPrefs'][] = 'searchlimit'` hides the MediaWiki search limit preference

## Notable Patterns / Gotchas

- **Dual role** — ExtendedSearch manages its own OpenSearch indices for the wiki search UI. The ChatBot's `hdp_wiki` index is separate, managed by the Haystack pipeline. Both use the same OpenSearch instance but different indices.
- **Search limit** — The preference `searchlimit` is explicitly hidden because ExtendedSearch manages result counts itself.
