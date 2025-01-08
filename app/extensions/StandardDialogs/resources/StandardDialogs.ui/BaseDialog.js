StandardDialogs = window.StandardDialogs || {};
StandardDialogs.ui = StandardDialogs.ui || {};

StandardDialogs.ui.BaseDialog = function StandardDialogsUiBaseDialog( config ) {
	StandardDialogs.ui.BaseDialog.super.call( this, config );
	this.pageName = config.pageName;
	this.mainInput = null;
};
OO.inheritClass( StandardDialogs.ui.BaseDialog, OO.ui.ProcessDialog );

StandardDialogs.ui.BaseDialog.prototype.getDialogTitlePageName = function () {
	return this.pageName.replace( '_', ' ' );
};

StandardDialogs.ui.BaseDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	const additionalData = this.makeSetupProcessData();
	data = $.extend( data, additionalData );
	return StandardDialogs.ui.BaseDialog.super.prototype.getSetupProcess.call( this, data );
};

StandardDialogs.ui.BaseDialog.prototype.initialize = function () {
	StandardDialogs.ui.BaseDialog.super.prototype.initialize.call( this );
	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true
	} );
	const formItems = this.getFormItems();
	this.content.$element.append(
		formItems.map( function ( item ) {
			return item.$element;
		} )
	);
	this.$body.append( this.content.$element );
};

StandardDialogs.ui.BaseDialog.prototype.getReadyProcess = function ( data ) {
	if ( this.mainInput ) {
		if ( this.mainInput.focus ) {
			this.mainInput.focus();
		}
		this.mainInput.connect( this, {
			enter: function () {
				this.executeAction( 'done' );
			}
		} );
	}
	return new OO.ui.Process( function () {} );
};

StandardDialogs.ui.BaseDialog.prototype.makeSetupProcessData = function () {
	return {
		actions: [
			{
				action: 'done',
				label: mw.message( 'standarddialogs-btn-done' ).plain(),
				flags: [ 'primary', 'progressive' ],
				id: this.elementId + '-btn-done'
			},
			{
				label: mw.message( 'standarddialogs-btn-cancel' ).plain(),
				flags: 'safe',
				id: this.elementId + '-btn-cancel'
			}
		]
	};
};

// Stub to be overwritten by subclass
StandardDialogs.ui.BaseDialog.prototype.getFormItems = function () {
	return [];
};

StandardDialogs.ui.BaseDialog.prototype.show = function () {
	if ( !this.windowManager ) {
		this.windowManager = new OO.ui.WindowManager( {
			modal: true
		} );
		$( document.body ).append( this.windowManager.$element );
		this.windowManager.addWindows( [ this ] );
	}

	this.windowManager.openWindow( this );
};

StandardDialogs.ui.BaseDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}

	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight + 10;
};

StandardDialogs.ui.BaseDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'done' ) {
		const doneActionProcess = this.makeDoneActionProcess();
		doneActionProcess.next( this.onActionDone, this );
		return doneActionProcess;
	}
	return StandardDialogs.ui.BaseDialog.super.prototype.getActionProcess.call( this, action );
};

// Stub to be overwritten by subclass
StandardDialogs.ui.BaseDialog.prototype.makeDoneActionProcess = function ( action ) {
	return new OO.ui.Process( ( function () {} ), this );
};

// Stub to be overwritten by subclass
StandardDialogs.ui.BaseDialog.prototype.onActionDone = function ( action ) {
	let args = [ 'actioncompleted' ];
	args = args.concat( this.getActionCompletedEventArgs() );
	this.emit.apply( this, args );
	this.close( { action: action } );
};

// Stub to be overwritten by subclass
StandardDialogs.ui.BaseDialog.prototype.getActionCompletedEventArgs = function ( action ) {
	return [];
};
