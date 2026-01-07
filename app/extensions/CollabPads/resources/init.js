( function ( mw, $, undefined ) { // eslint-disable-line no-unused-vars, no-shadow-restricted-names

	function startSession() {
		const configs = {
			pageNamespace: mw.config.get( 'wgNamespaceNumber' ),
			pageTitle: mw.config.get( 'wgTitle' )
		};
		const api = new collabpads.api.Api();

		api.setSessionByPageTitle( configs ).done( () => {
			mw.loader.using( [ 'ext.CollabPads.rebase' ] );
		} );
	}

	mw.loader.using( [ 'ext.collabpads.api' ] ).done( () => {
		if ( mw.user.options.get( 'collabPads-startSessionDialog-dontShowAgain' ) ) {
			startSession();
			return;
		}

		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		const startSessionDialogWindow = new collabpad.ui.StartSessionDialog();
		windowManager.addWindows( [ startSessionDialogWindow ] );
		windowManager.openWindow( startSessionDialogWindow );

		startSessionDialogWindow.on( 'actionCompleted', () => {
			startSession();
		} );
	} );
}( mediaWiki, jQuery ) );
