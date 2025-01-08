bs = bs || {};
bs.ui = bs.ui || {};
bs.ui.widget = bs.ui.widget || {};

bs.ui.widget.TextInputMWVisualEditor = function ( config ) {
	bs.ui.widget.TextInputMWVisualEditor.parent.call( this, config );

	this.selector = config.selector || '.bs-vec-widget';
	this.visualEditor = null;
	this.config = config;
	this.loading = false;
	this.currentValue = config.value || '';
	this.bsVecConfig = require( './config.json' );
};

OO.inheritClass( bs.ui.widget.TextInputMWVisualEditor, OO.ui.MultilineTextInputWidget );

bs.ui.widget.TextInputMWVisualEditor.prototype.onFocus = function() {
	if( this.loading || this.visualEditor ) {
		return;
	}

	this.makeVisualEditor( this.config );
	$( this.config.selector ).hide();
	$( this.config.selector ).attr( 'tabindex', '-1' );
};

/**
 *
 * @returns {string}
 */
bs.ui.widget.TextInputMWVisualEditor.prototype.getValue = function() {
	if ( !this.visualEditor ) {
		return this.currentValue;
	}
	var promise = this.visualEditor.getWikiText();
	var me = this;
	promise.done( function ( value ) {
		// keep track of actual value anyway to be on the safe side
		me.currentValue = value;
	} );
	return promise;
};

/**
 *
 * @param {string} value
 * @returns {undefined}
 */
bs.ui.widget.TextInputMWVisualEditor.prototype.setValue = function( value ) {
	if( !this.visualEditor ) {
		this.currentValue = value;
		return;
	}

	this.visualEditor.clearSurfaces();
	this.visualEditor.addSurface(
		ve.dm.converter.getModelFromDom(
			ve.createDocumentFromHtml( value )
		)
	);
};

bs.ui.widget.TextInputMWVisualEditor.prototype.makeVisualEditor = function( config ) {
	var me = this;
	config = config || me.config;

	this.loading = true;
		var modules = [
		'ext.bluespice.visualEditorConnector.standalone'
	].concat( this.bsVecConfig.StandalonePluginModules || [] );

	mw.loader.using( modules ).done( function() {
		me.emit( 'editorStartup', me );
		bs.vec.createEditor( config.id, {
			renderTo: config.selector,
			value: me.currentValue,
			format: config.format
		} ).done( function( target ){
			me.visualEditor = target;
			me.visualEditor.getSurface().getModel().on( 'history', me.onHistoryChange, [], me );
		} ).then( function() {
			me.emit( 'editorStartupComplete', me );
			me.loading = false;
		} );
	} );
};

bs.ui.widget.TextInputMWVisualEditor.prototype.onHistoryChange = function() {
	// we do not need to track the actual value, just that there was a change
	this.emit( 'change', this.currentValue );
};

bs.ui.widget.TextInputMWVisualEditor.prototype.getValidity = function() {
	// visual editor is always valid
	var $dfd = $.Deferred();
	$dfd.resolve();
	return $dfd.promise( this );
};
