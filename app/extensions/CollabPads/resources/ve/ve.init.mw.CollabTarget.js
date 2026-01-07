/*!
 * VisualEditor MediaWiki Initialization CollabTarget class.
 *
 * @copyright 2011-2016 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

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
ve.init.mw.CollabTarget = function VeInitMwCollabTarget( title, rebaserUrl, config ) {
	config = config || {};
	config.toolbarConfig = Object.assign( {
		shadow: true,
		actions: true,
		floatable: true
	}, config.toolbarConfig );

	this.title = title;
	this.rebaserUrl = rebaserUrl;
	this.importTitle = config.importTitle || null;

	// Parent constructor
	ve.init.mw.CollabTarget.super.call( this, config );

	// eslint-disable-next-line no-jquery/no-global-selector
	this.$editableContent = $( '#mw-content-text' );

	// Initialization
	this.$element.addClass( 've-init-mw-articleTarget ve-init-mw-collabTarget' );
};

/* Inheritance */

OO.inheritClass( ve.init.mw.CollabTarget, ve.init.mw.Target );

/* Static Properties */

ve.init.mw.CollabTarget.static.name = 'collab';

ve.init.mw.CollabTarget.static.trackingName = 'collab';

ve.init.mw.CollabTarget.static.toolbarGroups =
	ve.copy( ve.init.mw.CollabTarget.static.toolbarGroups );

ve.init.mw.CollabTarget.static.importRules = ve.copy( ve.init.mw.CollabTarget.static.importRules );
ve.init.mw.CollabTarget.static.importRules.external.blacklist[ 'link/mwExternal' ] = false;

ve.init.mw.CollabTarget.static.actionGroups = [
	{
		name: 'help',
		include: [ 'help' ]
	},
	{
		name: 'pageMenu',
		type: 'list',
		icon: 'menu',
		indicator: null,
		title: OO.ui.deferMsg( 'visualeditor-pagemenu-tooltip' ),
		label: OO.ui.deferMsg( 'visualeditor-pagemenu-tooltip' ),
		invisibleLabel: true,
		include: [ 'collabpad-reset-changes', 'meta', 'categories', 'settings', 'advancedSettings', 'languages', 'templatesUsed', 'changeDirectionality', 'findAndReplace' ]
	},
	{
		name: 'authorList',
		include: [ 'collab-authorList' ]
	},
	{
		name: 'reset-changes',
		include: [ 'collabpad-reset-changes' ],
		classes: [ 'collabpad-reset-changes-button' ]
	},
	{
		name: 'leave',
		include: [ 'collabpad-leave' ],
		classes: [ 'collabpad-leave-button' ]
	},
	{
		name: 'export',
		include: [ 'collabpad-export' ]
	}
];

/* Methods */

/**
 * @inheritdoc
 */
ve.init.mw.CollabTarget.prototype.getSurfaceClasses = function () {
	const classes = ve.init.mw.CollabTarget.super.prototype.getSurfaceClasses.call( this );
	return classes.concat( [ 'mw-body-content' ] );
};

/**
 * @inheritdoc
 */
ve.init.mw.CollabTarget.prototype.getSurfaceConfig = function ( config ) {
	return ve.init.mw.CollabTarget.super.prototype.getSurfaceConfig.call( this, ve.extendObject( {
		nullSelectionOnBlur: false
	}, config ) );
};

/**
 * Page modifications after editor load.
 */
ve.init.mw.CollabTarget.prototype.transformPage = function () {
};

/**
 * Page modifications after editor teardown.
 */
ve.init.mw.CollabTarget.prototype.restorePage = function () {
};

/**
 * Get the title of the imported document, if there was one
 *
 * @return {mw.Title|null} Title of imported document
 */
ve.init.mw.CollabTarget.prototype.getImportTitle = function () {
	return this.importTitle;
};

/**
 * @inheritdoc
 * title: "asd"
 */
ve.init.mw.CollabTarget.prototype.getPageName = function () {
	return this.getImportTitle().toString();
};

/* Registration */

ve.init.mw.targetFactory.register( ve.init.mw.CollabTarget );

/**
 * Export tool
 */
