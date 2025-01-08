/**
 * Js for WhoIsOnline extension
 *
 * @author     Patric Wirth
 * @package    Bluespice_Extensions
 * @subpackage WhoIsOnline
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GPL-3.0-only
 * @filesource
 */
( function( mw, $, bs, d ){
	var interval = mw.config.get( 'bsgWhoIsOnlineInterval', 0 ) * 1000;
	if ( interval < 1 ) {
		return;
	}
	if ( !String.prototype.startsWith ) {
		// old browsers
		Object.defineProperty( String.prototype, 'startsWith', {
			value: function(search, rawPos) {
				var pos = rawPos > 0 ? rawPos|0 : 0;
				return this.substring(pos, pos + search.length) === search;
			}
		} );
	}
	var markers = {};
	var onlineMarkers = function() {
		$( '.bs-userminiprofile[data-bs-whoisonline-marker]' ).each( function() {
			var userName = $( this ).data( 'bs-whoisonline-marker' );
			if ( !userName || userName.length < 1 ) {
				return;
			}
			markers[userName] = 'unchecked';
		} );
		return markers;
	};
	var listener = function( result, Listener ) {
		if( result.success !== true ) {
			return;
		}

		markers = result['onlinemarkers'];
		$( '.bs-userminiprofile[data-bs-whoisonline-marker]' ).removeClass( function ( index, classString ) {
			var classes = classString.split(" "),
				remove = [];
			for ( var i = 0; classes.length > i; i++ ) {
				if ( classes[i].startsWith( 'bs-whoisonline-marker-' ) !== true ) {
					continue;
				}
				remove.push( classes[i] );
			}
			return remove.join(" ");
		} );

		$( '.bs-userminiprofile[data-bs-whoisonline-marker]' ).each( function() {
			var userName = $( this ).data( 'bs-whoisonline-marker' );
			if ( !userName || userName.length < 1 ) {
				return;
			}
			var userMarker = ( markers[userName] ? markers[userName] : 'unchecked' );
			$( this ).addClass( 'bs-whoisonline-marker-' + userMarker );
			if ( userMarker !== 'unchecked' ) {
				$( this ).find( 'a' ).attr( 'aria-label', userName + ' ' + userMarker );
			}
		} );

		$( '.bs-whoisonline-portlet' ).each( function(){
			var portlet = result['portletItems'];
			$( this ).html( portlet );
		});

		$( '.bs-whoisonline-count').each( function(){
			$( this ).html( result['count'] );
		} );

		BSPing.registerListener(
			'WhoIsOnline',
			interval,
			[ { 'onlinemarkers': onlineMarkers() } ],
			listener
		);
	};
	BSPing.registerListener(
		'WhoIsOnline',
		interval,
		[ { 'onlinemarkers': onlineMarkers() } ],
		listener
	);

	let whoIsOnlinePopup = null;

	function getPopupContent( data ) {
		let panel = $( '<div>' );
		if ( data.length > 0 ) {
			data = data.split( ',' );
			for ( let key in data ) {
				let userWidget = new OOJSPlus.ui.widget.UserWidget( {
					user_name: data[ key ],
					showLink: true,
					showRawUsername: false
				} );
				panel.append( userWidget.$element );
			}
		} else {
			$span = $( '<span>' ).text( mw.message( 'bs-whoisonline-nousers' ).text() );
			panel.append( $span );
		}
		return panel;
	}

	function showPopup( element ) {
		let targetId = '#' + $( element ).attr( 'data-target-id' );
		let targetData = $( element ).attr( 'data-target' );
		if ( !whoIsOnlinePopup ) {
			let content = getPopupContent( targetData );
			whoIsOnlinePopup = new OO.ui.PopupWidget( {
				$content: content,
				padded: true,
				id: targetId,
				width: 300
			} );
			$( element ).append( whoIsOnlinePopup.$element );
		}
		whoIsOnlinePopup.toggle( true );
		$( element ).attr( 'aria-expanded', true );
	}

	function closePopup( element ) {
		whoIsOnlinePopup.toggle( false );
		$( element ).attr( 'aria-expanded', false );
	}

	$( '.wo-link' ).on( 'mouseover click', ( e ) => {
		showPopup( e.currentTarget );
	} );

	$( '.wo-link' ).on( 'mouseleave', ( e ) => {
		closePopup( e.currentTarget );
	} );

	$( '.wo-link' ).on( 'keydown', ( e ) => {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			showPopup( e.currentTarget );
		}
		if ( e.key === 'Escape' && whoIsOnlinePopup ) {
			closePopup( e.currentTarget );
		}
	} );
} )( mediaWiki, jQuery, blueSpice, document );
