# SPDX-License-Identifier: GPL-3.0-only
# Copyright (C) 2026 Sidiberlin
from haystack import Pipeline
from haystack.components.builders.prompt_builder import PromptBuilder
from haystack.components.converters.output_adapter import OutputAdapter
from haystack_integrations.document_stores.opensearch.document_store import (
    OpenSearchDocumentStore,
)
from haystack_integrations.components.retrievers.opensearch.bm25_retriever import (
    OpenSearchBM25Retriever,
)
from haystack.components.embedders.sentence_transformers_text_embedder import (
    SentenceTransformersTextEmbedder,
)
from haystack_integrations.components.retrievers.opensearch.embedding_retriever import (
    OpenSearchEmbeddingRetriever,
)
from haystack.components.joiners.document_joiner import DocumentJoiner
from haystack.components.rankers.sentence_transformers_similarity import (
    SentenceTransformersSimilarityRanker,
)
from haystack.components.generators.azure import AzureOpenAIGenerator
from haystack.components.routers.conditional_router import ConditionalRouter
from haystack.components.joiners.string_joiner import StringJoiner
from haystack.components.joiners.answer_joiner import AnswerJoiner
from haystack.components.builders.answer_builder import AnswerBuilder
import logging

logging.basicConfig(
    format="%(levelname)s - %(name)s -  %(message)s", level=logging.WARNING
)
logging.getLogger("haystack").setLevel(logging.INFO)

