( function( mw, $, d ){

	$( d ).ready( function() {
		var hash = window.location.hash;
		var offset = $( '#main' ).offset().top + $( '#title-line' ).height();
		if ( hash !== '' ) {
			var jumpmark = getJumpmarkEl( hash );
			if ( !jumpmark ) {
				return;
			}

			var position = getPosition( jumpmark, offset);
			$( 'body, html').animate( {
					scrollTop: position
				},
				100
			);
		};

		$( d ).on( 'click',
			'nav.skip-links a, #mw-content-text a:not( [id] ):not( [class] ),#mw-content-text map area',
			function ( event ) {
				userInputScroll( event, this, offset );
			}
		);

		$( d ).on( 'keyup',
			'nav.skip-links a, #mw-content-text a:not( [id] ):not( [class] ),#mw-content-text map area',
			function ( event ) {
				if ( event.keyCode === 13 ) {
					userInputScroll( event, this, offset );
				}
			}
		);

	} );

	function userInputScroll( event, element, offset ) {
		let localUrl = element.pathname + element.search;
		let fullUrl = window.location.origin + localUrl;

		if( element.href.indexOf( localUrl ) !== 0 && element.href.indexOf( fullUrl ) !== 0 ) {
			return;
		}

		if ( element.hash !== '' ) {
			var jumpmark = getJumpmarkEl( element.hash );
			if ( !jumpmark ) {
				return;
			}

			var position = getPosition( jumpmark, offset );
			if ( position === 0 ) {
				return;
			}

			$( 'body,html' ).animate( {
				scrollTop: position
			} );
		}
	}

	function getJumpmarkEl( hash ) {
		// Strip the leading # to get the id
		var id = hash.replace( '#', '' ),
			urldecodedId = decodeURIComponent( id ),
			element = d.getElementById( urldecodedId );

		if ( !element ) {
			// MediaWiki may use additional anchors
			id = mw.util.rawurlencode( urldecodedId ).replace( /%/g, '.' );
			element = d.getElementById( id );
		}
		return element;
	}

	function getPosition( jumpmark, offset ) {
		var $jumpmark = $( jumpmark ),
			$heading = $jumpmark.closest( 'h1,h2,h3,h4,h5,h6' ),
			position = $jumpmark.offset().top;

		if ( $heading.length === 1  ) {
			position -= $heading.height();
		}

		return position - offset;
	}

} )( mediaWiki, jQuery, document );
