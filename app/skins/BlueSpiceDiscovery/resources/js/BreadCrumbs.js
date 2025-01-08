( function( d, $, mw ) {

	$( document ).ready( function( e ) {
		var dropdownToggles = document.getElementsByClassName( 'breadcrumb-nav-subpages' );

		Array.from( dropdownToggles ).forEach( toggle => {
			toggle.addEventListener ('show.bs.dropdown', function() {
				var $breadcrumbItem = $(this).parent( '.breadcrumb-item');
				if( $breadcrumbItem.data( 'loaded' ) === true ) {
					return;
				}
				var path = $(this).data( 'bs-path' );
				if( !path ) {
					return;
				}

				var $dropdownMenu = $breadcrumbItem.find('.dropdown-menu > ul').first();

				mw.loader.using( 'mediawiki.api' ).done( function() {
					var api = new mw.Api();
					api.abort();
					api.get( {
							"format": "json",
							"action": "bs-wikisubpage-treestore",
							"node": path,
							"limit": "-1"
					})
					.done( function( response ) {
						for ( var i = 0; i < response.children.length; i++ ) {
							$dropdownMenu.append( '<li>' + response.children[i].page_link + '</li>' );
						};
						$breadcrumbItem.data( 'loaded', true );
						var $dropdownItems = $dropdownMenu.find( 'a' );
						for ( var x = 0; x < $dropdownItems.length; x++ ) {
							$($dropdownItems[x]).addClass( "dropdown-item" );
						}
					});
				});
			});
		});
	} );

})( document, jQuery, mediaWiki );