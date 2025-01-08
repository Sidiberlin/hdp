$( () => {
	const $liveRegion = $( '#mws-wcag-generic-status-container' );
	$liveRegion.text( mw.message( 'bs-expiry-pageinfoelement-expired-tooltip' ).text() );
} );
