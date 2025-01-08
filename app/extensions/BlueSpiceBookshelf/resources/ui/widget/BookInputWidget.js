bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

ext.bookshelf.ui.widget.BookInputWidget = function( config ) {
	config = config || {};
	config.$overlay = config.$overlay || true;
	ext.bookshelf.ui.widget.BookInputWidget.parent.call( this, $.extend( {}, config, { autocomplete: false } ) );
	OO.ui.mixin.LookupElement.call( this, config );

	this.returnProperty = config.returnProperty || 'book_displaytext';
};

/* Setup */

OO.inheritClass( ext.bookshelf.ui.widget.BookInputWidget, OO.ui.TextInputWidget );
OO.mixinClass( ext.bookshelf.ui.widget.BookInputWidget, OO.ui.mixin.LookupElement );

/* Methods */

/**
 * Handle menu item 'choose' event, updating the text input value to the value of the clicked item.
 *
 * @param {OO.ui.MenuOptionWidget} item Selected item
 */
ext.bookshelf.ui.widget.BookInputWidget.prototype.onLookupMenuChoose = function ( item ) {
	this.closeLookupMenu();
	this.setLookupsDisabled( true );
	this.setValue( item.getData()[ this.returnProperty ] || '' );
	this.setLookupsDisabled( false );
};

/**
 * @inheritdoc
 */
ext.bookshelf.ui.widget.BookInputWidget.prototype.focus = function () {
	var retval;

	// Prevent programmatic focus from opening the menu
	this.setLookupsDisabled( true );

	// Parent method
	retval = ext.bookshelf.ui.widget.BookInputWidget.parent.prototype.focus.apply( this, arguments );

	this.setLookupsDisabled( false );

	return retval;
};

/**
 * @inheritdoc
 */
ext.bookshelf.ui.widget.BookInputWidget.prototype.getLookupRequest = function () {
	var inputValue = this.value;

	return new mw.Api().get( {
		action: 'bs-bookshelf-store',
		query: inputValue
	} );
};

ext.bookshelf.ui.widget.BookInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response.results || {};
};

ext.bookshelf.ui.widget.BookInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	var i, bookData,
		items = [];

	for ( i = 0; i < data.length; i++ ) {
		bookData = data[i];
		items.push( new OO.ui.MenuOptionWidget( {
			label: bookData.book_displaytext,
			data: bookData
		} ) );
	}

	return items;
};