# OpenSearch with the ingest-attachment plugin
#
# BlueSpiceExtendedSearch registers four search sources: wikipage,
# specialpage, repofile and externalfile. The two file sources call
# ingest.putPipeline() with an `attachment` processor during
# initBackends.php — unconditionally, with no capability check.
#
# Stock opensearchproject/opensearch does not ship that processor, so
# without this plugin initBackends aborts partway through with
#
#     No processor type exists with name [attachment]
#
# leaving bluespice_externalfile uncreated. Every subsequent search then
# fails with a 404 (index_not_found_exception) because the query spans
# all four indices at once — which takes down the whole Search Center UI,
# not just file search.
#
# Keep the base image tag in sync with the plugin: opensearch-plugin
# refuses to install a version that does not match the running node.

FROM opensearchproject/opensearch:2.18.0

# --batch accepts the plugin's additional-permissions prompt
# (reflection + AWT colour management, required by the bundled Tika).
RUN ./bin/opensearch-plugin install --batch ingest-attachment
