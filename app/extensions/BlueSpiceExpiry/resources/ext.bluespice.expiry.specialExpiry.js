( ( $ ) => {

	$( () => {
		const $container = $( '#bs-expiry-special-expiry-container' );
		if ( $container.length === 0 ) {
			return;
		}

		const panel = new ext.bluespice.expiry.ui.panel.SpecialExpiryPanel();

		$container.append( panel.$element );
	} );

} )( jQuery );
