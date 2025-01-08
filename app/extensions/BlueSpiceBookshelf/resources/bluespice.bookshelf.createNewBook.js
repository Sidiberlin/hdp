$( document ).on( 'click', '.new-book-action', function( e ) {
	e.preventDefault();
	require( './ui/dialog/AddNewBook.js' );
	var modules = require( './pluginModules.json' );
	mw.loader.using( modules ).done( function () {
		if ( !this.windowManager ) {
			this.windowManager = new OO.ui.WindowManager( {
				modal: true
			} );
			$( document.body ).append( this.windowManager.$element );
		}
		var dialog = new ext.bookshelf.ui.dialog.AddNewBookDialog();
		dialog.connect( this, {
			book_created: function ( bookTitle ) {
				window.location.href = mw.util.getUrl(
					'Special:Books',
					{
						filter: bookTitle
					}
				)
			}
		} );
		this.windowManager.addWindows( [ dialog ] );
		this.windowManager.openWindow( dialog );
	} );

	return false;
} );
