mw.hook( 'cpd.makeFilenameProcessor' ).add( ( filenameProcessor ) => {
	filenameProcessor.processor = new nsfr.NamespaceFilenameProcessor();
} );
