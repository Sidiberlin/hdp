( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.DepartmentMeta = function( name, config ) {
		config = config || {};
		bs.bookshelf.ui.pages.DepartmentMeta.super.call( this, name, config );
	};

	OO.inheritClass( bs.bookshelf.ui.pages.DepartmentMeta, bs.bookshelf.ui.pages.MetaDataPage );

	bs.bookshelf.ui.pages.DepartmentMeta.prototype.getOutlineLabel = function () {
		return mw.message( 'bs-bookshelfui-bookmetatag-department' ).text();
	};

	bs.bookshelf.ui.pages.DepartmentMeta.prototype.setup = function () {
		this.inputWidget = new OO.ui.TextInputWidget( {
			value: this.value
		} );

		var fieldLayout = new OO.ui.FieldLayout( this.inputWidget, {
			align: 'top',
			label: mw.message( 'bs-bookshelfui-bookmetatag-department' ).text()
		} );

		this.$element.append( fieldLayout.$element );
	};

	bs.bookshelf.ui.pages.DepartmentMeta.prototype.getValue = function () {
		return this.inputWidget.getValue();
	};

	bs.bookshelf.ui.pages.DepartmentMeta.prototype.setValue = function ( value ) {
		this.inputWidget.setValue( value );
	};

} )( mediaWiki, jQuery, blueSpice );