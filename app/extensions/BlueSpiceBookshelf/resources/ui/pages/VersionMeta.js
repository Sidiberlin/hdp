( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.VersionMeta = function( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.VersionMeta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.VersionMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.VersionMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-version' ).text();
	};

	bs.bookshelf.ui.pages.VersionMeta.prototype.setup = function () {
		this.inputWidget = new OO.ui.TextInputWidget( {
			value: this.value
		} );

		var fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-version' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.VersionMeta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.VersionMeta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

} )( mediaWiki, jQuery, blueSpice );