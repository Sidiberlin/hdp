$( function () {
	const $container = $( '#contentProvisioning-overview' );
	if ( $container.length === 0 ) {
		return;
	}

	const panel = new contentProvisioning.ui.panel.ContentProvisioningOverview( {
		expanded: false
	} );

	$container.append( panel.$element );

	panel.connect( this, {
		sync: function ( pagePrefixedText ) {
			contentProvisioning._internal._getApi().done( ( api ) => { // eslint-disable-line no-underscore-dangle
				api.forceSync( pagePrefixedText ).done( function ( response ) {
					if ( !response.hasOwnProperty( 'success' ) ) {
						console.error( response.error ); // eslint-disable-line no-console
					}

					this.store.reload().done( ( data ) => { // eslint-disable-line no-unused-vars
						// Probably nothing to do here
					} );
				}.bind( panel ) ).fail( ( error ) => {
					OO.ui.alert( error );
				} );
			} );
		},
		diff: function ( pagePrefixedText ) {
			contentProvisioning._internal._getApi().done( ( api ) => { // eslint-disable-line no-underscore-dangle
				api.getDiff( pagePrefixedText ).done( ( response ) => {
					let diffHtml = response.diffHtml;

					if ( !response.hasOwnProperty( 'diffHtml' ) ) {
						if ( response.hasOwnProperty( 'error' ) ) {
							console.error( response.error ); // eslint-disable-line no-console
							diffHtml = response.error;
						} else {
							return;
						}
					}

					const windowManager = new OO.ui.WindowManager();
					$( document.body ).append( windowManager.$element );

					const dialog = new contentProvisioning.ui.dialog.ContentDiff( diffHtml );
					windowManager.addWindows( [ dialog ] );
					windowManager.openWindow( dialog );
				} ).fail( ( error ) => {
					OO.ui.alert( error );
				} );
			} );
		}
	} );
} );
