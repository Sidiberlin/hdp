1|1|# Security Policy / Sicherheitsrichtlinie
2|2|
3|3|## Reporting a Vulnerability / Melden einer Schwachstelle
4|4|
5|5|**EN:**
6|6|
7|7|**Do NOT open a public issue for security vulnerabilities.**
8|8|
9|9|To report a security issue:
10|10|1. Open a **confidential** issue in the [GitHub issue tracker](https://github.com/Sieddi/hdp/issues) using the **`security`** label
11|11|2. Or contact the maintainers directly via GitHub
12|12|
13|13|Please include:
14|14|- Description of the vulnerability
15|15|- Steps to reproduce or proof of concept
16|16|- Affected versions (see `publiccode.yml` → `softwareVersion`)
17|17|- Suggested fix (if any)
18|18|
19|19|We will acknowledge receipt within **72 hours** and provide an initial assessment within **7 days**.
20|20|
21|21|---
22|22|
23|23|**DE:**
24|24|
25|25|**Erstellen Sie KEIN öffentliches Issue für Sicherheitslücken.**
26|26|
27|27|So melden Sie ein Sicherheitsproblem:
28|28|1. Erstellen Sie ein **vertrauliches** Issue im [GitHub-Issue-Tracker](https://github.com/Sieddi/hdp/issues) mit dem Template **security** oder dem Label **`security`**
29|29|2. Oder kontaktieren Sie die Maintainer direkt über GitHub
30|30|
31|31|Bitte geben Sie an:
32|32|- Beschreibung der Schwachstelle
33|33|- Schritte zur Reproduktion oder Proof of Concept
34|34|- Betroffene Versionen (siehe `publiccode.yml` → `softwareVersion`)
35|35|- Vorgeschlagene Lösung (falls vorhanden)
36|36|
37|37|Wir bestätigen den Eingang innerhalb von **72 Stunden** und liefern eine Ersteinschätzung innerhalb von **7 Tagen**.
38|38|
39|39|---
40|40|
41|41|## Supported Versions / Unterstützte Versionen
42|42|
43|43|**EN:** Only the latest release (tracked via `publiccode.yml` → `softwareVersion`) receives security updates.
44|44|
45|45|**DE:** Nur die neueste Version (verfolgt über `publiccode.yml` → `softwareVersion`) erhält Sicherheitsupdates.
46|46|
47|47|## Scope / Geltungsbereich
48|48|
49|49|**EN:** This policy covers the HDP Docker distribution, `docker-compose.yml`, `setup.sh`, and the Haystack RAG pipeline. Vulnerabilities in upstream BlueSpice extensions or MediaWiki core should be reported to their respective projects:
50|50|
51|51|- MediaWiki: <https://phabricator.wikimedia.org/maniphest/>
52|52|- BlueSpice: <https://help.bluespice.com/>
53|53|
54|54|**DE:** Diese Richtlinie deckt die HDP-Docker-Distribution, `docker-compose.yml`, `setup.sh` und die Haystack-RAG-Pipeline ab. Schwachstellen in Upstream-BlueSpice-Erweiterungen oder MediaWiki-Core sollten bei den jeweiligen Projekten gemeldet werden:
55|55|
56|56|- MediaWiki: <https://phabricator.wikimedia.org/maniphest/>
57|57|- BlueSpice: <https://help.bluespice.com/>
58|58|