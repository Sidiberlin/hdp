/*!
 * VisualEditor DataModel SurfaceSynchronizer class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */
/* global io */

/**
 * DataModel surface synchronizer.
 *
 * @class
 * @mixes OO.EventEmitter
 * @mixes ve.dm.RebaseClient
 *
 * @constructor
 * @param {ve.dm.Surface} surface Surface model to synchronize
 * @param {string} documentId Document ID
 * @param {Object} [config] Configuration options
 * @cfg {string} [server] IO server
 * @cfg {string} [defaultName] Default username
 */
ve.dm.SurfaceSynchronizer = function VeDmSurfaceSynchronizer( surface, documentId, config ) {
	config = config || {};

	// Mixin constructors
	OO.EventEmitter.call( this );
	ve.dm.RebaseClient.call( this );

	// Properties
	this.surface = surface;
	this.doc = surface.documentModel;
	this.store = this.doc.getStore();
	this.authors = {};
	this.authorsSinceLastSave = [];
	this.authorSelections = {};
	this.documentId = documentId;

	this.silentSessionEnd = false;

	// Whether the document has been initialized
	this.initialized = false;
	// Whether we are currently synchronizing the model
	this.applying = false;
	this.token = null;
	this.serverId = null;
	this.loadSessionKey();
	this.paused = false;

	// SocketIO events
	const pathParts = config.server.split( '/' );
	const uri = pathParts[ 2 ];
	const path = '/' + pathParts.slice( 3 ).join( '/' );
	const options = {
		path: path,
		query: {
			docName: this.documentId,
			authorId: this.getAuthorId() || '',
			token: this.token || ''
		},
		transports: [ 'websocket' ]
	};
	this.socket = io( uri, options );
	this.socket.on( 'registered', this.onRegistered.bind( this ) );
	this.socket.on( 'initDoc', this.onInitDoc.bind( this ) );
	this.socket.on( 'newChange', this.onNewChange.bind( this ) );
	this.socket.on( 'authorChange', this.onAuthorChange.bind( this ) );
	this.socket.on( 'authorDisconnect', this.onAuthorDisconnect.bind( this ) );
	this.socket.on( 'alreadyLoggedIn', this.alreadyLogedIn.bind( this ) );
	this.socket.on( 'saveRevision', this.onSaveRevision.bind( this ) );
	this.socket.on( 'deleteSession', this.onDeleteSession.bind( this ) );

	this.authorData = ve.init.platform.sessionStorage.getObject( 've-collab-author' );
	if ( this.authorData ) {
		this.changeAuthor( this.authorData );
	} else if ( config.defaultName ) {
		this.changeAuthor( {
			name: config.defaultName,
			realName: config.realName
		} );
	}

	// Events
	this.surface.connect( this, {
		history: 'onSurfaceHistory',
		select: 'onSurfaceSelect'
	} );

	this.submitChangeThrottled = ve.debounce(
		ve.throttle( this.submitChange.bind( this ), 250 ), 0
	);
};

/* Inheritance */

OO.mixinClass( ve.dm.SurfaceSynchronizer, OO.EventEmitter );
OO.mixinClass( ve.dm.SurfaceSynchronizer, ve.dm.RebaseClient );

/* Events */

/**
 * @event authorSelect
 * @param {number} authorId The author whose selection has changed
 */

/**
 * @event authorChange
 * @param {number} authorId The author whose data has changed
 */

/**
 * @event wrongDoc
 */

/**
 * @event initDoc
 * @param {Error} Error if there was a problem initializing the document
 */

/**
 * @event disconnect
 */

/**
 * @event pause
 * The synchronizer is paused or resumes
 */

/* Methods */

/**
 * Destroy the synchronizer
 */
ve.dm.SurfaceSynchronizer.prototype.destroy = function () {
	this.socket.disconnect();
	this.doc.disconnect( this );
	this.surface.disconnect( this );
	this.initialized = false;
};

/**
 * Pause sending/receiving changes
 */
ve.dm.SurfaceSynchronizer.prototype.pauseChanges = function () {
	if ( this.paused ) {
		return;
	}
	this.paused = true;
	this.queuedChanges = [];
	this.emit( 'pause' );
};