ve.ui.MWExportTool = function VeUiMWExportTool() {
	// Parent constructor
	ve.ui.MWExportTool.super.apply( this, arguments );

	if ( OO.ui.isMobile() ) {
		this.setIcon( 'upload' );
		this.setTitle( null );
	}
};

OO.inheritClass( ve.ui.MWExportTool, ve.ui.Tool );
ve.ui.MWExportTool.static.name = 'collabpad-export';
ve.ui.MWExportTool.static.displayBothIconAndLabel = !OO.ui.isMobile();
ve.ui.MWExportTool.static.group = 'export';
ve.ui.MWExportTool.static.disabled = true;
ve.ui.MWExportTool.static.autoAddToCatchall = false;
ve.ui.MWExportTool.static.flags = [ 'progressive', 'primary' ];
ve.ui.MWExportTool.static.title =
	OO.ui.deferMsg( 'collabpads-toolbar-save-button-label' );
ve.ui.MWExportTool.static.label = 'collabpads-toolbar-save-button-label';
ve.ui.MWExportTool.static.commandName = 'mwExportWikitext';
ve.ui.toolFactory.register( ve.ui.MWExportTool );

ve.ui.MWExportTool.prototype.onUpdateState = async function () {
	const saveable = await ve.init.target.isSaveable();

	if ( saveable ) {
		this.setDisabled( false );
	} else {
		this.setDisabled( true );
	}
};

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'mwExportWikitext', 'window', 'open',
		{ args: [ 'mwExportWikitext' ] }
	)
);

/**
 * Leave session tool
 */
ve.ui.LeaveTool = function VeUiLeaveTool() {
	// Parent constructor

	ve.ui.LeaveTool.super.apply( this, arguments );
	if ( OO.ui.isMobile() ) {
		this.setIcon( 'logOut' );
		this.setTitle( null );
	}

};

OO.inheritClass( ve.ui.LeaveTool, OO.ui.PopupTool );
ve.ui.LeaveTool.static.name = 'collabpad-leave';
ve.ui.LeaveTool.static.displayBothIconAndLabel = false;
ve.ui.LeaveTool.static.flags = [ 'destructive' ];
ve.ui.LeaveTool.static.icon = 'logOut';
ve.ui.LeaveTool.static.title = OO.ui.deferMsg( 'collabpads-leave-tooltip' );
ve.ui.LeaveTool.static.autoAddToCatchall = false;

ve.ui.LeaveTool.prototype.onSelect = function () {
	const LeaveSessionDialog = new collabpad.ui.LeaveSessionDialog();
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ LeaveSessionDialog ] );
	windowManager.openWindow( LeaveSessionDialog );
};
ve.ui.LeaveTool.prototype.onUpdateState = function () {};
ve.ui.toolFactory.register( ve.ui.LeaveTool );

/**
 * Reset changes tool
 */
ve.ui.ResetChangesTool = function VeUiResetChangesTool() {
	// Parent constructor

	ve.ui.ResetChangesTool.super.apply( this, arguments );
	if ( OO.ui.isMobile() ) {
		this.setIcon( 'history' );
		this.setTitle( null );
	}

};

OO.inheritClass( ve.ui.ResetChangesTool, OO.ui.PopupTool );
ve.ui.ResetChangesTool.static.name = 'collabpad-reset-changes';
ve.ui.ResetChangesTool.static.displayBothIconAndLabel = false;
ve.ui.ResetChangesTool.static.flags = [ 'destructive' ];
ve.ui.ResetChangesTool.static.icon = 'history';
ve.ui.ResetChangesTool.static.title = OO.ui.deferMsg( 'collabpads-reset-changes-tooltip' );
ve.ui.ResetChangesTool.static.autoAddToCatchall = false;

ve.ui.ResetChangesTool.prototype.onSelect = function () {
	const ResetChangesDialog = new collabpad.ui.ResetChangesDialog();
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ ResetChangesDialog ] );
	windowManager.openWindow( ResetChangesDialog );
};
ve.ui.ResetChangesTool.prototype.onUpdateState = function () {};
ve.ui.toolFactory.register( ve.ui.ResetChangesTool );
