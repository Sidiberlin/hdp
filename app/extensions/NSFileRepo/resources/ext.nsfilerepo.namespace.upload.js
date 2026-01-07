( function ( mw ) {

	mw.hook( 'enhancedUpload.makeParamProcessor' ).add( ( paramsProcessor ) => {
		paramsProcessor.processors.push( new nsfr.EnhancedUploadParamsProcessor() );
	} );

}( mediaWiki ) );