/**
 * Resume sending/receiving changes
 */
ve.dm.SurfaceSynchronizer.prototype.resumeChanges = function () {
	let i;
	if ( !this.paused ) {
		return;
	}
	this.applying = true;
	try {
		// Don't cache length, as it's not inconceivable acceptChange could
		// cause another change to arrive in some weird setup
		for ( i = 0; i < this.queuedChanges.length; i++ ) {
			this.acceptChange( this.queuedChanges[ i ] );
		}
	} finally {
		this.applying = false;
	}
	this.paused = false;
	// Schedule submission of unsent local changes, if any
	this.submitChangeThrottled();
	this.emit( 'pause' );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.getChangeSince = function ( start, toSubmit ) {
	const change = this.doc.getChangeSince( start );
	const selection = this.surface.getSelection();
	if ( !selection.equals( this.lastSubmittedSelection ) ) {
		change.selections[ this.getAuthorId() ] = selection;
		if ( toSubmit ) {
			this.lastSubmittedSelection = selection;
		}
	}
	return change;
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.submitChange = function () {
	// Prevent submission before initialization is complete
	if ( !this.initialized ) {
		return;
	}
	// Parent method
	ve.dm.RebaseClient.prototype.submitChange.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.sendChange = function ( backtrack, change ) {
	/*
	 * serializedChange = change.serialize(true);
	 *
	 * It's an essential part of the implementation for troubleshooting problems
	 * related to the ve.dm.Change and issues with stores and transactions
	 */
	this.socket.emit( 'submitChange', {
		backtrack: this.backtrack,
		change: change
	} );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.applyChange = function ( change ) {
	let authorId;
	// Author selections are superseded by change.selections, so no need to translate them
	for ( authorId in change.selections ) {
		authorId = +authorId;
		delete this.authorSelections[ authorId ];
	}

	try {
		change.applyTo( this.surface );
	} catch ( e ) {
		console.error( e ); // eslint-disable-line no-console
		this.invalidChange();
		throw new Error( 'Invalid change' );
	}

	// HACK: After applyTo(), the selections are wrong and applying them could crash.
	// The only reason this doesn't happen is because
	// everything that tries to do that uses setTimeout().
	// Translate the selections that aren't going to be overwritten by change.selections
	try {
		this.applyNewSelections( this.authorSelections, change );
		// Apply the overwrites from change.selections
		this.applyNewSelections( change.selections );
	} catch ( e ) {
		console.error( e ); // eslint-disable-line no-console
		// Ignore - we will see how this affects usage in real life
	}
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.unapplyChange = function ( change ) {
	change.unapplyTo( this.surface );
	// Translate all selections for what we just unapplied
	// HACK: After unapplyTo(), the selections are wrong and applying them could crash.
	// The only reason this doesn't happen is because
	// everything that tries to do that uses setTimeout().
	this.applyNewSelections( this.authorSelections, change.reversed() );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.addToHistory = function ( change ) {
	change.addToHistory( this.doc );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.removeFromHistory = function ( change ) {
	change.removeFromHistory( this.doc );
};

/**
 * @inheritdoc
 */
ve.dm.SurfaceSynchronizer.prototype.logEvent = function ( event ) {
	if ( !this.initialized ) {
		// Do not log before initialization is complete; this prevents us from logging the entire
		// document history during initialization
		return;
	}
	this.socket.emit( 'logEvent', ve.extendObject( { sendTimestamp: Date.now() }, event ) );
};

/**
 * Respond to transactions happening on the document. Ignores transactions applied by
 * SurfaceSynchronizer itself.
 */
ve.dm.SurfaceSynchronizer.prototype.onSurfaceHistory = function () {
	if ( this.applying || !this.initialized || this.paused ) {
		// Ignore our own synchronization or initialization transactions
		return;
	}
	const change = this.getChangeSince( this.sentLength, true );
	const authorId = this.authorId;
	// HACK annotate transactions with authorship information
	// This relies on being able to access the transaction object by reference;
	// we should probably set the author deeper in dm.Surface or dm.Document instead.
	change.transactions.forEach( ( tx ) => {
		tx.authorId = authorId;
	} );
	// TODO deal with staged transactions somehow
	this.applyNewSelections( this.authorSelections, change );
	this.submitChangeThrottled();
	this.updateAuthorsSinceLastChange();
};

/**
 * Respond to selection changes.
 */
ve.dm.SurfaceSynchronizer.prototype.onSurfaceSelect = function () {
	if ( this.paused ) {
		return;
	}
	this.submitChangeThrottled();
};

/**
 * Translate incoming selections by change, then apply them and fire authorSelect
 *
 * @param {Object} newSelections Each author (key) maps to a new incoming ve.dm.Selection
 * @param {ve.dm.Change|ve.dm.Transaction} [changeOrTx] Object to translate over, if any
 * @fires authorSelect
 */
ve.dm.SurfaceSynchronizer.prototype.applyNewSelections = function ( newSelections, changeOrTx ) {
	let authorId;
	let translatedSelection;
	const change = changeOrTx instanceof ve.dm.Change ? changeOrTx : null;
	const tx = changeOrTx instanceof ve.dm.Transaction ? changeOrTx : null;
	for ( authorId in newSelections ) {
		authorId = +authorId;
		if ( authorId === this.authorId ) {
			continue;
		}
		if ( change ) {
			translatedSelection = newSelections[ authorId ].translateByChange( change, authorId );
		} else if ( tx ) {
			translatedSelection =
				newSelections[ authorId ].translateByTransactionWithAuthor( tx, authorId );
		} else {
			translatedSelection = newSelections[ authorId ];
		}
		if ( !translatedSelection.equals( this.authorSelections[ authorId ] ) ) {
			// This works correctly even if newSelections === this.authorSelections
			this.authorSelections[ authorId ] = translatedSelection;
			this.emit( 'authorSelect', authorId );
		}
	}
};

/**
 * Get author data object
 *
 * @param {number} [authorId] Author ID, defaults to current author
 * @return {Object} Author object, containing 'name' and 'color'
 */
ve.dm.SurfaceSynchronizer.prototype.getAuthorData = function ( authorId ) {
	if ( !authorId ) {
		authorId = this.getAuthorId();
	}
	// This is not very nice, as we are hotswapping the name and realName, but alternative is
	// Overriding ve.ce.Surface, so nope.
	// Needed because surface will use `name` for the label on the cursor
	// (line 5519: label: authorData.name)
	const data = Object.assign( {}, this.authors[ authorId ] );
	data.name = data.realName || data.name;
	return data;
};

/**
 * Handles author data changes
 *
 * @param {Object} data The data object containing author information
 * @param {boolean} [init=false] - Indicates if the function is called from onInitDoc
 */
ve.dm.SurfaceSynchronizer.prototype.onAuthorChange = function ( data, init = false ) {
	const authorId = data.authorId;
	const authorData = data.authorData;
	const currentAuthorId = this.getAuthorId();

	// Notify when a new author joins
	if ( !init && !this.authors[ authorId ] ) {
		const name = authorData.realName ? authorData.realName : authorData.name;
		const msg = mw.msg( 'collabpads-author-join', name, authorData.name );
		mw.notify( msg, { type: 'warn' } );
	}

	this.authors[ authorId ] = authorData;
	this.emit( 'authorChange', authorId );

	if ( authorId === currentAuthorId ) {
		ve.init.platform.sessionStorage.setObject( 've-collab-author', authorData );
	}
};

ve.dm.SurfaceSynchronizer.prototype.changeAuthor = function ( data ) {
	this.socket.emit( 'changeAuthor', ve.extendObject( {}, this.getAuthorData( this.getAuthorId() ), data ) );
};

ve.dm.SurfaceSynchronizer.prototype.onAuthorDisconnect = function ( authorId ) {
	const realName = this.authors[ authorId ].realName;
	const name = realName || this.authors[ authorId ].name;
	const msg = mw.msg( 'collabpads-author-leave', name, this.authors[ authorId ].name );
	mw.notify( msg, { type: 'warn' } );
	delete this.authors[ authorId ];
	delete this.authorSelections[ authorId ];
	this.emit( 'authorDisconnect', authorId );
};

ve.dm.SurfaceSynchronizer.prototype.alreadyLogedIn = function () {
	this.socket.disconnect();
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	const messageDialog = new OO.ui.MessageDialog();
	windowManager.addWindows( [ messageDialog ] );
	windowManager.openWindow( messageDialog, {
		title: mw.msg( 'collabpads-already-participating-title' ),
		message: mw.msg( 'collabpads-already-participating-message' ),
		actions: [
			{
				action: 'accept',
				label: mw.msg( 'collabpads-dialog-accept-button' ),
				flags: 'primary'
			}
		]
	} );

	// on closing redirect to view page
	const fullPageName = mw.config.get( 'wgPageName' );
	const pageUrl = `${ location.protocol }//${ location.host }${ location.pathname }?title=${ fullPageName }`;
	windowManager.on( 'closing', () => {
		window.location.href = pageUrl;
	} );
};

/**
 * Sends a notification when an author saves
 *
 * @param {number} authorId The Id of the author who saved
 */
ve.dm.SurfaceSynchronizer.prototype.onSaveRevision = function ( authorId ) {
	this.emit( 'saveRevision', authorId );
	this.clearAuthorsSinceLastChange();
	if ( this.authors[ authorId ] ) {
		const realName = this.authors[ authorId ].realName;
		const name = realName || this.authors[ authorId ].name;
		const msg = mw.msg( 'collabpads-author-save', name, this.authors[ authorId ].name );
		mw.notify( msg, { type: 'warn' } );
	}
};

/**
 * Respond to a "registered" event from the server
 *
 * @param {Object} data
 * @param {number} data.authorId The author ID allocated by the server
 * @param {string} data.token
 * @fires wrongDoc
 */
ve.dm.SurfaceSynchronizer.prototype.onRegistered = function ( data ) {
	if ( this.serverId && this.serverId !== data.serverId ) {
		this.socket.disconnect();
		this.emit( 'wrongDoc' );
		return;
	}
	this.serverId = data.serverId;
	this.setAuthorId( data.authorId );
	this.surface.setAuthorId( this.authorId );
	this.token = data.token;
	this.saveSessionKey();
};

ve.dm.SurfaceSynchronizer.prototype.saveSessionKey = function () {
	ve.init.platform.sessionStorage.setObject( 'visualeditor-session-key', {
		serverId: this.serverId,
		docName: this.documentId,
		authorId: this.getAuthorId(),
		token: this.token
	} );
};

ve.dm.SurfaceSynchronizer.prototype.loadSessionKey = function () {
	const data = ve.init.platform.sessionStorage.getObject( 'visualeditor-session-key' );
	if ( data && data.docName === this.documentId ) {
		this.serverId = data.serverId;
		this.setAuthorId( data.authorId );
		this.token = data.token;
	}
};

/**
 * Respond to an initDoc event from the server, catching us up on the prior history of the document.
 *
 * @param {Object} data
 * @param {Object} data.history Serialized change representing the server's history
 * @param {Object} data.authors Object mapping author IDs to author data objects (name/color)
 * @fires initDoc
 */
ve.dm.SurfaceSynchronizer.prototype.onInitDoc = function ( data ) {
	let authorId;
	if ( this.initialized ) {
		// Ignore attempt to initialize a second time
		return;
	}
	for ( authorId in data.authors ) {
		this.onAuthorChange( {
			authorId: +authorId,
			authorData: data.authors[ authorId ]
		}, true
		);
	}
	try {
		const history = ve.dm.Change.static.unsafeDeserialize( data.history );
		this.acceptChange( history );
	} catch ( e ) {
		this.socket.disconnect();
		this.emit( 'initDoc', e );
		return;
	}
	this.emit( 'initDoc' );

	// After the init process is done, the history is added as a single change and can be undone.
	// Cleaning undoStack prevents this bug!
	this.surface.undoStack = [];

	// Mark ourselves as initialized and retry any prevented submissions
	this.initialized = true;
	this.submitChangeThrottled();
	this.updateAuthorsSinceLastChange();
};

/**
 * Respond to a newChange event from the server, signalling a newly committed change
 *
 * If the commited change is by another author, then:
 * - Rebase uncommitted changes over the committed change
 * - If there is a rebase rejection, then apply its inverse to the document
 * - Apply the rebase-transposed committed change to the document
 * - Rewrite history to have the committed change followed by rebased uncommitted changes
 *
 * If the committed change is by the local author, then it is already applied to the document
 * and at the correct point in the history: just move the commit pointer.
 *
 * @param {Object} serializedChange Serialized ve.dm.Change that the server has applied
 */
ve.dm.SurfaceSynchronizer.prototype.onNewChange = function ( serializedChange ) {
	const change = ve.dm.Change.static.deserialize( serializedChange );
	if ( this.paused ) {
		this.queuedChanges.push( change );
		return;
	}
	// Make sure we don't attempt to submit any of the transactions we commit while manipulating
	// the state of the document
	this.applying = true;
	try {
		this.acceptChange( change );
	} finally {
		this.applying = false;
	}
	// Schedule submission of unsent local changes, if any
	this.submitChangeThrottled();
	if ( change.transactions && change.transactions.length ) {
		this.updateAuthorsSinceLastChange();
	}
};

ve.dm.SurfaceSynchronizer.prototype.onDisconnect = function () {
	this.initialized = false;
	this.emit( 'disconnect' );
};

ve.dm.SurfaceSynchronizer.prototype.updateAuthorsSinceLastChange = function () {
	for ( const authorId in this.authors ) {
		const authorName = this.authors[ authorId ].name;
		if ( this.authorsSinceLastSave.indexOf( authorName ) === -1 ) {
			this.authorsSinceLastSave.push( authorName );
		}
	}
};

ve.dm.SurfaceSynchronizer.prototype.getAuthorsSinceLastChange = function () {
	return this.authorsSinceLastSave;
};

ve.dm.SurfaceSynchronizer.prototype.clearAuthorsSinceLastChange = function () {
	this.authorsSinceLastSave = [];
};

ve.dm.SurfaceSynchronizer.prototype.invalidChange = function () {
	this.openDialog( new collabpad.ui.InvalidChangeDialog( this.surface ) );
};

ve.dm.SurfaceSynchronizer.prototype.initFailed = function () {
	const pageName = mw.config.get( 'wgTitle' );
	mw.loader.using( [ 'ext.collabpads.api' ] ).then( () => {
		const collabApi = new collabpads.api.Api();
		collabApi.deleteSession( mw.config.get( 'wgNamespaceNumber' ), pageName );
		this.silentSessionEnd = true;
		this.socket.emit( 'deleteSession' );
		this.openDialog( new collabpad.ui.InvalidInitializationDialog( this.surface ) );
	} );

};

ve.dm.SurfaceSynchronizer.prototype.openDialog = function ( dialog ) {
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ dialog ] );
	windowManager.openWindow( dialog );
};

ve.dm.SurfaceSynchronizer.prototype.onDeleteSession = function () {
	this.socket.disconnect();
	if ( this.silentSessionEnd ) {
		return;
	}
	const windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	const sessionEndedDialog = new OO.ui.MessageDialog();
	windowManager.addWindows( [ sessionEndedDialog ] );
	windowManager.openWindow( sessionEndedDialog, {
		title: mw.msg( 'collabpads-session-ended-title' ),
		message: mw.msg( 'collabpads-session-ended-message' ),
		actions: [
			{
				action: 'accept',
				label: mw.msg( 'collabpads-dialog-accept-button' ),
				flags: 'primary'
			}
		]
	} );

	// on closing redirect to view page
	const fullPageName = mw.config.get( 'wgPageName' );
	const pageUrl = `${ location.protocol }//${ location.host }${ location.pathname }?title=${ fullPageName }`;
	windowManager.on( 'closing', () => {
		window.location.href = pageUrl;
	} );
};
