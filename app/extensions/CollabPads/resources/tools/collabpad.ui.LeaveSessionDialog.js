window.collabpad = window.collabpad || {};
window.collabpad.ui = window.collabpad.ui || {};

/**
 * Dialog for Leave
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} [config] Config options
 */
collabpad.ui.LeaveSessionDialog = function CollabpadUiMwLeaveSessionDialog( config ) {
	// Initialization
	this.surface = ve.init.target.getSurface();

	// Parent constructor
	collabpad.ui.LeaveSessionDialog.super.call( this, config );
	this.$element.addClass( 'collabpad-ui-LeaveSessionDialog' );
};

/* Inheritance */
OO.inheritClass( collabpad.ui.LeaveSessionDialog, OO.ui.MessageDialog );

/* Static Properties */
collabpad.ui.LeaveSessionDialog.static.name = 'LeaveSessionDialog';
collabpad.ui.LeaveSessionDialog.static.title = mw.msg( 'collabpads-leave-dialog-title' );
collabpad.ui.LeaveSessionDialog.static.message = mw.msg( 'collabpads-leave-dialog-message' );
collabpad.ui.LeaveSessionDialog.static.size = 'small';
collabpad.ui.LeaveSessionDialog.static.actions = [
	{
		label: mw.msg( 'collabpads-dialog-cancel-button' ),
		action: 'cancel',
		flags: [ 'safe' ]
	},
	{
		label: mw.msg( 'collabpads-leave-dialog-leave-button' ),
		action: 'leave-session',
		flags: [ 'primary', 'progressive' ]
	}
];

collabpad.ui.LeaveSessionDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'cancel' ) {
		this.close();
	}
	if ( action === 'leave-session' ) {
		this.leave();
	}
	return collabpad.ui.LeaveSessionDialog.super.prototype.getActionProcess.call(
		this, action
	);
};

/**
 * Leave the document edit mode and redirect user to page with view-mode
 */
collabpad.ui.LeaveSessionDialog.prototype.leave = function () {
	const fullPageName = mw.config.get( 'wgPageName' );
	const pageUrl = `${ location.protocol }//${ location.host }${ location.pathname }?title=${ fullPageName }`;
	// Redirect user to view page
	location.href = pageUrl;

	const numberOfAuthors = Object.keys( this.surface.getModel().synchronizer.authors ).length;
	if ( numberOfAuthors === 1 ) {
		// Last author to leave
		this.deleteSessionIfNoChanges( fullPageName );
	}
};

/**
 * Delete the session and DB entry if no changes made
 *
 * @param {string} fullPageName
 */
collabpad.ui.LeaveSessionDialog.prototype.deleteSessionIfNoChanges = function ( fullPageName ) {
	const pageName = mw.config.get( 'wgTitle' );
	const api = new mw.Api();
	api.get( {
		action: 'query',
		titles: fullPageName,
		prop: 'revisions',
		rvprop: 'content'
	} ).then( ( data ) => {
		const pages = data.query.pages;
		const pageId = Object.keys( pages )[ 0 ];
		const originalWikitext = pages[ pageId ].revisions[ 0 ][ '*' ];

		// Get the modified wikitext
		ve.init.target.getWikitextFragment( this.surface.getModel().getDocument() )
			.then( ( modifiedWikitext ) => {
				if ( originalWikitext.trim() === modifiedWikitext.trim() ) {
					// No changes
					mw.loader.using( [ 'ext.collabpads.api' ] ).then( () => {
						const collabApi = new collabpads.api.Api();
						collabApi.deleteSession( mw.config.get( 'wgNamespaceNumber' ), pageName );
						this.surface.getModel().synchronizer.socket.emit( 'deleteSession' );
					} );
				}
			} );
	} );
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.LeaveSessionDialog );
