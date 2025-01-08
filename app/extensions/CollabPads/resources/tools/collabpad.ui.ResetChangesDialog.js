window.collabpad = window.collabpad || {};
window.collabpad.ui = window.collabpad.ui || {};

/**
 * Dialog for Reset Changes
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} [config] Config options
 */
collabpad.ui.ResetChangesDialog = function CollabpadUiMwResetChangesDialog( config ) {
	// Initialization
	this.surface = ve.init.target.getSurface();

	// Parent constructor
	collabpad.ui.ResetChangesDialog.super.call( this, config );
	this.$element.addClass( 'collabpad-ui-ResetChangesDialog' );
};

/* Inheritance */
OO.inheritClass( collabpad.ui.ResetChangesDialog, OO.ui.MessageDialog );

/* Static Properties */
collabpad.ui.ResetChangesDialog.static.name = 'ResetChangesDialog';
collabpad.ui.ResetChangesDialog.static.title = mw.msg( 'collabpads-reset-changes-dialog-title' );
collabpad.ui.ResetChangesDialog.static.message = mw.msg( 'collabpads-reset-changes-dialog-message' );
collabpad.ui.ResetChangesDialog.static.size = 'small';
collabpad.ui.ResetChangesDialog.static.actions = [
	{
		label: mw.msg( 'collabpads-dialog-cancel-button' ),
		action: 'cancel',
		flags: [ 'safe' ]
	},
	{
		label: mw.msg( 'collabpads-reset-changes-dialog-reset-changes-button' ),
		action: 'reset-changes',
		flags: [ 'primary', 'progressive' ]
	}
];

collabpad.ui.ResetChangesDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'cancel' ) {
		this.close();
	}
	if ( action === 'reset-changes' ) {
		this.resetChanges();
	}
	return collabpad.ui.ResetChangesDialog.super.prototype.getActionProcess.call(
		this, action
	);
};
/**
 * Revert to the last saved version of the page
 */
collabpad.ui.ResetChangesDialog.prototype.resetChanges = function () {
	const surfaceModel = this.surface.getModel();
	const document = surfaceModel.getDocument();
	const api = new mw.Api();
	const fullPageName = mw.config.get( 'wgPageName' );

	// Get last saved wikitext
	api.get( {
		action: 'query',
		titles: fullPageName,
		prop: 'revisions',
		rvprop: 'content'
	} ).then( ( data ) => {
		const pages = data.query.pages;
		const pageId = Object.keys( pages )[ 0 ];
		const originalWikitext = pages[ pageId ].revisions[ 0 ][ '*' ];

		// Fetch HTML from Parsoid
		api.post( {
			action: 'visualeditor',
			paction: 'parse',
			page: fullPageName,
			wikitext: originalWikitext,
			pst: true
		} ).then( ( response ) => {
			const htmlFromParsoid = response.visualeditor.content;

			// Convert HTML from Parsoid to ve.dm.Document
			const newDocument = ve.dm.converter.getModelFromDom(
				ve.createDocumentFromHtml( htmlFromParsoid )
			);

			// Remove the current content
			const removeTransaction = ve.dm.TransactionBuilder.static.newFromRemoval(
				document,
				document.getDocumentRange()
			);
			surfaceModel.change( removeTransaction );

			// Insert new document
			const insertTransaction = ve.dm.TransactionBuilder.static.newFromDocumentInsertion(
				document,
				0,
				newDocument
			);
			surfaceModel.change( insertTransaction );
			ve.init.target.setDirty( false );
		} );
	} );
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.ResetChangesDialog );
