$( document ).on( 'click', '#ca-bookshelf-panel-create-new-book, #ca-bookshelf-actions-primary-new-book, .new-book-action', ( e ) => {
	e.preventDefault();
	require( './ui/dialog/AddNewBook.js' );
	const modules = require( './pluginModules.json' );
	mw.loader.using( modules ).done( function () {
		if ( !this.windowManager ) {
			this.windowManager = new OO.ui.WindowManager( {
				modal: true
			} );
			$( document.body ).append( this.windowManager.$element );
		}
		const dialog = new ext.bookshelf.ui.dialog.AddNewBookDialog();
		dialog.connect( this, {
			book_created: function ( bookTitle ) { // eslint-disable-line camelcase
				window.location.href = mw.util.getUrl(
					'Special:Books',
					{
						filter: bookTitle
					}
				);
			}
		} );
		this.windowManager.addWindows( [ dialog ] );
		this.windowManager.openWindow( dialog );
	} );

	return false;
} );
