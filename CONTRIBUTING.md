# Contributing to HDP / Beiträge leisten

> **EN:** Contributions are welcome! This project is funded by BMBF and developed as an open-source BlueSpice MediaWiki distribution with a Haystack RAG chatbot.
>
> **DE:** Beiträge sind willkommen! Dieses Projekt wird vom BMBF gefördert und als Open-Source-BlueSpice-MediaWiki-Distribution mit Haystack-RAG-Chatbot entwickelt.

---

## Development Setup / Entwicklungsumgebung

**EN:** See [`README-DOCKER.md`](README-DOCKER.md) for complete Docker setup instructions. Quick start:

**DE:** Siehe [`README-DOCKER.md`](README-DOCKER.md) für vollständige Docker-Einrichtungsanleitung. Kurzanleitung:

```bash
cp .env.example .env
# Edit .env — set passwords and HDP_LLM_API_KEY
docker compose up -d --build
docker compose exec mediawiki bash /setup.sh
```

**Requirements / Voraussetzungen:**
- Docker 24+ and Docker Compose v2
- 8 GB RAM minimum (embedding model + OpenSearch)
- 10 GB free disk space (Docker images + embeddings)

---

## How to Contribute / Wie beitragen

### 1. Report Issues / Probleme melden

**EN:** Use the [GitHub issue tracker](https://github.com/Sieddi/hdp/issues). Search existing issues before creating a new one. Include:
- BlueSpice HDP version (see `publiccode.yml` → `softwareVersion`)
- Docker and Docker Compose versions
- Steps to reproduce
- Expected vs. actual behavior
- Relevant log output (`docker compose logs <service>`)

**DE:** Verwenden Sie den [GitHub-Issue-Tracker](https://github.com/Sieddi/hdp/issues). Suchen Sie zunächst in bestehenden Issues. Geben Sie an:
- BlueSpice-HDP-Version (siehe `publiccode.yml` → `softwareVersion`)
- Docker- und Docker-Compose-Versionen
- Schritte zur Reproduktion
- Erwartetes vs. tatsächliches Verhalten
- Relevante Protokollausgaben (`docker compose logs <service>`)

### 2. Submit Merge Requests / Merge Requests einreichen

**EN:**
1. Fork the repository on GitHub
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes — keep commits focused
4. **Run `./scripts/check.sh`** — one command, about 100 seconds, no toolchain
   install and no `.env` required. It returns the same verdict as the CI lint
   stage, so a green run here means a green pipeline.
5. Test against a running stack: `docker compose up -d --build && docker compose exec mediawiki bash /setup.sh`
6. Verify the wiki loads at `http://localhost:8080/w/`
7. Submit a merge request with a clear description

**DE:**
1. Forken Sie das Repository auf GitHub
2. Erstellen Sie einen Feature-Branch: `git checkout -b feature/mein-feature`
3. Nehmen Sie Ihre Änderungen vor — halten Sie Commits fokussiert
4. **Führen Sie `./scripts/check.sh` aus** — ein Befehl, ca. 100 Sekunden, ohne
   Toolchain-Installation und ohne `.env`. Das Ergebnis entspricht dem der
   CI-Lint-Stufe.
5. Testen Sie gegen einen laufenden Stack: `docker compose up -d --build && docker compose exec mediawiki bash /setup.sh`
6. Prüfen Sie, ob das Wiki unter `http://localhost:8080/w/` lädt
7. Reichen Sie einen Merge Request mit klarer Beschreibung ein

#### Before pushing / Vor dem Push

```bash
./scripts/check.sh              # all checks (~100s)
./scripts/check.sh --fix        # apply auto-fixes where available
./scripts/check.sh --only ruff  # a single check
./scripts/check.sh --list       # what each check needs

# Tests specifically
scripts/ci/pytest.sh --tier unit      # stdlib only, ~2s — run this on every save
scripts/ci/pytest.sh                  # unit + haystack
scripts/ci/bats.sh                    # shell behaviour

# Against a real wiki (needs docker; boots the stack, installs it, tears it down)
scripts/ci/t3-integration.sh

# ... and the full seven-container smoke suite (search + chatbot, ~25 min):
scripts/ci/t4-smoke.sh
```

**EN:** Each check prefers a tool already on your `PATH` and otherwise runs the
same pinned container image CI uses, so there is nothing to install. A check
that can run neither way is reported `SKIP` — **a skip is not a pass**, and the
output says so; CI will still run it.

The most important one is `fresh-clone`. `docs/QA-REPORT.md` records seven bugs,
six of them critical, each of which was invisible in the developer's own
checkout and only appeared on a genuinely fresh clone. That check clones the
repository at `HEAD` into a temporary directory and asserts everything
`setup.sh` needs is actually committed — so it tests **committed** state, not
your working tree.

If you change vendored upstream code under `app/`, see
[`docs/dev/patches.md`](docs/dev/patches.md): patches belong in the manifest,
not in an inline `sed`.

**DE:** Jede Prüfung nutzt bevorzugt ein bereits installiertes Werkzeug und
sonst dasselbe gepinnte Container-Image wie die CI — es muss nichts installiert
werden. Eine Prüfung, die auf keinem Weg laufen kann, wird als `SKIP` gemeldet;
**ein Skip ist kein Pass**, und die CI führt sie trotzdem aus.

Am wichtigsten ist `fresh-clone`: sechs der sieben in `docs/QA-REPORT.md`
dokumentierten Fehler waren nur in einem frischen Clone sichtbar. Die Prüfung
klont das Repository bei `HEAD` in ein temporäres Verzeichnis und prüft den
**committeten** Stand, nicht Ihr Arbeitsverzeichnis.

### 3. Code Style / Code-Stil

**EN:**
- PHP: Follow [MediaWiki coding conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP) (`phan` and `phpcs` configs exist in the repo)
- Shell scripts: Use `set -euo pipefail` in all scripts
- Docker: Pin all image versions explicitly (no `latest` tags)
- Secrets: Never hardcode — use `.env` references (`${HDP_*}`)

**DE:**
- PHP: Befolgen Sie die [MediaWiki-Coding-Conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP) (`phan`- und `phpcs`-Konfigurationen vorhanden)
- Shell-Skripte: Verwenden Sie `set -euo pipefail` in allen Skripten
- Docker: Alle Image-Versionen explizit pinnen (keine `latest`-Tags)
- Secrets: Niemals fest codieren — `.env`-Referenzen verwenden (`${HDP_*}`)

---

## Project Structure / Projektstruktur

```
docker-compose.yml          # 7-service Docker stack
docker/                     # Container build contexts + setup scripts
  setup.sh                  # First-boot MediaWiki installer
  haystack/                 # Haystack RAG pipeline + ingestion
  mediawiki/                # Wiki templates, FAQ, Help pages
app/                        # MediaWiki application root
  extensions/               # ~130 BlueSpice + standard extensions (vendored)
  skins/                    # BlueSpiceDiscovery + standard skins (vendored)
  settings.d/               # HDP-specific MediaWiki configuration
docs/                       # Architecture diagrams and wiki documentation
scripts/                    # Utility scripts (doc conversion, etc.)
publiccode.yml              # EU publiccode.yml metadata
```

---

## License / Lizenz

**EN:** By contributing, you agree that your contributions are licensed under [GPL-3.0-only](LICENSE), the same license that covers the project.

**DE:** Durch Ihren Beitrag stimmen Sie zu, dass Ihre Beiträge unter [GPL-3.0-only](LICENSE) lizenziert sind, derselben Lizenz, die das Projekt abdeckt.
