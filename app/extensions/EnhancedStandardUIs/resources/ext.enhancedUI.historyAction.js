( function ( mw, $ ) {

	$( function () {
		require( './panel/HistoryPanel.js' );
		mw.user.getRights().done( function ( rights ) {
			/* eslint-disable-next-line no-jquery/no-global-selector */
			var $historyCnt = $(
				'#enhanced-history-cnt[data-history]'
			);
			var cfg = {
				rights: rights,
				data: $historyCnt.data( 'history' )
			};
			var historyGrid = new ext.enhancedUI.panel.HistoryPanel( cfg );
			$historyCnt.append( historyGrid.$element );
		} );
	} );

	var toolbarOffsetJson = require( './addToolbarOffset.json' );
	var toolbarOffsetHeight = toolbarOffsetJson.offsetHeight;
	$( window ).on( 'scroll', function () {

		var windowTop = $( this ).scrollTop();
		var $toolbar = $( '.enhanced-history-toolbar' ); // eslint-disable-line no-jquery/no-global-selector
		var contentWidth = getContentWidth();

		if ( windowTop > toolbarOffsetHeight ) {
			$toolbar.css( 'top', toolbarOffsetHeight );
			$toolbar.css( 'position', 'fixed' );
			$toolbar.css( 'width', contentWidth );
			$toolbar.css( 'z-index', 5 );
		} else {
			$toolbar.removeAttr( 'style' );
		}
	} );

	function getContentWidth() {
		return $( '#mw-content-text' ).innerWidth(); // eslint-disable-line no-jquery/no-global-selector
	}
}( mediaWiki, jQuery ) );