followup_elaborate = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte detailliert, abwägend und ausführlich.
Nehme auch Zusatzinformationen und Kontext aus den Dokumenten in deine Antwort auf.
Wenn die Antwort in mehreren Dokumenten enthalten ist, fasse diese zusammen.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
followup_bulletpoints = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte detailliert, abwägend, ausführlich und immmer in Bulletpoints.
Wenn die Antwort in mehreren Dokumenten enthalten ist, fasse diese zusammen.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
followup_short = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte kurz und präzise.
Wenn die Antwort in mehreren Dokumenten enthalten ist, fasse diese zusammen.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
followup_onlytext = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte detailliert, abwägend und ausführlich.
Wenn die Antwort in mehreren Dokumenten enthalten ist, fasse diese zusammen.
Verwende nie Bulletpoints, sondern antworte in einem durchgängigen, strukturiertem Text.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
followup_citations = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte detailliert, abwägend und ausführlich.
Deine Antwort besteht ausschließlich aus Zitaten aus den Dokumenten.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
chat_summary_prompt_builder = PromptBuilder(
    template="""NUR wenn die Chathistorie angegeben ist, formuliere die folgende Frage (Current Question) so um, dass sie gut für die Websearch geeignet ist.
Wenn die Chathistorie leer ist, formuliere die Frage NICHT neu. 
Füge nur falls notwendig Kontext zur Current Question hinzu.
Wenn du keine Änderungen vornehmen möchtest, gib einfach die Current Question aus.
Chathistorie: {{ question }}
Umformulierte Frage:""",
    required_variables="*",
)
replies_to_query = OutputAdapter(template="{{ replies[0] }}", output_type=str)
opensearchdocumentstore = OpenSearchDocumentStore(
    hosts="HOSTNAME:PORT",
    use_ssl=True,
    verify_certs=False,
    http_auth=("admin", "admin"),
    index="INDEXNAME",
    embedding_dim=1024,
    similarity="cosine",
)
bm25_retriever = OpenSearchBM25Retriever(
    top_k=30, document_store=opensearchdocumentstore
)
query_embedder = SentenceTransformersTextEmbedder(
    model="mixedbread-ai/deepset-mxbai-embed-de-large-v1", prefix='"query: "'
)
embedding_retriever = OpenSearchEmbeddingRetriever(
    top_k=40, efficient_filtering=True, document_store=opensearchdocumentstore
)
document_joiner = DocumentJoiner(join_mode="concatenate")
ranker = SentenceTransformersSimilarityRanker(
    model="PM-AI/bi-encoder_msmarco_bert-base_german",
    top_k=14,
    meta_fields_to_embed=[
        "document.meta.chatbotmeta",
        "document.meta.display_title",
        "document.meta.sections",
    ],
)
qa_prompt_builder = PromptBuilder(
    template="""Du bist ein technischer Experte.
Du beantwortest die Fragen wahrheitsgemäß auf Grundlage der vorgelegten Dokumente.
Antworte detailliert, abwägend und ausführlich.
Wenn die Antwort in mehreren Dokumenten enthalten ist, fasse diese zusammen.
Verwende ausschließlich Verweise in der Form [NUMMER DES DOKUMENTS], wenn du Informationen aus einem Dokument verwendest, z. B. [3] für Dokument[3].
Nenne niemals die Dokumente, sondern gib immer nur eine Zahl in eckigen Klammern als Referenz an.
Der Verweis darf sich nur auf die Nummer beziehen, die in eckigen Klammern hinter der Passage steht.
Ignoriere Dokumente, die keine Antwort auf die Frage enthalten.
Antworte nur auf der Grundlage der vorgelegten Dokumente. Erfinde keine Fakten.
Wenn in dem Dokument keine Informationen zu der Frage gefunden werden können, gib dies an.
Hier sind die Dokumente:

{%- for document in documents -%}
Dokument[{{ loop.index }}]:
{{ document.meta.title_level_1 }}
{{ document.meta.title_level_2 }}
{{ document.meta.title_level_3 }}
{{ document.meta.title_level_4 }}
{{ document.meta.title_level_5 }}

{{ document.content }}
{%- endfor -%}

Frage: {{ question }}
Antwort:""",
    required_variables=["documents", "question"],
)
chat_summary_llm = AzureOpenAIGenerator(
    azure_endpoint="ENDPOINT",
    api_version="2023-05-15",
    azure_deployment="gpt-4o",
    generation_kwargs={"temperature": 0},
)
conditionalrouter = ConditionalRouter(
    routes=[
        {
            "condition": '{{path == "rag"}}',
            "output": "{{question}}",
            "output_name": "normal",
            "output_type": str,
        },
        {
            "condition": '{{path == "followup_short"}}',
            "output": "{{question}}",
            "output_name": "followup_short",
            "output_type": str,
        },
        {
            "condition": '{{path == "followup_elaborate"}}',
            "output": "{{question}}",
            "output_name": "followup_elaborate",
            "output_type": str,
        },
        {
            "condition": '{{path == "followup_bulletpoints"}}',
            "output": "{{question}}",
            "output_name": "followup_bulletpoints",
            "output_type": str,
        },
        {
            "condition": '{{path == "followup_onlytext"}}',
            "output": "{{question}}",
            "output_name": "followup_onlytext",
            "output_type": str,
        },
        {
            "condition": '{{path == "followup_citations"}}',
            "output": "{{question}}",
            "output_name": "followup_citations",
            "output_type": str,
        },
    ]
)
stringjoiner = StringJoiner()
outputadapter = OutputAdapter(template="{{strings[0]}}", output_type=str)
answerllm = AzureOpenAIGenerator(
    azure_endpoint="ENDPOINT",
    api_version="2023-05-15",
    azure_deployment="gpt-4o",
    generation_kwargs={"temperature": 0},
)
answer_builder = AnswerBuilder()
answer_builder_chatsummary = AnswerBuilder()
answer_joiner = AnswerJoiner()

