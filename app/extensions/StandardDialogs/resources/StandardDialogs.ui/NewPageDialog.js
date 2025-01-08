StandardDialogs = StandardDialogs || {};
StandardDialogs.ui = StandardDialogs.ui || {};

StandardDialogs.ui.NewPageDialog = function StandardDialogsUiNewPageDialog( config ) {
	StandardDialogs.ui.NewPageDialog.super.call( this, config );
};
OO.inheritClass( StandardDialogs.ui.NewPageDialog, StandardDialogs.ui.BaseDialog );

StandardDialogs.ui.NewPageDialog.static.name = 'ext-standard-dialogs-new-page';

StandardDialogs.ui.NewPageDialog.prototype.makeSetupProcessData = function () {
	data = StandardDialogs.ui.NewPageDialog.super.prototype.makeSetupProcessData.call( this );
	data.title = mw.message( 'standarddialogs-new-page-title' ).plain();

	return data;
};
StandardDialogs.ui.NewPageDialog.prototype.getFormItems = function () {
	const title = new mw.Title( this.pageName );
	let prefix = '';
	if ( title.getNamespaceId() > 0 ) {
		prefix = title.getNamespacePrefix();
	}
	this.mainInput = this.targetTitle = new OOJSPlus.ui.widget.TitleInputWidget( {
		id: this.elementId + '-tf-target',
		value: prefix,
		$overlay: this.$overlay,
		mustExist: false,
		contentPagesOnly: false
	} );

	return [
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.FieldLayout( this.targetTitle, {
					label: mw.message( 'standarddialogs-new-page-label' ).plain(),
					align: 'top'
				} )
			]
		} )
	];
};

StandardDialogs.ui.NewPageDialog.prototype.makeDoneActionProcess = function () {
	this.newTitle = mw.Title.newFromText( this.targetTitle.getValue() );
	return new OO.ui.Process( function () {}, this );
};
StandardDialogs.ui.NewPageDialog.prototype.getActionCompletedEventArgs = function ( action ) {
	return [ this.newTitle ];
};
