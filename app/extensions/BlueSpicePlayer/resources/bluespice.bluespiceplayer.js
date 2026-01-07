( function ( mw, $ ) {
	$( () => {
		$( '.bs-video' ).each( function () {
			const playrOptions = $( this ).data( 'plyr-config' );
			const player = new Plyr( this, playrOptions );
			// Even though the player is initialized with the `playrOptions.muted`,
			// Plyr does not respect these options in some cases.
			// Therefore we set them explicitly.
			player.muted = playrOptions.muted || false;

			// For some odd reason Plyr does not autoplay videos in some browsers.
			// Therefore we start it explicitly.
			if ( playrOptions.autoplay ) {
				// Starting the video without user interaction is only possible
				// if the video is muted. Plyr does not respect the value from
				// `playrOptions` in some cases.
				player.muted = true;
				player.play().catch( ( error ) => {
					mw.log.error( 'Error playing video:', error );
				} );
			}
		} );
	} );
}( mediaWiki, jQuery ) );
