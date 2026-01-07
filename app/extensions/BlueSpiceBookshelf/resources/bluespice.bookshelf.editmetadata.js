$( document ).on( 'click', '.bs-books-overview-action-metadata', ( e ) => {
	e.preventDefault();
	let target = e.target;
	if ( target.nodeName !== 'A' ) {
		target = $( target ).parent();
	}

	const pageName = $( target ).data( 'prefixed_db_key' );
	mw.loader.using(
		[
			'bluespice.bookshelf.metadata.manager',
			'ext.bookshelf.metadata.dialog'
		] ).done( () => {
		const metadataManager = new ext.bookshelf.data.BookMetaDataManager( pageName );
		metadataManager.load().done( ( data ) => {
			const windowManager = new OO.ui.WindowManager( {
				modal: true
			} );
			$( document.body ).append( windowManager.$element );

			const dialog = new ext.bookshelf.ui.dialog.MetaDataDialog( {
				data: data
			} );
			dialog.on( 'metadataset', ( metadata ) => {
				metadataManager.save( metadata ).done( () => {
					window.location.reload();
				} ).fail( ( error ) => {
					console.log( error ); // eslint-disable-line no-console
				} );
			} );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog );
		} );
	} );
} );
