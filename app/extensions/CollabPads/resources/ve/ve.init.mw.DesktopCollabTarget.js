/*!
 * VisualEditor MediaWiki Initialization DesktopCollabTarget class.
 *
 * @copyright 2011-2016 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */

/**
 * MediaWiki mobile article target.
 *
 * @class
 * @extends ve.init.mw.Target
 *
 * @constructor
 * @param {mw.Title} title Page sub-title
 * @param {string} rebaserUrl Rebaser server URL
 * @param {Object} [config] Configuration options
 * @cfg {mw.Title} [importTitle] Title to import
 */
ve.init.mw.DesktopCollabTarget = function VeInitMwDesktopCollabTarget( title, rebaserUrl, config ) {
	// Parent constructor
	ve.init.mw.DesktopCollabTarget.super.call( this, title, rebaserUrl, config );

	this.$originalContent = $( '<div>' ).addClass( 've-init-mw-desktopArticleTarget-originalContent' );
	this.$editableContent = $( '#mw-content-text' );
	this.originalWikitext = null;
	this.isDirty = false;

	// Initialization
	this.$element.addClass( 've-init-mw-desktopArticleTarget' ).append( this.$originalContent );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.DesktopCollabTarget, ve.init.mw.CollabTarget );

/* Methods */

/**
 * Page modifications after editor teardown.
 */
ve.init.mw.DesktopCollabTarget.prototype.restorePage = function () {
	this.$element.parent().append( this.$originalContent.children() );
	$( '#contentSub' ).empty();
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopCollabTarget.prototype.attachToolbar = function () {
	const toolbar = this.getToolbar();

	// Parent method
	ve.init.mw.DesktopCollabTarget.super.prototype.attachToolbar.apply( this, arguments );

	toolbar.$element.addClass(
		've-init-mw-desktopArticleTarget-toolbar ve-init-mw-desktopArticleTarget-toolbar-open ve-init-mw-desktopArticleTarget-toolbar-opened'
	);
	this.$element.prepend( toolbar.$element );
};

ve.init.mw.DesktopCollabTarget.prototype.setupToolbar = function () {
	ve.init.mw.DesktopCollabTarget.super.prototype.setupToolbar.apply( this, arguments );
	this.toolbarSaveButton = this.actionsToolbar.getToolGroupByName( 'export' ).items[ 0 ];
};

ve.init.mw.DesktopCollabTarget.prototype.setDirty = function ( value ) {
	this.isDirty = value;
	this.toolbarSaveButton.onUpdateState();
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopCollabTarget.prototype.setSurface = function ( surface ) {
	if ( surface !== this.surface ) {
		this.$editableContent.after( surface.$element );
	}

	// Parent method
	ve.init.mw.DesktopCollabTarget.super.prototype.setSurface.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.init.mw.DesktopCollabTarget.prototype.surfaceReady = function () {
	// Parent method
	ve.init.mw.DesktopCollabTarget.super.prototype.surfaceReady.apply( this, arguments );
	const surfaceModel = this.getSurface().getModel();
	surfaceModel.connect( this, {
		history: 'onHistoryChange'
	} );

	mw.hook( 've.activationComplete' ).fire();
	mw.hook( 've.collabpad.DropletsActivation' ).fire();
};

/**
 * Update the toolbar save button to reflect if the article can be saved
 */
ve.init.mw.DesktopCollabTarget.prototype.onHistoryChange = function () {
	this.setDirty( true );
};

/**
 * Check if the article can be saved based on changes
 *
 * @return {Promise<boolean>} A promise that resolves to whether the article can be saved
 */
ve.init.mw.DesktopCollabTarget.prototype.isSaveable = async function () {
	const surface = this.getSurface();
	if ( !surface ) {
		// Called before we're attached, so meaningless; abandon for now
		return false;
	}

	if ( !surface.getModel().hasBeenModified() ) {
		return false;
	}

	if ( this.isDirty ) {
		return true;
	}

	// Initial check
	const originalWikitext = await this.getOriginalWikitext();

	return this.hasWikitextChanged( originalWikitext );
};

/**
 * Get the original wikitext of the article
 *
 * @return {Promise<string>} A promise that resolves to the original wikitext
 */
ve.init.mw.DesktopCollabTarget.prototype.getOriginalWikitext = async function () {
	if ( this.originalWikitext !== null ) {
		return this.originalWikitext;
	}

	await this.updateOriginalWikitext();

	return this.originalWikitext;
};

/**
 * Update the original wikitext of the article
 *
 * @return {Promise<void>} A promise that resolves once the original wikitext is updated
 */
ve.init.mw.DesktopCollabTarget.prototype.updateOriginalWikitext = async function () {
	const api = new mw.Api();
	const fullPageName = mw.config.get( 'wgPageName' );
	const data = await api.get( {
		action: 'query',
		titles: fullPageName,
		prop: 'revisions',
		rvprop: 'content'
	} );

	const pages = data.query.pages;
	const pageId = Object.keys( pages )[ 0 ];
	this.originalWikitext = '';
	// Only set the original wikitext if the page has revisions
	if ( pages[ pageId ].revisions && pages[ pageId ].revisions[ 0 ] ) {
		this.originalWikitext = pages[ pageId ].revisions[ 0 ][ '*' ];
	}
};

/**
 * Check if the wikitext has changed from the original
 *
 * @param {string} originalWikitext The original wikitext to compare against
 * @return {Promise<boolean>} A promise that resolves to whether the wikitext has changed
 */
ve.init.mw.DesktopCollabTarget.prototype.hasWikitextChanged = async function ( originalWikitext ) {
	const modifiedWikitext = await this.getWikitextFragment(
		this.getSurface().getModel().getDocument()
	);

	if ( originalWikitext === modifiedWikitext.trim() ) {
		return false;
	}

	return true;
};

/* Registration */

ve.init.mw.targetFactory.register( ve.init.mw.DesktopCollabTarget );
