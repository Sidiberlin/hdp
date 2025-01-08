( function( mw, $, d ){

	var initialTitlePos = 0;
	$( document ).ready( function( e ) {
		initialTitlePos = getTitleLineContent().offset().top;
		if ( initialTitlePos === 'undefined' ) {
			initialTitlePos = 0;
		}

		$( window ).scroll( function() {
			if( !isSpecialPage() && window.innerWidth >= 767  ) {
				setSickyTitleForVerticalPosition();
				handleVEToolbar();
			}
		} );

		$( window ).on( 'resize', function( e ) {
			if ( ( window.innerWidth < 766 ) &&  $( 'body' ).hasClass( 'title-fixed' ) ) {
				disableStickyTitle();
			} else if ( window.innerWidth >= 767 ) {
				setSickyTitleForVerticalPosition();
				handleVEToolbar();
			}
		} );

		if ( $( 'body' ).hasClass( 'title-fixed' ) ) {
			let resizeObserver = new ResizeObserver(() => {
				alignTitleLine();
				resizeTitleLineContent();
			} );
			resizeObserver.observe( $( '#main' )[0] );
		}

	} )

	function setSickyTitleForVerticalPosition() {
		var windowTop = $( this ).scrollTop();
		var headerHeight = $( '#header > nav' ).height();

		if ( windowTop > ( initialTitlePos - headerHeight ) ) {
			enableSitckyTitle();
		} else {
			disableStickyTitle();
		}
	}

	function enableSitckyTitle() {
		$( 'body' ).addClass( 'title-fixed' );

		var $titleLine = getTitleLine();
		var $titleSection = getTitleSection();
		$titleSection.css( 'padding-top', $titleLine.height() );

		alignTitleLine();
		resizeTitleLineContent();
	}

	function disableStickyTitle() {
		$( 'body' ).removeClass( 'title-fixed' );

		var $titleSection = getTitleSection();
		$titleSection.removeAttr( 'style' );

		var $titleLineContent = getTitleLineContent();
		$titleLineContent.removeAttr( 'style' );
	}

	function handleVEToolbar() {
		if ( inEditMode() ) {
			if ( isStickyTitle() ) {
				setVEToolbarPosition();
			} else {
				resetVEToolbarPosition();
			}
		}
	}

	function isStickyTitle() {
		return $( 'body' ).hasClass( 'title-fixed' );
	}

	function isSpecialPage() {
		return $( 'body' ).hasClass( 'ns-special' );
	}

	function getTitleSection() {
		return $( '#title-section' );
	}

	function getTitleLine() {
		return $( '#title-line' );
	}

	function getTitleLineContent() {
		return $( '#title-line > div' );
	}

	function setTitleLineContentWidth( width ) {
		var $titleLineContent = getTitleLineContent();
		$titleLineContent.outerWidth( width );
	}

	function getWrapperTopPosition() {
		return $( '#wrapper' ).offset().top;
	}

	function getMainWidth() {
		return $( '#main' ).outerWidth();
	}

	function getMainPosLeft() {
		var offset =  $( '#main' ).offset();
		return offset.left;
	}

	function getContentWidth() {
		return $( 'main' ).outerWidth();
	}

	function resizeTitleLineContent() {
		var mainWidth = getMainWidth();
		// MMV overlay triggers this resize method but with
		// var mainWidth = 0
		if ( mainWidth > 0 ) {
			setTitleLineContentWidth( mainWidth );
		}
	}

	function alignTitleLine() {
		var left = getMainPosLeft();
		$( 'body.title-fixed #title-line > div' ).css( 'margin-left', left );
	}

	function getVEToolbar() {
		return  $( '#content .ve-init-target >.ve-ui-toolbar > .oo-ui-toolbar-bar' );
	}

	function setVEToolbarPosition() {
		var top = getWrapperTopPosition();
		var mainWidth = getContentWidth();
		var $titleLine = getTitleLine();
		var topPosition = $titleLine.height() + top;

		var $toolbar = getVEToolbar();
		$toolbar.css( 'top', topPosition );
		$toolbar.css( 'position', 'fixed' );
		$toolbar.width( mainWidth );

		var $titleSection = getTitleSection();
		$titleSection.css( 'padding-bottom', $toolbar.height() );
	}

	function resetVEToolbarPosition() {
		var $toolbar = getVEToolbar();
		$toolbar.removeAttr( "style" );
	}

	function inEditMode() {
		var targetVisual = $( '.ve-init-target-visual' );
		var targetSource = $( '.ve-init-target-source' );

		if ( targetVisual.length || targetSource.length ) {
			return true;
		}

		return false;
	}

} )( mediaWiki, jQuery, document );