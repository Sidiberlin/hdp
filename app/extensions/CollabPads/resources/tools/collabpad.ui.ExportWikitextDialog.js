/**
 * Dialog for exportWikitexting CollabTarget pages
 *
 * @class
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {boolean} forceDeleteSession Force session delete; InvalidChangeDialog passes this
 */
collabpad.ui.ExportWikitextDialog = function VeUiMwExportWikitextDialog( forceDeleteSession = false ) { // eslint-disable-line max-len
	// Initialization
	this.forceDeleteSession = forceDeleteSession;
	this.surface = ve.init.target.getSurface();

	// Parent constructor
	collabpad.ui.ExportWikitextDialog.super.call( this );
	this.$element.addClass( 'collab-ui-ExportWikitextDialog' );
};

/* Inheritance */
OO.inheritClass( collabpad.ui.ExportWikitextDialog, OO.ui.ProcessDialog );

/* Static Properties */
collabpad.ui.ExportWikitextDialog.static.name = 'mwExportWikitext';
collabpad.ui.ExportWikitextDialog.static.title = mw.msg( 'collabpads-save-dialog-title' );
collabpad.ui.ExportWikitextDialog.static.size = 'medium';
collabpad.ui.ExportWikitextDialog.static.actions = [
	{
		icon: 'close',
		action: 'close',
		flags: [ 'safe' ]
	},
	{
		label: mw.msg( 'collabpads-save-dialog-save-button-label' ),
		action: 'save-session',
		flags: [ 'primary', 'progressive' ]
	}
];

/**
 * @inheritdoc
 */
collabpad.ui.ExportWikitextDialog.prototype.initialize = function () {
	collabpad.ui.ExportWikitextDialog.super.prototype.initialize.call( this );

	const panel = new OO.ui.PanelLayout( {
		id: 'collabPads-ExportWikitextDialog-panel',
		padded: true,
		expanded: false
	} );

	const message = document.createElement( 'p' );
	message.innerText = mw.msg( 'collabpads-save-dialog-summary' );

	this.editSummaryInput = new ve.ui.MWEditSummaryWidget( {
		$overlay: this.$overlay,
		placeholder: ve.msg( 'visualeditor-editsummary' ),
		classes: [ 've-ui-mwSaveDialog-summary' ]
	} );

	const checkboxes = document.createElement( 'div' );
	checkboxes.id = 'collabPads-exportWikitextDialog-checkboxes';

	this.minorEditCheckbox = new OO.ui.CheckboxInputWidget();
	const minorEditLayout = new OO.ui.FieldLayout( this.minorEditCheckbox, {
		align: 'inline',
		label: mw.msg( 'collabpads-save-dialog-minor-edit' )
	} );

	this.watchPageCheckbox = new OO.ui.CheckboxInputWidget( { selected: true } );
	const watchPageLayout = new OO.ui.FieldLayout( this.watchPageCheckbox, {
		align: 'inline',
		label: mw.msg( 'collabpads-save-dialog-watch-page' )
	} );

	checkboxes.appendChild( minorEditLayout.$element[ 0 ] );
	checkboxes.appendChild( watchPageLayout.$element[ 0 ] );

	panel.$element.append(
		message,
		this.editSummaryInput.$element,
		checkboxes
	);

	this.$body.append( panel.$element );
};

collabpad.ui.ExportWikitextDialog.prototype.getActionProcess = function ( action ) {
	return collabpad.ui.ExportWikitextDialog.super.prototype.getActionProcess.call( this, action ).next( async function () {
		this.pushPending();
		if ( action === 'save-session' ) {
			try {
				await this.export();
				this.popPending();
				this.close();
			} catch ( error ) {
				console.error( 'CollabPads error: ' + error ); // eslint-disable-line no-console
				this.popPending();
				dfd.reject();
			}
		}
		if ( action === 'close' ) {
			this.popPending();
			this.close();
		}
	}, this );
};

/**
 * Save the page and delete the session if this is the last author
 */
collabpad.ui.ExportWikitextDialog.prototype.export = async function () {
	const fullPageName = mw.config.get( 'wgPageName' );
	const api = new mw.Api();
	const isMinorEdit = this.minorEditCheckbox.isSelected();
	const summaryInput = this.editSummaryInput.getValue();
	const shouldWatchPage = this.watchPageCheckbox.isSelected();
	const watchPageFunction = shouldWatchPage ? api.watch : api.unwatch;

	const wikitext = await ve.init.target.getWikitextFragment(
		this.surface.getModel().getDocument()
	);

	await api.postWithEditToken( {
		action: 'edit',
		title: fullPageName,
		text: wikitext,
		summary: summaryInput,
		minor: isMinorEdit
	} )
		.done( async ( data ) => {
			if ( !data.edit || !data.edit.newrevid ) {
				return;
			}

			const synchronizer = this.surface.getModel().synchronizer;
			const revisionId = data.edit.newrevid;
			const autorsSinceLastSave = synchronizer.getAuthorsSinceLastChange();
			if ( autorsSinceLastSave.length === 0 ) {
				autorsSinceLastSave.push( mw.user.getName() );
			}
			const collabApi = new collabpads.api.Api();
			const title = mw.Title.newFromText( fullPageName );
			await collabApi.recordParticipants(
				title.getNamespaceId(),
				title.getName(),
				revisionId,
				autorsSinceLastSave
			);
			synchronizer.clearAuthorsSinceLastChange();
		} );

	await watchPageFunction.call( api, fullPageName );
	ve.init.target.setDirty( false );
	this.surface.getModel().synchronizer.socket.emit( 'saveRevision' );

	mw.notify(
		mw.message( 'collabpads-save-complete-notif' ).plain(),
		{ type: 'success' }
	);

	const numberOfAuthors = Object.keys( this.surface.getModel().synchronizer.authors ).length;
	if ( numberOfAuthors === 1 || this.forceDeleteSession ) {
		this.deleteSessionRedirect( fullPageName, 1000 );
	}
};

/**
 * Delete session from DBs and redirect after delay
 *
 * @param {string} fullPageName
 * @param {number} delay
 */
collabpad.ui.ExportWikitextDialog.prototype.deleteSessionRedirect = function ( fullPageName, delay ) { // eslint-disable-line max-len
	const pageName = mw.config.get( 'wgTitle' );
	mw.loader.using( [ 'ext.collabpads.api' ] ).then( () => {
		const collabApi = new collabpads.api.Api();
		collabApi.deleteSession( mw.config.get( 'wgNamespaceNumber' ), pageName );
		this.surface.getModel().synchronizer.socket.emit( 'deleteSession' );
	} );

	setTimeout( () => {
		const pageUrl = `${ location.protocol }//${ location.host }${ location.pathname }?title=${ fullPageName }`;
		// Redirect user to view page
		location.href = pageUrl;
	}, delay );
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.ExportWikitextDialog );
