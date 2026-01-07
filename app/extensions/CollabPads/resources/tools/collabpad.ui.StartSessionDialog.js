window.collabpad = window.collabpad || {};
window.collabpad.ui = window.collabpad.ui || {};

const ACTION_CANCEL = 'cancel-session';
const ACTION_START = 'start-session';
const USER_OPTION = 'collabPads-startSessionDialog-dontShowAgain';

/**
 * Dialog for start collaborative session on page
 *
 * @class
 * @extends OO.ui.MessageDialog
 *
 * @constructor
 */
collabpad.ui.StartSessionDialog = function () {
	collabpad.ui.StartSessionDialog.super.call( this );
};

OO.inheritClass( collabpad.ui.StartSessionDialog, OO.ui.MessageDialog );

collabpad.ui.StartSessionDialog.static.name = 'StartSessionDialog';
collabpad.ui.StartSessionDialog.static.size = 'medium';
collabpad.ui.StartSessionDialog.static.padded = true;
collabpad.ui.StartSessionDialog.static.scrollable = false;
collabpad.ui.StartSessionDialog.static.actions = [
	{
		label: mw.msg( 'collabpads-dialog-cancel-button' ),
		action: ACTION_CANCEL,
		flags: 'safe'
	},
	{
		label: mw.msg( 'collabpads-start-label' ),
		action: ACTION_START,
		flags: [ 'primary', 'progressive' ]
	}
];

/**
 * Initialize the dialog
 */
collabpad.ui.StartSessionDialog.prototype.initialize = function () {
	collabpad.ui.StartSessionDialog.super.prototype.initialize.call( this );

	const panel = new OO.ui.PanelLayout( {
		id: 'collabPads-startSessionDialog-panel',
		padded: true,
		expanded: false
	} );

	const heading = document.createElement( 'h3' );
	heading.innerText = mw.msg( 'collabpads-start-dialog-title' );
	heading.className = 'collabPads-startSessionDialog-heading';

	const icon = document.createElement( 'img' );
	icon.src = mw.config.get( 'wgScriptPath' ) +
		'/extensions/CollabPads/resources/images/startSessionDialog-icon.svg';
	icon.className = 'collabPads-startSessionDialog-icon';

	const message = document.createElement( 'p' );
	message.innerText = mw.msg( 'collabpads-start-dialog-message' );
	message.className = 'collabPads-startSessionDialog-text';

	const showAgainCheckbox = new OO.ui.CheckboxInputWidget()
		.on( 'change', ( value ) => {
			const configValue = value ? '1' : '0';
			new mw.Api().saveOption( USER_OPTION, configValue );
			mw.user.options.set( USER_OPTION, configValue );
		} );
	const showAgainLayout = new OO.ui.FieldLayout( showAgainCheckbox, {
		align: 'inline',
		label: mw.msg( 'collabpads-start-dialog-show-again' ),
		classes: [ 'collabPads-startSessionDialog-text' ]
	} );

	panel.$element.append(
		heading,
		icon,
		message,
		showAgainLayout.$element
	);

	this.$body.append( panel.$element );
};

/**
 * Get the body height of the dialog
 *
 * @return {number} The height of the dialog body
 */
collabpad.ui.StartSessionDialog.prototype.getBodyHeight = function () {
	const $panel = $( '#collabPads-startSessionDialog-panel' ); // eslint-disable-line no-jquery/no-global-selector
	const height = $panel.height();
	return height ? height + 30 : 340;
};

/**
 * Get the process for the given action
 *
 * @param {string} action The action name
 * @return {OO.ui.Process} The process for the action
 */
collabpad.ui.StartSessionDialog.prototype.getActionProcess = function ( action ) {
	if ( action === ACTION_CANCEL ) {
		this.cancel();
	}
	if ( action === ACTION_START ) {
		return this.makeDoneActionProcess( action );
	}

	return collabpad.ui.StartSessionDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Redirect user to page in view mode
 */
collabpad.ui.StartSessionDialog.prototype.cancel = function () {
	const fullPageName = mw.config.get( 'wgPageName' );
	const pageUrl = `${ location.protocol }//${ location.host }${ location.pathname }?title=${ fullPageName }`;
	location.href = pageUrl;
};

/**
 * Make done action process for 'start-session'
 *
 * @param {string} action The action name
 * @return {OO.ui.Process} The process for the done action
 */
collabpad.ui.StartSessionDialog.prototype.makeDoneActionProcess = function ( action ) {
	const process = new OO.ui.Process();
	process.next( this.onActionDone.bind( this, action ) );
	return process;
};

/**
 * Handle the 'actionCompleted' event and close the dialog
 *
 * @param {string} action The action name
 */
collabpad.ui.StartSessionDialog.prototype.onActionDone = function ( action ) {
	const args = [ 'actionCompleted' ];
	this.emit.apply( this, args );
	this.close( { action: action } );
};

/* Registration */
ve.ui.windowFactory.register( collabpad.ui.StartSessionDialog );
