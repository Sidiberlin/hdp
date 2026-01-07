bs.util.registerNamespace( 'ext.bookshelf.data' );

ext.bookshelf.data.BookNavigationTreeStore = function ( cfg ) {
	ext.bookshelf.data.BookNavigationTreeStore.parent.call( this, cfg );
	this.activeChapterNumber = mw.config.get( 'bsActiveChapterNumber' );
};

OO.inheritClass( ext.bookshelf.data.BookNavigationTreeStore, OOJSPlus.ui.data.store.NavigationTreeStore );

ext.bookshelf.data.BookNavigationTreeStore.prototype.getRequestData = function () {
	const data = ext.bookshelf.data.BookNavigationTreeStore.parent.prototype.getRequestData.call( this );

	return {
		bookID: mw.config.get( 'bsActiveBookId' ),
		chapterNumber: this.activeChapterNumber,
		node: data.node,
		'expand-paths': data[ 'expand-paths' ]
	};
};

ext.bookshelf.data.BookNavigationTreeStore.prototype.setActiveChapterNumber = function ( chapterNumber ) {
	this.activeChapterNumber = chapterNumber;
};
