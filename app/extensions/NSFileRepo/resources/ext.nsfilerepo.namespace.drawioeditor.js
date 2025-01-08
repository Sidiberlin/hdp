mw.hook( 'drawioeditor.makeFilenameProcessor' ).add( function ( filenameProcessor ) {
	filenameProcessor.processor = new nsfr.NamespaceFilenameProcessor();
} );
