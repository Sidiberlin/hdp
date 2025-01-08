window.officeimport = {
	ui: {},
	api: {}
};

$( function () {
	$( document ).on( 'click', '#ca-import-office-file', function ( e ) {
		e.preventDefault();

		mw.loader.using( [ 'ext.importofficefiles.ui.form' ], function () {
			const windowManager = new OO.ui.WindowManager();
			$( document.body ).append( windowManager.$element );

			const dialog = new officeimport.ui.ImportDialog();
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		} );
	} );
} );
