$( document ).on( 'click', '.bs-books-overview-action-delete', function ( e ) {
	var target = e.target;
	if ( target.nodeName != 'A' ) {
		target = $( target ).parent();
	}

	var pageName = $( target ).data( 'prefixed_db_key' );
	mw.loader.using( [ 'ext.standardDialogs.ui.DeleteDialog' ] ).done( function () {
		const msg = require( './deleteDialogMsg.json' );

		const diag = new StandardDialogs.ui.DeleteDialog( {
			id: 'standarddialogs-dlg-delete',
			pageName: pageName,
			msg: msg
		} );
		diag.on( 'actioncompleted', function () {
			window.location.reload();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );
