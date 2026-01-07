( function ( $ ) {
	$( () => {
		const $grid = $( '#bs-usermanager-grid' );
		if ( $grid.length === 0 ) {
			return;
		}

		function reEvaluateLimit() {
			bs.config.getDeferred( 'BlueSpiceUserLimit', true ).done( ( value ) => {
				if ( value.percent === null ) {
					// No point in showing the limit notice if no limit is set
					return;
				}
				$grid.find( '#bs-prodistributionconnector-userlimit-notice' ).remove();
				const widget = new OO.ui.MessageWidget( {
					id: 'bs-prodistributionconnector-userlimit-notice',
					type: value.percent > 90 ? 'error' : value.percent > 70 ? 'warning' : 'notice',
					label: new OO.ui.HtmlSnippet( value.sentence )
				} );

				widget.$element.css( 'margin-bottom', '20px' );
				$grid.prepend( widget.$element );
			} );
		}

		reEvaluateLimit();
		$( 'body' ).on( 'BSUserManagerStoreReload', () => {
			reEvaluateLimit();
		} );
	} );
}( jQuery ) );
