( function( mw, $, d ){
	$( d ).on( 'click', '#ca-watch, #ca-unwatch', function( e ) {
		var $this = $(this);
		var currentPage = mw.config.get( 'wgPageName' );
		mw.loader.using( 'mediawiki.api' ).done( function() {
			var api = new mw.Api();
			if( $this.attr( 'id' ) === 'ca-watch' ) {
				api.watch( currentPage ).done( function() {
					$this.attr( 'id', 'ca-unwatch' );
					$this.attr( 'aria-label', mw.message( 'unwatch' ).plain() );
					$this.attr( 'title', mw.message( 'tooltip-ca-unwatch' ).plain() );
					$this.removeClass( 'bi-star' ).addClass( 'bi-star-fill' );
					mw.notify( mw.message( 'addedwatchtext-short', currentPage ).text() );
				} );
				e.preventDefault();
				return false;
			}
			api.unwatch( currentPage ).done( function() {
				$this.attr( 'id', 'ca-watch' );
				$this.attr( 'aria-label', mw.message( 'watch' ).plain() );
				$this.attr( 'title', mw.message( 'tooltip-ca-watch' ).plain() );
				$this.removeClass( 'bi-star-fill' ).addClass( 'bi-star' );
				mw.notify( mw.message( 'removedwatchtext-short', currentPage ).text() );
			} );
		} );
		e.preventDefault();
		return false;
	} );
} )( mediaWiki, jQuery, document );