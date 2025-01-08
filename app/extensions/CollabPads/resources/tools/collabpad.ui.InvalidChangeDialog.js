window.collabpad = window.collabpad || {};
window.collabpad.ui = window.collabpad.ui || {};

/**
 * Dialog for Invalid Change
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {ve.dm.Surface} surface Surface model
 */
collabpad.ui.InvalidChangeDialog = function CollabpadUiMwInvalidChangeDialog( surface ) {
	// Initialization
	this.surface = surface;

	// Parent constructor
	collabpad.ui.InvalidChangeDialog.super.call( this );
	this.$element.addClass( 'collabpad-ui-InvalidChangeDialog' );
};

/* Inheritance */
OO.inheritClass( collabpad.ui.InvalidChangeDialog, OO.ui.MessageDialog );

/* Static Properties */
collabpad.ui.InvalidChangeDialog.static.name = 'InvalidChangeDialog';
collabpad.ui.InvalidChangeDialog.static.title = mw.msg( 'collabpads-invalid-change-dialog-title' );
collabpad.ui.InvalidChangeDialog.static.message = mw.msg( 'collabpads-invalid-change-dialog-message' );
collabpad.ui.InvalidChangeDialog.static.size = 'small';
collabpad.ui.InvalidChangeDialog.static.actions = [
	{
		label: mw.msg( 'collabpads-invalid-change-dialog-delete-button' ),
		action: 'delete',
		flags: [ 'destructive' ]
	},
	{
		label: mw.msg( 'collabpads-invalid-change-dialog-save-button' ),
		action: 'save',
		flags: [ 'primary', 'progressive' ]
	}
];

collabpad.ui.InvalidChangeDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'delete' ) {
		this.deleteSessionRedirect();
	}
	if ( action === 'save' ) {
		this.save();
	}
	return collabpad.ui.InvalidChangeDialog.super.prototype.getActionProcess.call(
		this, action
	);
};

/**
 * Delete session from DBs
 */
collabpad.ui.InvalidChangeDialog.prototype.deleteSessionRedirect = function () {
	const pageName = mw.config.get( 'wgTitle' );
	mw.loader.using( [ 'ext.collabpads.api' ] ).then( () => {
		const collabApi = new collabpads.api.Api();
		collabApi.deleteSession( mw.config.get( 'wgNamespaceNumber' ), pageName );
		this.surface.synchronizer.socket.emit( 'deleteSession' );
	} );
};

/**
 * Open ExportWikitextDialog
 */
collabpad.ui.InvalidChangeDialog.prototype.save = async function () {
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	const forceDeleteSession = true;
	const exportWikitextDialog = new collabpad.ui.ExportWikitextDialog( forceDeleteSession );
	windowManager.addWindows( [ exportWikitextDialog ] );
	windowManager.openWindow( exportWikitextDialog );
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.InvalidChangeDialog );
