( function( mw, $, d, undefined ){
    // See https://github.com/hallowelt/mwstake-mediawiki-component-commonuserinterface/blob/master/tests/phpunit/TreeDataGeneratorTest.php#L32
    // and BlueSpiceDiscovery/src/Component/EnhancedSidebarTree.php

    // This script is responsible for setting the cookie. Functionality of the tree is done in
    // https://github.com/hallowelt/mwstake-mediawiki-component-commonuserinterface/blob/master/resources/tree/tree.js
    $( '.tree-component' ).on( 'click', '.mws-tree-expander', function( e ) {
        let cntId = getContainerId( this );
        let cookieContent = getCookieContent( cntId );
        let expandedBeforeClick = $( this ).attr( 'aria-expanded' );
        let $items = $( this ).parents( 'li' );
        let path = makePath( $items );

        let newCookieContent = [];
        if ( expandedBeforeClick === 'false' ) {
            newCookieContent = addPath( path, cookieContent );
        } else {
            newCookieContent = removePath( path, cookieContent );
        }

        setCookieContent( cntId, newCookieContent );
    } );

    function getContainerId( clickEl ) {
        cnt = $( clickEl ).parents( '.mws-tree-cnt.root ' ).first();
        cntId = $( cnt ).attr( 'id' );

        return cntId;
    }

    function getCookieContent( cntId ) {
        let cookieContent = discovery_cookie.get( cntId );

        if ( !cookieContent ) {
            cookieContent = [];
        }

        return cookieContent;
    }

    function setCookieContent( cntId, cookieContent ) {
        discovery_cookie.set( cntId, cookieContent );
    }

    function makePath( $items ) {
        let paths = [];
        for ( let index = ( $items.length - 1 ); index >= 0; index-- ) {
            let item = $items[index];
            let id = $( item ).attr( 'id' );

            paths.push( id );
        }

        let path = paths.join( '/' );

        return path;
    }

    function addPath( path, cookieContent ) {
        let paths = removePath( path, cookieContent );
        paths.push( path );

        return paths;
    }

    function removePath( path, cookieContent ) {
        let paths = [];
        for ( let index = 0; index < cookieContent.length; index++ ) {
            let curPath = cookieContent[index];
            if ( curPath.substr( 0, path.length ) !== path ) {
                paths.push( curPath );
            }
        }

        return paths;
    }
} )( mediaWiki, jQuery, document );