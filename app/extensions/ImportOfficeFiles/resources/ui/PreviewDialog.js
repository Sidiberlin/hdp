officeimport.ui.PreviewDialog = function ( cfg ) {
	officeimport.ui.PreviewDialog.super.call( this, cfg );
};

OO.inheritClass( officeimport.ui.PreviewDialog, OO.ui.ProcessDialog );

officeimport.ui.PreviewDialog.static.name = 'previewDialog';
officeimport.ui.PreviewDialog.static.title = 'Page preview';
officeimport.ui.PreviewDialog.static.actions = [
	{
		title: 'Cancel',
		icon: 'close',
		flags: 'safe'
	}
];

officeimport.ui.PreviewDialog.prototype.initialize = function () {
	officeimport.ui.PreviewDialog.super.prototype.initialize.apply( this, arguments );

	this.$headline = $( '<h2>' ).addClass( 'preview-headline' );
	this.$contentContainer = $( '<div>' ).addClass( 'preview-content-container' );

	this.$body.append( this.$contentContainer );
};

officeimport.ui.PreviewDialog.prototype.getSetupProcess = function ( data ) {
	this.$contentContainer.empty();

	this.$headline.html( data.title );
	this.$contentContainer.html( data.content );
	return officeimport.ui.PreviewDialog.parent.prototype.getSetupProcess.call( this, data );
};

officeimport.ui.PreviewDialog.prototype.getReadyProcess = function ( data ) {
	return officeimport.ui.PreviewDialog.parent.prototype.getReadyProcess.call( this, data );
};

officeimport.ui.PreviewDialog.prototype.getActionProcess = function ( action ) {
	return officeimport.ui.PreviewDialog.parent.prototype.getActionProcess.call( this, action );
};
