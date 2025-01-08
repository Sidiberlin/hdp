officeimport.ui.SelectFilePage = function ( name, cfg ) {
	officeimport.ui.SelectFilePage.parent.call( this, name, cfg );

	const supportedMimeTypes = mw.config.get( 'importOfficeFilesSupportedMimeTypes' );

	this.fileWidget = new OO.ui.SelectFileWidget( {
		name: 'upload',
		showDropTarget: true,
		accept: supportedMimeTypes
	} );

	this.fieldLayout = new OO.ui.FieldLayout( this.fileWidget, {
		label: mw.message( 'importofficefiles-ui-dialog-label-select-file' ).text(),
		align: 'top',
		classes: [ 'file-import-layout' ]
	} );

	this.fileWidget.connect( this, {
		change: function ( val ) {
			this.value = val;
			this.emit( 'fileSelected', val );
		}
	} );

	this.$element.append( this.fieldLayout.$element );
};

OO.inheritClass( officeimport.ui.SelectFilePage, OO.ui.PageLayout );

officeimport.ui.SelectFilePage.prototype.getData = function () {
	return this.value;
};

officeimport.ui.SelectFilePage.prototype.uploadFile = function ( file ) {
	const dfd = $.Deferred();

	mw.loader.using( [ 'ext.importofficefiles.api' ], function () {
		const api = new officeimport.api.Api();
		api.uploadFile( file ).done( function ( response ) {
			this.emit( 'uploadSuccess', response.uploadId, response.filename );
			dfd.resolve( response.uploadId, response.filename );
		}.bind( this ) ).fail( function ( error, xhr, type ) {
			this.emit( 'uploadFailed', error, xhr, type );
			dfd.reject( error, xhr, type );
		}.bind( this ) );
	}.bind( this ) );

	return dfd.promise();
};
