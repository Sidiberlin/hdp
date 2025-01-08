( function ( mw ) {

	mw.hook( 'enhancedUpload.makeParamProcessor' ).add( function ( paramsProcessor ) {
		paramsProcessor.processor = new nsfr.EnhancedUploadParamsProcessor();
	} );

}( mediaWiki ) );
