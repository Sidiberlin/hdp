bs.util.registerNamespace( 'bs.bookshelf.notifications' );

bs.bookshelf.notifications.BookSubscriptionSet = function ( cfg ) { // eslint-disable-line no-unused-vars
	// Parent constructor
	bs.bookshelf.notifications.BookSubscriptionSet.parent.apply( this, arguments );
};

OO.inheritClass( bs.bookshelf.notifications.BookSubscriptionSet, ext.notifyme.ui.SubscriptionSet );

bs.bookshelf.notifications.BookSubscriptionSet.prototype.getLabel = function () {
	return mw.message( 'bs-bookshelf-notification-subscription-set-book-title' ).text();
};

bs.bookshelf.notifications.BookSubscriptionSet.prototype.getKey = function () {
	return 'book';
};

bs.bookshelf.notifications.BookSubscriptionSet.prototype.getEditor = function ( dialog ) {
	return new bs.bookshelf.notifications.BookSubscriptionSetEditor( { dialog: dialog } );
};

bs.bookshelf.notifications.BookSubscriptionSet.prototype.getHeaderKeyValue = function () {
	return this.value.set.book;
};

ext.notifyme.subscriptionSetRegistry.register( 'book', bs.bookshelf.notifications.BookSubscriptionSet );
