StandardDialogs = StandardDialogs || {};
StandardDialogs.ui = StandardDialogs.ui || {};

StandardDialogs.ui.MoveDialog = function StandardDialogsUiMoveDialog( config ) {
	StandardDialogs.ui.MoveDialog.super.call( this, config );
};
OO.inheritClass( StandardDialogs.ui.MoveDialog, StandardDialogs.ui.BaseDialog );

StandardDialogs.ui.MoveDialog.static.name = 'ext-standard-dialogs-move';

StandardDialogs.ui.MoveDialog.prototype.makeSetupProcessData = function () {
	data = StandardDialogs.ui.MoveDialog.super.prototype.makeSetupProcessData.call( this );
	data.title = mw.message( 'standarddialogs-move-title', this.getDialogTitlePageName() ).plain();

	return data;
};

StandardDialogs.ui.MoveDialog.prototype.getFormItems = function () {
	this.mainInput = this.targetTitle = new OOJSPlus.ui.widget.TitleInputWidget( {
		id: this.elementId + '-tf-target',
		value: this.getDialogTitlePageName(),
		$overlay: this.$overlay,
		mustExist: false,
		contentPagesOnly: false
	} );
	this.moveReasonText = new OO.ui.TextInputWidget( {} );
	this.moveLeaveRedirectCheckbox = new OO.ui.CheckboxInputWidget( {
		id: this.elementId + '-cb-redirect',
		value: 'redirect',
		selected: true
	} );
	this.moveSubpagesCheckbox = new OO.ui.CheckboxInputWidget( {
		id: this.elementId + '-cb-subpage',
		value: 'subpage',
		selected: true
	} );
	this.moveWatchCheckbox = new OO.ui.CheckboxInputWidget( {
		id: this.elementId + '-cb-unwatch',
		value: 'unwatch',
		selected: false
	} );

	return [
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.FieldLayout( this.targetTitle, {
					label: mw.message( 'newtitle' ).plain(),
					align: 'top'
				} ),
				new OO.ui.FieldLayout( this.moveReasonText, {
					label: mw.message( 'movereason' ).plain(),
					align: 'top'
				} ),
				new OO.ui.FieldLayout( this.moveLeaveRedirectCheckbox, {
					label: mw.message( 'move-leave-redirect' ).plain(),
					align: 'inline'
				} ),
				new OO.ui.FieldLayout( this.moveSubpagesCheckbox, {
					label: mw.message( 'move-subpages', 100 ).plain(),
					align: 'inline'
				} ),
				new OO.ui.FieldLayout( this.moveWatchCheckbox, {
					label: mw.message( 'move-watch' ).plain(),
					align: 'inline'
				} )
			]
		} )
	];
};

StandardDialogs.ui.MoveDialog.prototype.makeDoneActionProcess = function () {
	const dialog = this;

	this.newTitle = mw.Title.newFromText( dialog.targetTitle.getValue() );

	const dfd = new $.Deferred();
	mw.loader.using( 'mediawiki.api' ).done( function () {
		const mwApi = new mw.Api();
		const params = {
			action: 'move',
			from: dialog.pageName,
			to: dialog.targetTitle.getValue(),
			reason: dialog.moveReasonText.getValue(),
			movetalk: true
		};
		if ( dialog.moveSubpagesCheckbox.isSelected() ) {
			params.movesubpages = '1';
		}
		if ( dialog.moveLeaveRedirectCheckbox.isSelected() === false ) {
			params.noredirect = '1';
		}
		if ( dialog.moveWatchCheckbox.isSelected() ) {
			params.watchlist = 'watch';
		} else {
			params.watchlist = 'unwatch';
		}
		mwApi.postWithToken( 'csrf', params ).done( function ( data ) {
			dfd.resolve.apply( dialog, arguments );
		} ).fail( function () {
			dfd.reject.apply( dialog, [ new OO.ui.Error( arguments[ 0 ], { recoverable: false } ) ] );
		} );
	} ).fail( function () {
		dfd.reject.apply( dialog, arguments );
	} );

	return new OO.ui.Process( dfd.promise(), this );
};

StandardDialogs.ui.MoveDialog.prototype.getActionCompletedEventArgs = function ( action ) {
	return [ this.newTitle ];
};
