$( document ).on( 'click', '.bs-books-overview-action-metadata', function( e ) {
	e.preventDefault();
	var target = e.target;
	if ( target.nodeName != 'A' ) {
		target = $( target).parent();
	}

	var pageName = $( target ).data( 'prefixed_db_key' );
	mw.loader.using(
	[
		'bluespice.bookshelf.metadata.manager',
		'ext.bookshelf.metadata.dialog'
	] ).done( function () {
		var metadataManager = new ext.bookshelf.data.BookMetaDataManager( pageName );
		metadataManager.load().done( function ( data ) {
			var windowManager = new OO.ui.WindowManager( {
				modal: true
			} );
			$( document.body ).append( windowManager.$element );

			var dialog = new ext.bookshelf.ui.dialog.MetaDataDialog( {
				data: data
			} );
			dialog.on( 'metadataset', function ( metadata ) {
				metadataManager.save( metadata ).done( function () {
					window.location.reload();
				} ).fail( function ( error ) {
					console.log( error );
				} );
			}.bind( this ) );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		} );
	} );
} );