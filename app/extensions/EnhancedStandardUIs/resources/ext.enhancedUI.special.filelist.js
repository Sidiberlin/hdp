( function ( mw, $ ) {

	$( function () {
		require( './panel/FilelistPanel.js' );
		mw.user.getRights().done( function ( rights ) {
			/* eslint-disable-next-line no-jquery/no-global-selector */
			var $allFilesCnt = $( '#enhanced-ui-filelist-cnt' );
			var filesPanel = new ext.enhancedUI.panel.FilelistPanel( {
				rights: rights
			} );
			$( $allFilesCnt ).append( filesPanel.$element );
		} );
	} );

}( mediaWiki, jQuery ) );
