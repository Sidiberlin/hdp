mw.hook( 'pdfcreator.export.data' ).add( ( context, data ) => {
	if ( data.mode !== 'book' ) {
		return;
	}
	if ( mw.util.getParamValue( 'book' ) ) {
		data.book = mw.util.getParamValue( 'book' );
		data.relevantTitle = mw.util.getParamValue( 'book' );
	}
} );
