bs.util.registerNamespace( 'bs.bookshelf.notifications' );

bs.bookshelf.notifications.BookSubscriptionSetEditor = function( cfg ) {
	bs.bookshelf.notifications.BookSubscriptionSetEditor.parent.call( this, cfg );
};

OO.inheritClass( bs.bookshelf.notifications.BookSubscriptionSetEditor, ext.notifyme.ui.subscriptionset.editor.SubscriptionSetEditor );

bs.bookshelf.notifications.BookSubscriptionSetEditor.prototype.makeLayout = function() {
	this.bookPicker = new mw.widgets.TitleInputWidget( {
		$overlay: this.dialog ? this.dialog.$overlay : true,
		required: true
	} );
	this.bookPicker.setNamespace( 1504 );
	this.bookPicker.connect( this, {
		change: function() {
			this.emit( 'change', this.getValue() );
		}
	} );
	return new OO.ui.FieldLayout( this.bookPicker, {
		align: 'top',
		label: mw.message( 'bs-bookshelf-notification-subscription-set-book-title' ).text()
	} );
};

bs.bookshelf.notifications.BookSubscriptionSetEditor.prototype.getValue = function() {
	return {
		book: this.bookPicker.getValue()
	};
};

bs.bookshelf.notifications.BookSubscriptionSetEditor.prototype.setValue = function( value ) {
	if ( value && value.hasOwnProperty( 'book' ) ) {
		this.bookPicker.setValue( value.book );
	}
};

bs.bookshelf.notifications.BookSubscriptionSetEditor.prototype.getValidity = function() {
	return this.bookPicker.getValidity();
};

bs.bookshelf.notifications.BookSubscriptionSetEditor.prototype.setValidityFlag = function( valid ) {
	this.bookPicker.setValidityFlag( valid );
};

