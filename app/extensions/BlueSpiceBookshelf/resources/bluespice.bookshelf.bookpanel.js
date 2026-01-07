$( () => {

	require( './ui/panel/BookNavigationTreePanel.js' );
	/* eslint-disable-next-line no-jquery/no-global-selector */
	const $bookTreeCnt = $( '#book-panel-tree' );

	const bookTreePanel = new ext.bookshelf.ui.panel.BookNavigationTreePanel( {
		skeletonID: 'bs-bookshelf-tree-skeleton'
	} );
	$bookTreeCnt.append( bookTreePanel.$element );
} );
