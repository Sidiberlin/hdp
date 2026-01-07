( function ( mw, $, bs ) {
	// eslint-disable-next-line no-new
	new bs.interwiki.search.InterwikiWidget( {
		$element: $( '#bs-interwiki-search' ),
		sources: mw.config.get( 'bsgInterwikiSearchSources' )
	} );
}( mediaWiki, jQuery, blueSpice ) );
