( function( mw, $, d, undefined ){
	scrollToTop = {
		duration: 400,
		offset: 300
	};

	$( window ).scroll( function() {
		if( $( this ).scrollTop() > scrollToTop.offset ) {
			$( '#back-to-top' ).removeClass( 'd-none' );
		}
		else {
			$( '#back-to-top' ).addClass( 'd-none' );
		}
	});

	$( d ).on('click', '.back-to-top', function( e ){
		e.preventDefault();
		$( 'body,html' ).animate(
			{
				scrollTop: 0
			},
			scrollToTop.duration
		);

		$( '#content' ).first().focus();
	} );

} )( mediaWiki, jQuery, document );