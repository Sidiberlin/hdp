( function ( mw, $ ) {
	$( document ).on( 'click', '.bs-books-overview-action-export', ( e ) => {
		e.preventDefault();
		let target = e.target;
		if ( target.nodeName !== 'A' ) {
			target = $( target ).parent();
		}

		const bookTitle = $( target ).data( 'prefixed_db_key' );
		exportBook( bookTitle, [] );
	} );

	window.onBookshelfViewToolExportBook = function ( data ) {
		const currentBook = mw.config.get( 'wgPageName' );
		const selectedItems = findSelectedItems( data );

		const chapters = [];
		for ( let index = 0; index < selectedItems.length; index++ ) {
			chapters.push( selectedItems[ index ].number );
		}
		exportBook( currentBook, chapters );
	};

	function exportBook( bookTitle, chapters ) {
		mw.loader.using( [ 'bluespice.bookshelf.api', 'ext.pdfcreator.export.api' ] ).done( () => {
			const bookApi = new ext.bookshelf.api.Api();
			bookApi.getBookTemplateForBook( bookTitle ).done( ( templateData ) => {
				const template = templateData.template;
				const pageId = templateData.pageid;

				const data = {
					mode: 'book',
					book: bookTitle,
					template: template,
					chapters: chapters,
					relevantTitle: bookTitle
				};
				mw.hook( 'pdfcreator.export.data' ).fire( this, data );

				const pdfApi = new ext.pdfcreator.api.Api();
				pdfApi.export( pageId, data ).done( () => {
					mw.notify( mw.message( 'bs-bookshelf-export-pdf-notification-done' ).text() );
				} )
					.fail( ( error ) => {
						console.log( error ); // eslint-disable-line no-console
					} );
			} ).fail( () => {
				console.error( 'export api module could not be loaded' ); // eslint-disable-line no-console
			} );
		} );
	}

	function findSelectedItems( items ) {
		let selectedItems = [];
		for ( let index = 0; index < items.length; index++ ) {
			const item = items[ index ];

			if ( item.hasOwnProperty( 'selected' ) && item.selected === true ) {
				selectedItems.push( item.chapter );
			}

			if ( item.hasOwnProperty( 'children' ) && item.children.length > 0 ) {
				const selectedChildren = findSelectedItems( item.children );
				selectedItems = selectedItems.concat( selectedChildren );
			}
		}
		return selectedItems;
	}
}( mediaWiki, jQuery ) );
