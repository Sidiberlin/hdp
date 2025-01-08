bs.util.registerNamespace( 'ext.bookshelf.ui.dialog' );

require( './../widget/MetaDataLayout.js' );
require( './../widget/MetaDataOutlineWidget.js' );

ext.bookshelf.ui.dialog.MetaDataDialog = function ( config ) {
	config = config || {};
	this.bookTitle = config.bookTitle || mw.config.get( 'wgRelevantPageName' );
	this.originData = config.data;
	ext.bookshelf.ui.dialog.MetaDataDialog.super.call( this, config );
	this.metadata = [];
}
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
		label: mw.message( 'cancel' ).text(),
		flags: 'safe'
	}
];

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.initialize = function () {
	ext.bookshelf.ui.dialog.MetaDataDialog.super.prototype.initialize.apply( this, arguments );
	var data = require( './metadata.json');
	var modules = data.modules;

	mw.loader.using( modules ).done( function () {
		var pages = this.getPagesFromConfig( data.pages );
		var metaDataKeys = this.getKeysFromConfig( data.pages );
		this.content = new ext.bookshelf.ui.widget.MetaDataLayout( {
			originData: this.originData,
			metaDataKeys: metaDataKeys
		} );
		this.content.addItems( pages );
		this.$body.append( this.content.$element );
		this.updateSize();
	}.bind( this ) );
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action ) {
		dialog.metadata = dialog.getNewMetaData();
		return new OO.ui.Process( function () {
			dialog.emit( 'metadataset', dialog.metadata );
			dialog.close( { action: action } );
		} );
	}
	return ext.bookshelf.ui.dialog.MetaDataDialog.super.prototype.getActionProcess.call( this, action );
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getNewMetaData = function () {
	var data = {};
	var pages = this.content.pages;
	for ( var page in pages ) {
		var value = pages[ page ].getValue();
		if ( value === '' ) {
			continue;
		}
		data[ page ] = value;
	}
	return data;
}

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getPagesFromConfig = function ( data ) {
	var pages = [];
	for ( var key in data ) {
		var classname = this.callbackFromString( data[ key ].classname );
		var active = false;
		var value = '';
		if ( this.originData.hasOwnProperty( key ) ) {
			active = true;
			value = this.originData[ key ];
		}
		var page =  new classname( key, {
			active: active,
			value: value,
			key: key
		} );
		page.toggle( active );
		pages.push( page );
	}

	pages.sort( function ( a, b ) {
		return a.getOutlineLabel().localeCompare( b.getOutlineLabel() );
	} );
	return pages;
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.getKeysFromConfig = function ( data ) {
	var keys = [];
	for ( var key in data ) {
		keys.push( data[ key ].key );
	}

	return keys;
};

ext.bookshelf.ui.dialog.MetaDataDialog.prototype.callbackFromString = function( callback ) {
	var parts = callback.split( '.' );
	var func = window[parts[0]];
	for( var i = 1; i < parts.length; i++ ) {
		func = func[parts[i]];
	}

	return func;
};
