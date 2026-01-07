bs.util.registerNamespace( 'ext.bookshelf.ui.dialog' );

require( './../widget/MetaDataLayout.js' );
require( './../widget/MetaDataOutlineWidget.js' );

ext.bookshelf.ui.dialog.MetaDataDialog = function ( config ) {
	config = config || {};
	this.bookTitle = config.bookTitle || mw.config.get( 'wgRelevantPageName' );
	this.originData = config.data;
	ext.bookshelf.ui.dialog.MetaDataDialog.super.call( this, config );
	this.metadata = [];
};
OO.inheritClass( ext.bookshelf.ui.dialog.MetaDataDialog, OO.ui.ProcessDialog );

ext.bookshelf.ui.dialog.MetaDataDialog.static.name = 'MetaDataDialog';
ext.bookshelf.ui.dialog.MetaDataDialog.static.title = mw.message( 'bs-bookshelf-metadata-dlg-title' ).text();
ext.bookshelf.ui.dialog.MetaDataDialog.static.size = 'larger';
ext.bookshelf.ui.dialog.MetaDataDialog.static.actions = [
	{
		action: 'save',
		label: mw.message( 'bs-bookshelf-metadata-dlg-action-save-label' ).text(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		title: mw.message( 'cancel' ).text(),
		flags: [ 'safe', 'close' ]
	}
];

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.initialize = function () {
	ext.bookshelf.ui.dialog.MetaDataDialog.super.prototype.initialize.apply( this, arguments );
	const data = require( './metadata.json' );

	// eslint-disable-next-line
	const modules = Object.values( data.modules );

	mw.loader.using( modules ).done( () => {
		const pages = this.getPagesFromConfig( data.pages );
		const metaDataKeys = this.getKeysFromConfig( data.pages );
		this.content = new ext.bookshelf.ui.widget.MetaDataLayout( {
			originData: this.originData,
			metaDataKeys: metaDataKeys
		} );
		this.content.addItems( pages );
		this.$body.append( this.content.$element );
		this.updateSize();
	} );
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getActionProcess = function ( action ) {
	const dialog = this;
	if ( action ) {
		dialog.metadata = dialog.getNewMetaData();
		return new OO.ui.Process( () => {
			dialog.emit( 'metadataset', dialog.metadata );
			dialog.close( { action: action } );
		} );
	}
	return ext.bookshelf.ui.dialog.MetaDataDialog.super.prototype.getActionProcess.call( this, action );
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getNewMetaData = function () {
	const data = {};
	const pages = this.content.pages;
	for ( const page in pages ) {
		const value = pages[ page ].getValue();
		if ( value === '' ) {
			continue;
		}
		data[ page ] = value;
	}
	return data;
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getPagesFromConfig = function ( data ) {
	const pages = [];
	for ( const key in data ) {
		const classname = this.callbackFromString( data[ key ].classname );
		let active = false;
		let value = '';
		if ( this.originData.hasOwnProperty( key ) ) {
			active = true;
			value = this.originData[ key ];
		}
		const page = new classname( key, { // eslint-disable-line new-cap
			active: active,
			value: value,
			key: key,
			$overlay: this.$overlay
		} );
		page.toggle( active );
		pages.push( page );
	}

	pages.sort( ( a, b ) => a.getOutlineLabel().localeCompare( b.getOutlineLabel() ) );
	return pages;
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getKeysFromConfig = function ( data ) {
	const keys = [];
	for ( const key in data ) {
		keys.push( data[ key ].key );
	}

	return keys;
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.callbackFromString = function ( callback ) {
	const parts = callback.split( '.' );
	let func = window[ parts[ 0 ] ];
	for ( let i = 1; i < parts.length; i++ ) {
		func = func[ parts[ i ] ];
	}

	return func;
};
