( function( mw, $, d ){
	$( d ).on( 'click', '#ca-details', function( e ) {
		e.preventDefault();
		var links = $( this ).data( 'toolbox' );
		mw.loader.using( 'skin.discovery.dialog.details' ).done( function() {
			var windowManager = OO.ui.getWindowManager();
			var dialog = new window.bs.skin.dialog.DetailsDialog( {
				links: links
			} );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		} );
		return false;
	} );
} )( mediaWiki, jQuery, document );