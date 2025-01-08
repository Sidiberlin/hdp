( function( d, $, mw ) {
	$( '.stacked-tabs .nav-tabs a' ).on( 'click', function() {
		$button = $( this );
		target = $button.data( 'bsTarget' );

		$button.parents( '.stacked-tabs').find( '.tab-pane.show.active' ).removeClass( 'show active' );
		$button.parents( '.stacked-tabs').find( '#'+target ).addClass( 'show active' );
	} );
})( document, jQuery, mediaWiki );