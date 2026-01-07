( function ( d ) {
	function setDestName() {
		const prefix = d.getElementById( 'mw-input-wpNSFR_Namespace' ).value;
		const destName = d.getElementById( 'wpDestFile' ).value;
		const destFileParts = [ destName ];
		if ( prefix !== '-' ) {
			destFileParts.unshift( prefix );
		}
		d.getElementById( 'mw-input-wpNSFR_DestFile' ).value = destFileParts.join( ':' );
	}
	d.addEventListener( 'DOMContentLoaded', () => {
		const namespaceInput = d.getElementById( 'mw-input-wpNSFR_Namespace' );
		const uploadFileInput = d.getElementById( 'wpUploadFile' );
		const destFileInput = d.getElementById( 'wpDestFile' );
		const uploadForm = d.getElementById( 'mw-upload-form' );
		if ( namespaceInput ) {
			namespaceInput.addEventListener( 'change', setDestName );
		}
		if ( uploadFileInput ) {
			uploadFileInput.addEventListener( 'change', () => {
				setTimeout( setDestName, 50 );
			} );
		}
		if ( destFileInput ) {
			destFileInput.addEventListener( 'change', setDestName );
			destFileInput.addEventListener( 'input', setDestName );
		}
		if ( uploadForm ) {
			uploadForm.addEventListener( 'submit', () => {
				setDestName();
				d.getElementById( 'wpDestFile' ).value = d.getElementById( 'mw-input-wpNSFR_DestFile' ).value;
			} );
		}
		setDestName();
	} );
}( document ) );
