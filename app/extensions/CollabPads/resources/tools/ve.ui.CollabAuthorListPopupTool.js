/*!
 * VisualEditor UserInterface AuthorListPopupTool class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * UserInterface AuthorListPopupTool
 *
 * @class
 * @extends OO.ui.PopupTool
 *
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config]
 */
ve.ui.CollabAuthorListPopupTool = function VeUiCollabAuthorListPopupTool( toolGroup, config ) {
	this.$authorList = $( '<div>' );
	this.defaultColor = getComputedStyle( document.documentElement )
		.getPropertyValue( '--default-color' ).replace( '#', '' );
	// Parent constructor
	ve.ui.CollabAuthorListPopupTool.super.call( this, toolGroup, ve.extendObject( {
		popup: {
			classes: [ 've-ui-authorListWidget-listPopup' ],
			$content: this.$authorList,
			padded: true,
			align: 'center'
		}
	}, config ) );
	// Events
	this.toolbar.connect( this, { surfaceChange: 'onSurfaceChange' } );
};

/* Inheritance */

OO.inheritClass( ve.ui.CollabAuthorListPopupTool, OO.ui.PopupTool );

/* Methods */

/**
 * Handle surfaceChange event fromt the toolbar
 *
 * @param {ve.dm.Surface|null} oldSurface Old surface
 * @param {ve.dm.Surface|null} newSurface New surface
 */
ve.ui.CollabAuthorListPopupTool.prototype.onSurfaceChange = function ( oldSurface, newSurface ) {
	// TODO: Disconnect oldSurface.
	// Currently in the CollabTarget life-cycle the surface is never changed.
	this.setup( newSurface );
};

/**
 * @inheritdoc
 */
// eslint-disable-next-line no-unused-vars
ve.ui.CollabAuthorListPopupTool.prototype.onPopupToggle = function ( visible ) {
	// Parent method
	ve.ui.CollabAuthorListPopupTool.super.prototype.onPopupToggle.apply( this, arguments );
};

/**
 * Setup the popup which a specific surface
 *
 * @param {ve.ui.Surface} surface Surface
 */
ve.ui.CollabAuthorListPopupTool.prototype.setup = function ( surface ) {
	this.synchronizer = surface.getModel().synchronizer;
	this.authorItems = {};

	this.surface = surface;

	if ( !this.synchronizer ) {
		this.setDisabled( true );
		return;
	}

	// TODO: Unbind from an existing surface if one is set
	this.changeNameDebounced = ve.debounce( this.changeName.bind( this ), 250 );

	// Copy link
	const copyLink = this.createCopyLink();
	const line = document.createElement( 'hr' );
	this.$authorList.append( line, copyLink );

	// Participants
	const heading = document.createElement( 'h6' );
	heading.className = 'collabpads-participants-heading';
	heading.innerText = mw.msg( 'collabpads-participants-label' );

	this.selfItem = new collabpad.ui.CollabAuthorItemWidget(
		this.synchronizer,
		this.popup.$element,
		{ editable: true, authorId: this.synchronizer.getAuthorId() }
	);

	this.$authorList.prepend( heading, this.selfItem.$element );

	this.selfItem.connect( this, {
		changeColor: 'onSelfItemChangeColor'
	} );
	this.synchronizer.connect( this, {
		authorChange: 'onSynchronizerAuthorUpdate',
		authorDisconnect: 'onSynchronizerAuthorDisconnect'
	} );

	let authorId;
	for ( authorId in this.synchronizer.authors ) {
		this.onSynchronizerAuthorUpdate( +authorId );
	}
};

/**
 * Create and setup the copy link element.
 *
 * @return {HTMLElement} The copy link element.
 */
ve.ui.CollabAuthorListPopupTool.prototype.createCopyLink = function () {
	const copyLink = document.createElement( 'a' );
	copyLink.href = '#';
	copyLink.className = 'collabpads-copy-link';

	const copyIcon = document.createElement( 'span' );
	copyIcon.className = 'oo-ui-iconElement-icon oo-ui-icon-copy';

	const copyText = document.createElement( 'span' );
	copyText.textContent = mw.msg( 'collabpads-copylink-label' );

	copyLink.append( copyIcon, copyText );

	copyLink.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		// Copy URL to clipboard
		navigator.clipboard.writeText( window.location.href ).then( // eslint-disable-line compat/compat, max-len
			() => {
				const msg = mw.msg( 'collabpads-clipboard-copy-success' );
				mw.notify( msg, { type: 'info' } );
			},
			() => {
				const msg = mw.msg( 'collabpads-clipboard-copy-fail' );
				mw.notify( msg, { type: 'error' } );
			}
		);
	} );

	return copyLink;
};

/**
 * Handle change events from the user's authorItem
 *
 * @param {string} value
 */
ve.ui.CollabAuthorListPopupTool.prototype.onSelfItemChange = function () {
	this.changeNameDebounced();
};

/**
 * Handle change color events from the user's authorItem
 *
 * @param {string} color
 */
ve.ui.CollabAuthorListPopupTool.prototype.onSelfItemChangeColor = function ( color ) {
	this.synchronizer.changeAuthor( { color: color } );
};

/**
 * Notify the server of a name change
 */
ve.ui.CollabAuthorListPopupTool.prototype.changeName = function () {
	this.synchronizer.changeAuthor( { name: this.selfItem.getName() } );
};

/**
 * Update the user count
 */
ve.ui.CollabAuthorListPopupTool.prototype.updateAuthorCount = function () {
	this.setTitle( ( Object.keys( this.authorItems ).length + 1 ).toString() );
};

/**
 * Called when the synchronizer receives a remote author selection or name change
 *
 * @param {number} authorId The author ID
 */
ve.ui.CollabAuthorListPopupTool.prototype.onSynchronizerAuthorUpdate = function ( authorId ) {
	if ( authorId === this.synchronizer.getAuthorId() ) {
		this.selfItem.update();
		return;
	}

	let authorItem = this.authorItems[ authorId ];
	if ( !authorItem ) {
		authorItem = new collabpad.ui.CollabAuthorItemWidget(
			this.synchronizer, this.popup.$element, { authorId: authorId }
		);
		this.authorItems[ authorId ] = authorItem;
		this.updateAuthorCount();
		const $children = this.$authorList.children();
		authorItem.$element.insertBefore( $children.eq( -2 ) );
	} else {
		authorItem.update();
	}
};

/**
 * Called when the synchronizer receives a remote author disconnect
 *
 * @param {number} authorId The author ID
 */
ve.ui.CollabAuthorListPopupTool.prototype.onSynchronizerAuthorDisconnect = function ( authorId ) {
	const authorItem = this.authorItems[ authorId ];
	if ( authorItem ) {
		authorItem.$element.remove();
		delete this.authorItems[ authorId ];
		this.updateAuthorCount();
	}
};

/* Static Properties */
ve.ui.CollabAuthorListPopupTool.static.name = 'collab-authorList';
ve.ui.CollabAuthorListPopupTool.static.group = 'utility';
ve.ui.CollabAuthorListPopupTool.static.icon = 'userAvatar';
ve.ui.CollabAuthorListPopupTool.static.title = '1';
ve.ui.CollabAuthorListPopupTool.static.autoAddToCatchall = false;
ve.ui.CollabAuthorListPopupTool.static.autoAddToGroup = false;
ve.ui.CollabAuthorListPopupTool.static.displayBothIconAndLabel = true;

/* Registration */
ve.ui.toolFactory.register( ve.ui.CollabAuthorListPopupTool );