pipeline = Pipeline()
pipeline.add_component("followup_elaborate", followup_elaborate)
pipeline.add_component("followup_bulletpoints", followup_bulletpoints)
pipeline.add_component("followup_short", followup_short)
pipeline.add_component("followup_onlytext", followup_onlytext)
pipeline.add_component("followup_citations", followup_citations)
pipeline.add_component("chat_summary_prompt_builder", chat_summary_prompt_builder)
pipeline.add_component("replies_to_query", replies_to_query)
pipeline.add_component("bm25_retriever", bm25_retriever)
pipeline.add_component("query_embedder", query_embedder)
pipeline.add_component("embedding_retriever", embedding_retriever)
pipeline.add_component("document_joiner", document_joiner)
pipeline.add_component("ranker", ranker)
pipeline.add_component("qa_prompt_builder", qa_prompt_builder)
pipeline.add_component("chat_summary_llm", chat_summary_llm)
pipeline.add_component("conditionalrouter", conditionalrouter)
pipeline.add_component("stringjoiner", stringjoiner)
pipeline.add_component("outputadapter", outputadapter)
pipeline.add_component("answerllm", answerllm)
pipeline.add_component("answer_builder", answer_builder)
pipeline.add_component("answer_builder_chatsummary", answer_builder_chatsummary)
pipeline.add_component("answer_joiner", answer_joiner)
pipeline.connect("replies_to_query.output", "bm25_retriever.query")
pipeline.connect("replies_to_query.output", "query_embedder.text")
pipeline.connect("replies_to_query.output", "ranker.query")
pipeline.connect("ranker.documents", "qa_prompt_builder.documents")
pipeline.connect("ranker.documents", "followup_elaborate.documents")
pipeline.connect("ranker.documents", "followup_bulletpoints.documents")
pipeline.connect("ranker.documents", "followup_onlytext.documents")
pipeline.connect("ranker.documents", "followup_citations.documents")
pipeline.connect("ranker.documents", "followup_short.documents")
pipeline.connect("bm25_retriever.documents", "document_joiner.documents")
pipeline.connect("query_embedder.embedding", "embedding_retriever.query_embedding")
pipeline.connect("embedding_retriever.documents", "document_joiner.documents")
pipeline.connect("document_joiner.documents", "ranker.documents")
pipeline.connect("chat_summary_prompt_builder.prompt", "chat_summary_llm.prompt")
pipeline.connect("chat_summary_llm.replies", "replies_to_query.replies")
pipeline.connect("replies_to_query.output", "conditionalrouter.question")
pipeline.connect("conditionalrouter.normal", "qa_prompt_builder.question")
pipeline.connect(
    "conditionalrouter.followup_bulletpoints", "followup_bulletpoints.question"
)
pipeline.connect("conditionalrouter.followup_elaborate", "followup_elaborate.question")
pipeline.connect("conditionalrouter.followup_short", "followup_short.question")
pipeline.connect("conditionalrouter.followup_onlytext", "followup_onlytext.question")
pipeline.connect("conditionalrouter.followup_citations", "followup_citations.question")
pipeline.connect("followup_bulletpoints.prompt", "stringjoiner.strings")
pipeline.connect("followup_elaborate.prompt", "stringjoiner.strings")
pipeline.connect("followup_short.prompt", "stringjoiner.strings")
pipeline.connect("qa_prompt_builder.prompt", "stringjoiner.strings")
pipeline.connect("followup_onlytext.prompt", "stringjoiner.strings")
pipeline.connect("followup_citations.prompt", "stringjoiner.strings")
pipeline.connect("stringjoiner.strings", "outputadapter.strings")
pipeline.connect("outputadapter.output", "answerllm.prompt")
pipeline.connect("ranker.documents", "answer_builder.documents")
pipeline.connect("answerllm.replies", "answer_builder.replies")
pipeline.connect("replies_to_query.output", "answer_builder.query")
pipeline.connect("answer_builder.answers", "answer_joiner.answers")
pipeline.connect("chat_summary_llm.replies", "answer_builder_chatsummary.replies")
pipeline.connect("answer_builder_chatsummary.answers", "answer_joiner.answers")

# Documentation:
# To run the pipeline, use the pipeline.run() method with the appropriate data.
# Here is an example on how to execute the pipeline:
# The pipeline is defined and loaded above. To execute it, use:
query = "Das ist eine Testfrage?"
result = pipeline.run(
    data={
        "chat_summary_prompt_builder": {"question": query},
        "answer_builder_chatsummary": {"query": query},
        "conditionalrouter": {"path": "rag"},
    }
)

print(result)
