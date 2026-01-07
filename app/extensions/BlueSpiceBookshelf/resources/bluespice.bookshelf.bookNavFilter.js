$( '.container-filter-search' ).each( function () { // eslint-disable-line no-jquery/no-global-selector
	const $searchField = $( this ),
		$containerEl = $searchField.parent(),
		searchField = OO.ui.infuse( $searchField );
	searchField.selector = $containerEl.data( 'selector' );
	searchField.on( 'change', function ( value ) {
		const normalValue = value.toLowerCase(),
			$elementsToFilter = $( this.selector );
		if ( normalValue === '' ) {
			$elementsToFilter.fadeIn(); // eslint-disable-line no-jquery/no-fade
			return;
		}
		$elementsToFilter.each( function () {
			const $element = $( this ),
				elementText = $element.text().toLowerCase();
			if ( elementText.indexOf( normalValue ) !== -1 ) {
				$element.fadeIn(); // eslint-disable-line no-jquery/no-fade
			} else {
				$element.fadeOut(); // eslint-disable-line no-jquery/no-fade
			}
		} );
	}, [], searchField );
} );
