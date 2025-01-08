( function ( mw, $, d ) {
	$( d ).on( 'click', '#ca-bs-qr-code', function ( e ) {
		e.preventDefault();

		var windowManager = OO.ui.getWindowManager();
		var pageName = mw.config.get( 'wgPageName' );
		var params = {
			action: 'bs-qr-code',
			page: pageName
		};

		var api = new mw.Api();
		api.get( params ).done( function ( response ) {
			var dialog = new bs.ui.dialog.QrCodeDialog( {
				titleMsg: 'bs-qr-code-action-qr-code-dialog-title',
				altMsg: 'bs-qr-code-action-qr-code-dialog-alt',
				page: pageName,
				data: response.data
			} );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		} );
	} );
}( mediaWiki, jQuery, document ) );
