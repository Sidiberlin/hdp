$( function() {
	$( document ).on( 'click', '#ca-pc_clear', function( e ) {
		e.preventDefault();
		OO.ui.confirm( mw.msg( 'pagecheckout-ui-clear-confirm' ) ).done( function ( confirmed ) {
			if ( confirmed ) {
				new mw.Api().postWithToken( 'csrf', {
					action: 'pagecheckout-clear',
					page_title: mw.config.get( 'wgRelevantPageName' )
				} ).done( function() {
					window.location.reload();
				} );
			}
		} );
	} );
} );
