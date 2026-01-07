$( () => {
	const containerIds = [ '#collabpadsessions-grid', '#collabpadsessions-admin-grid' ];
	const $container = $( containerIds.join( ', ' ) );

	if ( $container.length === 0 ) {
		return;
	}

	// admin = all sessions
	// else only sessions owned by user
	const sessionOwner =
		( $container.attr( 'id' ) === 'collabpadsessions-admin-grid' ) ?
			'*' :
			mw.config.get( 'wgUserName' );
	const panel = new collabpad.panel.SessionList( {
		expanded: false,
		sessionOwner: sessionOwner
	} );

	$container.append( panel.$element );
} );
