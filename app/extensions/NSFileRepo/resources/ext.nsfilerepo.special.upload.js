( function ( mw, $, d ) {
	// eslint-disable-next-line no-underscore-dangle
	function _setDestName() {
		// eslint-disable-next-line no-jquery/no-global-selector
		var prefix = $( '#mw-input-wpNSFR_Namespace' ).val();
		// eslint-disable-next-line no-jquery/no-global-selector
		var destName = $( '#wpDestFile' ).val();
		var destFileParts = [ destName ];
		if ( prefix !== '-' ) {
			destFileParts.unshift( prefix );
		}

		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#mw-input-wpNSFR_DestFile' ).val( destFileParts.join( ':' ) );
	}

	// eslint-disable no-jquery/no-global-selector
	$( d ).on( 'change', '#mw-input-wpNSFR_Namespace', _setDestName );
	$( d ).on( 'change', '#wpUploadFile', _setDestName );
	$( d ).on( 'change', '#wpDestFile', _setDestName );

	// eslint-disable no-jquery/no-global-selector
	$( d ).on( 'submit', '#mw-upload-form', function () {
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#wpDestFile' ).val( $( '#mw-input-wpNSFR_DestFile' ).val() );
	} );

}( mediaWiki, jQuery, document ) );
