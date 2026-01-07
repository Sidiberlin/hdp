$( () => {
	const $container = $( '#wikiexplorer' ); // eslint-disable-line no-jquery/no-global-selector
	if ( $container.length === 0 ) {
		return;
	}

	const panel = new bs.wikiexplorer.ui.Panel( {
		expanded: false
	} );

	$container.append( panel.$element );
} );
