<?php
/**
 * Mermaid extension — renders MermaidJS diagrams client-side via the
 * `{{#mermaid: ...}}` parser function.
 *
 * Used by the Help:Architektur / Help:Diagramme/* pages that were
 * generated from docs/wiki/*.md — those documents contain embedded
 * flowcharts and sequence diagrams that only render usefully if the
 * mermaid.min.js runtime is served by the wiki.
 *
 * Vendored source: https://github.com/SemanticMediaWiki/Mermaid tag 6.0.2
 * Numbered 060 so it loads before BlueSpiceDiscovery (080) but after the
 * core BlueSpice bundles (030-050), avoiding any race with the parser
 * initialisation order.
 */

wfLoadExtension( 'Mermaid' );

// Keep the extension's default theme ("forest") — matches the existing
// architecture.png colour palette in app/skins/hdp/. Override here if
// site-wide restyle is ever needed.
// $GLOBALS['mermaidgDefaultTheme'] = 'neutral';
