$( function() {
	var $container = $( '#contentProvisioning-overview' );
	if ( $container.length === 0 ) {
		return;
	}

	var panel = new contentProvisioning.ui.panel.ContentProvisioningOverview( {
		expanded: false
	} );

	$container.append( panel.$element );

	panel.connect( this, {
		sync: function( pagePrefixedText ) {
			contentProvisioning._internal._getApi().done( function( api ) {
				api.forceSync( pagePrefixedText ).done( function( response ) {
					if ( !response.hasOwnProperty( 'success' ) ) {
						console.log( response.error );
					}

					this.store.reload().done( function( data ) {
						// Probably nothing to do here
					}.bind( this ) );
				}.bind( panel ) ).fail( function( error ) {
					OO.ui.alert( error );
				} );
			} );
		},
		diff: function( pagePrefixedText ) {
			contentProvisioning._internal._getApi().done( function( api ) {
				api.getDiff( pagePrefixedText ).done( function( response ) {
					var diffHtml = response.diffHtml;

					if ( !response.hasOwnProperty( 'diffHtml' ) ) {
						if ( response.hasOwnProperty( 'error' ) ) {
							console.log( response.error );
							diffHtml = response.error;
						} else {
							return;
						}
					}

					var windowManager = new OO.ui.WindowManager();
					$( document.body ).append( windowManager.$element );

					var dialog = new contentProvisioning.ui.dialog.ContentDiff( diffHtml );
					windowManager.addWindows( [ dialog ] );
					windowManager.openWindow( dialog );
				} ).fail( function( error ) {
					OO.ui.alert( error );
				} );
			} );
		}
	} )
} );
