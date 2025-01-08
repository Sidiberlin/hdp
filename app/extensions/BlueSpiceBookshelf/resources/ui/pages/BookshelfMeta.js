( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.BookshelfMeta = function( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.BookshelfMeta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.BookshelfMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.BookshelfMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-bookshelf' ).text();
	};

	bs.bookshelf.ui.pages.BookshelfMeta.prototype.setup = function () {
		var values = require( './bookshelfdata.json' );
		var options = [];
		if ( values.length > 0 ) {
			values.forEach( function ( val ) {
				options.push( {
					data: val
				} );
			} );
		}
		this.inputWidget = new OO.ui.ComboBoxInputWidget( {
			options: options,
			$overlay: true,
			value: this.value
		} );

		var fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-bookshelf' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.BookshelfMeta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.BookshelfMeta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

} )( mediaWiki, jQuery, blueSpice );