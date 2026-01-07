window.collabpad = window.collabpad || {};
window.collabpad.ui = window.collabpad.ui || {};

/**
 * Dialog for initialization error
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {ve.dm.Surface} surface Surface model
 */
collabpad.ui.InvalidInitializationDialog = function ( surface ) {
	// Initialization
	this.surface = surface;

	// Parent constructor
	collabpad.ui.InvalidChangeDialog.super.call( this );
	this.$element.addClass( 'collabpad-ui-InvalidChangeDialog' );
};

/* Inheritance */
OO.inheritClass( collabpad.ui.InvalidInitializationDialog, collabpad.ui.InvalidChangeDialog );

/* Static Properties */
collabpad.ui.InvalidInitializationDialog.static.name = 'InvalidInitializationDialog';
collabpad.ui.InvalidInitializationDialog.static.title = mw.msg( 'collabpads-invalid-initialization-dialog-title' );
collabpad.ui.InvalidInitializationDialog.static.message = mw.msg( 'collabpads-invalid-initialization-dialog-message' );
collabpad.ui.InvalidInitializationDialog.static.size = 'medium';
collabpad.ui.InvalidInitializationDialog.static.actions = [
	{
		label: mw.msg( 'collabpads-leave-dialog-leave-button' ),
		action: 'leave'
	}
];

collabpad.ui.InvalidInitializationDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'leave' ) {
		const title = mw.Title.newFromText( mw.config.get( 'wgPageName' ) );
		window.location.href = title.getUrl();
	}
	return collabpad.ui.InvalidInitializationDialog.super.prototype.getActionProcess.call(
		this, action
	);
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.InvalidInitializationDialog );
