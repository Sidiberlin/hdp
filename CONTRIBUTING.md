1|1|# Contributing to HDP / Beiträge leisten
2|2|
3|3|> **EN:** Contributions are welcome! This project is funded by BMBF and developed as an open-source BlueSpice MediaWiki distribution with a Haystack RAG chatbot.
4|4|>
5|5|> **DE:** Beiträge sind willkommen! Dieses Projekt wird vom BMBF gefördert und als Open-Source-BlueSpice-MediaWiki-Distribution mit Haystack-RAG-Chatbot entwickelt.
6|6|
7|7|---
8|8|
9|9|## Development Setup / Entwicklungsumgebung
10|10|
11|11|**EN:** See [`README-DOCKER.md`](README-DOCKER.md) for complete Docker setup instructions. Quick start:
12|12|
13|13|**DE:** Siehe [`README-DOCKER.md`](README-DOCKER.md) für vollständige Docker-Einrichtungsanleitung. Kurzanleitung:
14|14|
15|15|```bash
16|16|cp .env.example .env
17|17|# Edit .env — set passwords and HDP_LLM_API_KEY
18|18|docker compose up -d --build
19|19|docker compose exec mediawiki bash /setup.sh
20|20|```
21|21|
22|22|**Requirements / Voraussetzungen:**
23|23|- Docker 24+ and Docker Compose v2
24|24|- 8 GB RAM minimum (embedding model + OpenSearch)
25|25|- 10 GB free disk space (Docker images + embeddings)
26|26|
27|27|---
28|28|
29|29|## How to Contribute / Wie beitragen
30|30|
31|31|### 1. Report Issues / Probleme melden
32|32|
33|33|**EN:** Use the [GitHub issue tracker](https://github.com/Sieddi/hdp/issues). Search existing issues before creating a new one. Include:
34|34|- BlueSpice HDP version (see `publiccode.yml` → `softwareVersion`)
35|35|- Docker and Docker Compose versions
36|36|- Steps to reproduce
37|37|- Expected vs. actual behavior
38|38|- Relevant log output (`docker compose logs <service>`)
39|39|
40|40|**DE:** Verwenden Sie den [GitHub-Issue-Tracker](https://github.com/Sieddi/hdp/issues). Suchen Sie zunächst in bestehenden Issues. Geben Sie an:
41|41|- BlueSpice-HDP-Version (siehe `publiccode.yml` → `softwareVersion`)
42|42|- Docker- und Docker-Compose-Versionen
43|43|- Schritte zur Reproduktion
44|44|- Erwartetes vs. tatsächliches Verhalten
45|45|- Relevante Protokollausgaben (`docker compose logs <service>`)
46|46|
47|47|### 2. Submit Merge Requests / Merge Requests einreichen
48|48|
49|49|**EN:**
50|50|1. Fork the repository on GitHub
51|51|2. Create a feature branch: `git checkout -b feature/my-feature`
52|52|3. Make your changes — keep commits focused
53|53|4. Test against a running stack: `docker compose up -d --build && docker compose exec mediawiki bash /setup.sh`
54|54|5. Verify the wiki loads at `http://localhost:8080/w/`
55|55|6. Submit a merge request with a clear description
56|56|
57|57|**DE:**
58|58|1. Forken Sie das Repository auf GitHub
59|59|2. Erstellen Sie einen Feature-Branch: `git checkout -b feature/mein-feature`
60|60|3. Nehmen Sie Ihre Änderungen vor — halten Sie Commits fokussiert
61|61|4. Testen Sie gegen einen laufenden Stack: `docker compose up -d --build && docker compose exec mediawiki bash /setup.sh`
62|62|5. Prüfen Sie, ob das Wiki unter `http://localhost:8080/w/` lädt
63|63|6. Reichen Sie einen Merge Request mit klarer Beschreibung ein
64|64|
65|65|### 3. Code Style / Code-Stil
66|66|
67|67|**EN:**
68|68|- PHP: Follow [MediaWiki coding conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP) (`phan` and `phpcs` configs exist in the repo)
69|69|- Shell scripts: Use `set -euo pipefail` in all scripts
70|70|- Docker: Pin all image versions explicitly (no `latest` tags)
71|71|- Secrets: Never hardcode — use `.env` references (`${HDP_*}`)
72|72|
73|73|**DE:**
74|74|- PHP: Befolgen Sie die [MediaWiki-Coding-Conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP) (`phan`- und `phpcs`-Konfigurationen vorhanden)
75|75|- Shell-Skripte: Verwenden Sie `set -euo pipefail` in allen Skripten
76|76|- Docker: Alle Image-Versionen explizit pinnen (keine `latest`-Tags)
77|77|- Secrets: Niemals fest codieren — `.env`-Referenzen verwenden (`${HDP_*}`)
78|78|
79|79|---
80|80|
81|81|## Project Structure / Projektstruktur
82|82|
83|83|```
84|84|docker-compose.yml          # 7-service Docker stack
85|85|docker/                     # Container build contexts + setup scripts
86|86|  setup.sh                  # First-boot MediaWiki installer
87|87|  haystack/                 # Haystack RAG pipeline + ingestion
88|88|  mediawiki/                # Wiki templates, FAQ, Help pages
89|89|app/                        # MediaWiki application root
90|90|  extensions/               # ~130 BlueSpice + standard extensions (vendored)
91|91|  skins/                    # BlueSpiceDiscovery + standard skins (vendored)
92|92|  settings.d/               # HDP-specific MediaWiki configuration
93|93|docs/                       # Architecture diagrams and wiki documentation
94|94|scripts/                    # Utility scripts (doc conversion, etc.)
95|95|publiccode.yml              # EU publiccode.yml metadata
96|96|```
97|97|
98|98|---
99|99|
100|100|## License / Lizenz
101|101|
102|102|**EN:** By contributing, you agree that your contributions are licensed under [GPL-3.0-only](LICENSE), the same license that covers the project.
103|103|
104|104|**DE:** Durch Ihren Beitrag stimmen Sie zu, dass Ihre Beiträge unter [GPL-3.0-only](LICENSE) lizenziert sind, derselben Lizenz, die das Projekt abdeckt.
105|105|