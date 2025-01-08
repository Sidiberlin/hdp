( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.bookshelf.ui.pages' );

	bs.bookshelf.ui.pages.MetaDataPage = function( name, cfg ) {
		cfg = cfg || {};
		cfg.padded = true;
		bs.bookshelf.ui.pages.MetaDataPage.super.call( this, name, cfg );
		this.active = cfg.active || false;
		this.value = cfg.value || '';
		this.key = cfg.key || '';

		this.setup();
	};

	OO.inheritClass( bs.bookshelf.ui.pages.MetaDataPage, OO.ui.PageLayout );

	bs.bookshelf.ui.pages.MetaDataPage.prototype.getOutlineLabel = function () {
		return '';
	};

	bs.bookshelf.ui.pages.MetaDataPage.prototype.isActive = function () {
		return this.active;
	};

	bs.bookshelf.ui.pages.MetaDataPage.prototype.toggleActive = function ( active ) {
		this.active = active;
	};

	bs.bookshelf.ui.pages.MetaDataPage.prototype.setup = function () {
		//STUB
	};

	bs.bookshelf.ui.pages.MetaDataPage.prototype.getValue = function () {
		return '';
	};

	bs.bookshelf.ui.pages.MetaDataPage.prototype.setValue = function () {
		//STUB;
	};


} )( mediaWiki, jQuery, blueSpice );