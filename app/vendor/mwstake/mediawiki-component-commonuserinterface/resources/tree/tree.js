( function( mw, $, d, undefined ){
    $( d ).on( 'click', '.mws-tree-expander', function( e ) {
        stopPropagation( e );

        controls = $( this ).attr( 'aria-controls' );
        toggleTreeItem( this, controls );
        
    } );

    $( d ).on( 'keydown', '.mws-tree li', function ( e ) {
        let $list = $( this ).parent( 'ul' );
        let nodes = $list.children();
        let index = nodes.index( this );

        //up
        if ( e.keyCode === 38 ) {
            stopPropagation( e );

            if ( index > 0 ) {
                let $listItem = $( nodes[index - 1] );
                let $focusableElementContainer = $listItem.find( '> div' ).first();
                let focusableElement = $focusableElementContainer.children().first();
                focusableElement.focus();
            }
            else {
                $parentTreeItem = $list.parents( 'li' ).first().find( '> div' ).first();
                if ( $parentTreeItem ) {
                    let $focusableElement = $parentTreeItem.children().first();
                    $focusableElement.focus();
                }
            }
        }

        // down
        if ( e.keyCode === 40 ) {
            stopPropagation( e );

            if ( $( this ).hasClass( 'expanded' ) ) {
                $childList = $( this ).find( '>  ul' ).first();
                let $listItem = $childList.children().first();
                let $focusableElementContainer = $listItem.find( '> div' ).first();
                let focusableElement = $focusableElementContainer.children().first();
                focusableElement.focus();
            }
            else if ( index < ( nodes.length - 1 ) ) {
                let $listItem = $( nodes[index + 1] );
                let $focusableElementContainer = $listItem.find( '> div' ).first();
                let focusableElement = $focusableElementContainer.children().first();
                focusableElement.focus();
            }
            else {
                let $parentItem = $( this ).parents( 'li' ).first();
                let $parentList = $parentItem.parent( 'ul' );
                let parentNodes = $parentList.children();
                let parentIndex = parentNodes.index( $parentItem );
                if ( parentIndex < parentNodes.length ) {
                    let $listItem = $( parentNodes[parentIndex + 1] );
                    let $focusableElementContainer = $listItem.find( '> div' ).first();
                    let focusableElement = $focusableElementContainer.children().first();
                    focusableElement.focus();
                }
            }
        }

        //left
        if ( e.keyCode === 37 ) {
            stopPropagation( e );
            // does not work for some reason
            if ( this.previousElementSibling ) {
                this.previousElementSibling.focus();
            }
        }

        // right
        if ( e.keyCode === 39 ) {
            stopPropagation( e );       
            if ( this.nextElementSibling ) {
                this.nextElementSibling.focus();
            }
        }
    } );

    $( d ).on( 'keydown', '.mws-tree-expander', function ( e ) {
        controls = $( this ).attr( 'aria-controls' );

        if ( e.keyCode === 13 ) {
            stopPropagation( e );
            toggleTreeItem( this, controls );
        }

        //left
        if ( e.keyCode === 37 ) {
            stopPropagation( e );
            if ( isExpanded( this ) ) {
                collapseTreeItem( this, controls );
            }
            else if ( this.previousElementSibling ) {
                this.previousElementSibling.focus();
            }
        }

        // right
        if ( e.keyCode === 39 ) {
            stopPropagation( e );       
            if ( !isExpanded( this ) ) {

                expandTreeItem( this, controls );
            }
            else if ( this.nextElementSibling ) {
                this.nextElementSibling.focus();
            }
        }
    } );
    

    function toggleTreeItem( element, controls ) {
        if ( $( element ).attr( 'aria-expanded' ) === 'true' ) {
            collapseTreeItem( element, controls );
        } else if ( $( element ).attr( 'aria-expanded' ) === 'false' ) {
            expandTreeItem( element, controls);
        }
    }

    function collapseTreeItem( element, controls ) {
        console.log( controls );
        $( '#'+controls ).removeClass( 'show' );
        $( '#'+controls ).attr( 'aria-expanded', 'false' );
        $( element ).attr( 'aria-expanded', 'false' );
        $( element ).removeClass( 'expanded' );
        $( element ).addClass( 'collapsed' );
        $( element ).parents( 'li' ).first().removeClass( 'expanded' );
    }

    function expandTreeItem( element, controls ) {
        $( '#'+controls ).addClass( 'show' );
        $( '#'+controls ).attr( 'aria-expanded', 'true' );
        $( element ).attr( 'aria-expanded', 'true' );
        $( element ).removeClass( 'collapsed' );
        $( element ).addClass( 'expanded' );
        $( element ).parents( 'li' ).first().addClass( 'expanded' );
    }

    function isExpanded( element ) {
        if ( $( element ).attr( 'aria-expanded' ) === 'true' ) {
            return true;
        }

        return false;
    }

    function stopPropagation( element ) {
        element.preventDefault();
		element.stopPropagation();
    }

} )( mediaWiki, jQuery, document );