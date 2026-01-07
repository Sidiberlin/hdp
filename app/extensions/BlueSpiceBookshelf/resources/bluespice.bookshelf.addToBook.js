$( document ).on( 'click', '#ca-bookshelf-add-to-book', ( e ) => {
	e.preventDefault();
	require( './ui/dialog/AddToBook.js' );

	const dialog = new bs.bookshelf.ui.dialog.AddToBook( {
		pagename: mw.config.get( 'wgPageName' )
	} );
	dialog.show().closed.then( ( actions ) => {
		if ( actions.action === 'cancel' ) {
			return;
		}
		const bookTitle = actions.book;
		const url = new URL( window.location.href );
		url.searchParams.set( 'book', bookTitle );
		window.location.href = url.toString();
	} );

	return false;
} );
