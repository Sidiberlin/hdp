window.bs = window.bs || {};

bs.wikiexplorer = {
	grid: {},
	store: {},
	ui: {}
};

require( './store/WikiExplorer.js' );
require( './ui/Panel.js' );
require( './special.wikiExplorer.js' );