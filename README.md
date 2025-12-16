# Chatbot für Handbuch der Projektförderung

Der Ordner `/app` enthält ein erweitertes Open-Source-Enterprise-Wiki BlueSpice (BlueSpice HDP Edition), die zugehörige KI-Pipeline findet sich im Ordner `/pipeline`.

Die Edition besteht aus BlueSpice pro, der BlueSpice-Vollversion zum Betrieb von Einzelwikis. BlueSpice pro bündelt die wichtigsten Funktionen für ein Produktivsystem, das den Ansprüchen eines professionell geführten Unternehmens gerecht wird.

Zusätzlich verfügt diese Edition über ein Chat-UI, eine Anbindung an ein mit Haystack bereitgestelltes Large-Language Modell und RAG-System, Maintainer-Funktionen (Ansehen und Bearbeiten von Nutzer-Feedback, Prüfung gefundener Quellen, Ergänzung von Metadaten) sowie die Möglichkeit zur Zugriffsbeschränkung auf bestimmte Dokumente.

Durch die Einbindung eines Large Language Models über Haystack (D-Stack Standard für KI) wird der Zugriff auf Inhalte eines Wikis personenunabhängiger, benutzerfreundlicher und inklusiver.

Der enthaltene Haystack Code umfasst die vollständige KI-Pipeline. Das Deployment der Pipeline erfolgt via hayhooks und muss individuell eingerichtet werden. Die Einbindung des gewünschten Large Language Models erfolgt über eine API. 

Die Lösung wurde gemeinsam entwickelt vom Bundesministerium für Bildung und Forschung, der deepset GmbH, dem GovTech Campus Deutschland e.V., der Hallo Welt! GmbH sowie dem Fraunhofer IVV. Sie wurde im KI Transarenzregister als Best Practice ausgezeichnet. 

Bei Test-Installationen, Fragen zur Weiterentwicklung oder zu Subskriptionsmodellen unterstützen Sie gerne:

Hallo Welt! GmbH bzw. GovTech Campus Deutschland e.V.

Kontakt: https://bluespice.com/de/kontakt/

Weitere Informationen: 
-	BlueSpice Website [offizielle Website] (https://bluespice.com)
-	Haystack / KI Orchestrierung [offizielle Website] (https://haystack.deepset.ai/) 
-	KI Transparenzregister (https://maki.kimarktplatz.bund.de/a/bmi-makimo-app/steckbriefe/BD20249356528?kiosk)