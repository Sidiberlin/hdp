(function( mw, $, d, undefined ) {
	$( function() {
		$( '.bs-video' ).each( function() {
			this.autoplay = mw.user.options.get( 'bs-showtime-pref-autostart' ) === '1';
			// Nobody wants to get blasted with sound on an autoplay video
			this.muted = this.autoplay;
			new Plyr( this );
		} );
	} );
})( mediaWiki, jQuery, document );
