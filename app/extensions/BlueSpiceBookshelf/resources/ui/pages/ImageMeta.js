( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.ImageMeta = function( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.ImageMeta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.ImageMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.ImageMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-bookshelfimage' ).text();
	};

	bs.bookshelf.ui.pages.ImageMeta.prototype.setup = function () {
		this.inputWidget = new OOJSPlus.ui.widget.FileSearchWidget( {
			extensions: [ 'png', 'jpg' ],
			value: this.value
		} );

		var fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-bookshelfimage' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.ImageMeta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.ImageMeta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

} )( mediaWiki, jQuery, blueSpice );