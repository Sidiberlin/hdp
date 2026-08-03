mw.hook( 'pdfcreator.export.data' ).add( ( context, data ) => {
	if ( data.mode !== 'book' ) {
		return;
	}
	if ( mw.util.getParamValue( 'book' ) ) {
		const activeBook = mw.util.getParamValue( 'book' );
		data.book = activeBook;
		data.relevantTitle = activeBook;
	} else if ( mw.config.get( 'bsActiveBook' ) ) {
		const activeBook = mw.config.get( 'bsActiveBook' );
		data.book = activeBook;
		data.relevantTitle = activeBook;
	}
} );
