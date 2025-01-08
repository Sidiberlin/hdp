( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.BookTitleMeta = function( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.BookTitleMeta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.BookTitleMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.BookTitleMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-title' ).text();
	};

	bs.bookshelf.ui.pages.BookTitleMeta.prototype.setup = function () {
		this.inputWidget = new OO.ui.TextInputWidget( {
			value: this.value
		} );

		var fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-title' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.BookTitleMeta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.BookTitleMeta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

} )( mediaWiki, jQuery, blueSpice );