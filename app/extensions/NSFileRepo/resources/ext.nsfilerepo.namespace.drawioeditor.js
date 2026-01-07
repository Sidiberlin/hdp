mw.hook( 'drawioeditor.makeFilenameProcessor' ).add( ( filenameProcessor ) => {
	filenameProcessor.processor = new nsfr.NamespaceFilenameProcessor();
} );
