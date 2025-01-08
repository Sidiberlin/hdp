mw.hook( 'cpd.makeFilenameProcessor' ).add( function ( filenameProcessor ) {
	filenameProcessor.processor = new nsfr.NamespaceFilenameProcessor();
} );
