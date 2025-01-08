$( document ).on( 'click', '#ca-upload-file', function ( e ) {
	mw.loader.using( [ 'ext.enhancedUpload.upload.dialog' ] ).done( function () {
		var dialog = new enhancedUpload.ui.dialog.UploadFilesDialog();
		dialog.show();
	} );
	e.defaultPrevented = true;
	return false;
} );
