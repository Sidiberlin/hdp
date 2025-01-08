/*!
 * Enable double-click-to-edit functionality.
 */
( function( mw, $, d ){
	if ( Number( mw.user.options.get( 'editondblclick' ) ) !== 1 ) {
		return;
	}

	if ( mw.config.get( 'wgAction' ) !== 'view' ) {
		return;
	}

	$( '#mw-content-text' ).on( 'dblclick', function ( e ) {
		var $visualEdit = $( '#ca-ve-edit' );
		var $sourceEdit = $( '#ca-edit' );

		if ( $visualEdit.length ) {
			e.preventDefault();
			$visualEdit.get( 0 ).click();
		} else if ( $sourceEdit.length ) {
			e.preventDefault();
			$visualEdit.get( 0 ).click();
		}
	} );
} )( mediaWiki, jQuery, document );