StandardDialogs = StandardDialogs || {};
StandardDialogs.ui = StandardDialogs.ui || {};

StandardDialogs.ui.NewSubpageDialog = function StandardDialogsUiNewSubpageDialog( config ) {
	StandardDialogs.ui.NewSubpageDialog.super.call( this, config );
};
OO.inheritClass( StandardDialogs.ui.NewSubpageDialog, StandardDialogs.ui.BaseDialog );

StandardDialogs.ui.NewSubpageDialog.static.name = 'ext-standard-dialogs-new-subpage';

StandardDialogs.ui.NewSubpageDialog.prototype.makeSetupProcessData = function () {
	data = StandardDialogs.ui.NewSubpageDialog.super.prototype.makeSetupProcessData.call( this );
	data.title = mw.message( 'standarddialogs-new-subpage-title', this.getDialogTitlePageName() ).plain();

	return data;
};
StandardDialogs.ui.NewSubpageDialog.prototype.getFormItems = function () {
	this.mainInput = this.targetTitle = new OOJSPlus.ui.widget.TitleInputWidget( {
		id: this.elementId + '-tf-target',
		$overlay: this.$overlay,
		mustExist: false,
		prefix: this.pageName + '/',
		contentPagesOnly: false
	} );

	return [
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.FieldLayout( this.targetTitle, {
					label: mw.message( 'standarddialogs-new-subpage-label' ).plain(),
					align: 'top'
				} )
			]
		} )
	];
};

StandardDialogs.ui.NewSubpageDialog.prototype.makeDoneActionProcess = function () {
	this.newTitle = mw.Title.newFromText( this.pageName + '/' + this.targetTitle.getValue() );
	return new OO.ui.Process( function () {}, this );
};
StandardDialogs.ui.NewSubpageDialog.prototype.getActionCompletedEventArgs = function ( action ) {
	return [ this.newTitle ];
};
