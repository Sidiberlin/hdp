officeimport.ui.ImportBooklet = function ( cfg ) {
	officeimport.ui.ImportBooklet.super.call( this, cfg );
	this.$overlay = cfg.$overlay;
	this.makePages();
};

OO.inheritClass( officeimport.ui.ImportBooklet, OO.ui.BookletLayout );

officeimport.ui.ImportBooklet.prototype.makePages = function () {
	this.pages = [
		new officeimport.ui.SelectFilePage( 'SelectFile', {
			expanded: false
		} ),
		new officeimport.ui.ConfigurationPage( 'Configuration', {
			expanded: false,
			$overlay: this.$overlay
		} ),
		new officeimport.ui.StructurePreviewPage( 'StructurePreview', {
			expanded: false
		} ),
		new officeimport.ui.ImportProgressPage( 'ImportProgress', {
			expanded: false
		} )
	];

	this.pagesOrder = [
		'SelectFile',
		'Configuration',
		'StructurePreview',
		'ImportProgress'
	];

	this.addPages( this.pages );
};
