$( document ).on( 'click', '#enhanced-filelist-upload-file', function ( e ) {
	mw.loader.using( [ 'ext.enhancedUpload.upload.dialog' ] ).done( function () {
		var dialog = new enhancedUpload.ui.dialog.UploadFilesDialog();
		dialog.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

mw.hook( 'enhanced.filelist.action' ).add( function ( data ) {
	if ( data.action === 'reupload' ) {
		data.action = '';
		mw.loader.using( [ 'ext.enhancedUpload.singleupload.dialog' ] ).done( function () {
			var dialog = new enhancedUpload.ui.dialog.UploadSingleFileDialog( {
				destFilename: data.row.dbkey,
				dialogTitle: mw.message( 'enhancedupload-single-file-dlg-title', data.row.dbkey ).text()
			} );
			dialog.show();
		} );
	}
} );
