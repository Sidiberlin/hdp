( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.Author2Meta = function ( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.Author2Meta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.Author2Meta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.Author2Meta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-author2' ).text();
	};

	bs.bookshelf.ui.pages.Author2Meta.prototype.setup = function () {
		this.inputWidget = new OO.ui.TextInputWidget( {
			value: this.value
		} );

		const fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-author2' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.Author2Meta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.Author2Meta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

}( mediaWiki, jQuery, blueSpice ) );
