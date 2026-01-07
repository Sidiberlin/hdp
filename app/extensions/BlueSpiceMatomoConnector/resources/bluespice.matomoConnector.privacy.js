BsMatomoConnector = BsMatomoConnector || {};
BsMatomoConnector.privacy = {
	onSaveCookiePreferences: function ( args ) {
		if ( !args.hasOwnProperty( 'url' ) ) {
			return;
		}
		const url = args.url + '.' + ( args.value ? 'doTrack' : 'doIgnore' );
		$.get( url );
	}
};
