( function( mw, $, d ){
    $( '.content-droplet-modal-button, .content-droplet-modal-dismiss' ).on( 'keydown', function( event ) {
        if( event.keyCode === 13 ) {
            $( this ).click();
        }
    } );
} )( mediaWiki, jQuery, document );