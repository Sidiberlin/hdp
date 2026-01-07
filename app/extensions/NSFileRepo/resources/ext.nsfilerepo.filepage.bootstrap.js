( function ( mw, $, d ) {
	$( d ).on( 'click', '#ca-move-file-namespace, .nsfr-move-file-namespace', function ( e ) {
		const me = this,
			config = mw.config.get( 'wgNSFRMoveFileNamespace' );
		if ( me.dialog ) {
			me.dialog.show();
		} else {
			mw.loader.using( [ 'ext.nsfilerepo.filepage' ] ).done( () => {
				me.dialog = new nsfr.ui.dialog.ChangeFileNamespaceAssociation( {
					unprefixedFileName: config.unprefixedFilename,
					currentNS: config.currentNamespace,
					excludeNS: config.excludeNS,
					formattedNamespaces: mw.config.get( 'wgFormattedNamespaces' ),
					currentPage: mw.config.get( 'wgPageName' )
				} );
				// eslint-disable-next-line no-unused-vars
				me.dialog.on( 'move-filepage-complete', ( oldPageName, newPageName ) => {
					window.location.reload();
				} );
				me.dialog.show();
			} );
		}

		e.defaultPrevented = true;
		return false;
	} );
}( mediaWiki, jQuery, document ) );
