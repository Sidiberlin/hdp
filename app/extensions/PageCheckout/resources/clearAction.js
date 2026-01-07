$( () => {
	$( document ).on( 'click', '#ca-pc_clear', ( e ) => {
		e.preventDefault();
		OO.ui.confirm( mw.msg( 'pagecheckout-ui-clear-confirm' ) ).done( ( confirmed ) => {
			if ( confirmed ) {
				new mw.Api().postWithToken( 'csrf', {
					action: 'pagecheckout-clear',
					page_title: mw.config.get( 'wgRelevantPageName' ) // eslint-disable-line camelcase
				} ).done( () => {
					window.location.reload();
				} );
			}
		} );
	} );
} );
