$( function() {
	var $mainContainer = $( '#bs-extendedstatistics-special-aggregatedstatistics' );
	var pluginModules = require( './pluginModules.json' );

	var reportHandlers =  $mainContainer.attr( 'data-reports' ),
		registry = new bs.aggregatedStatistics.ReportRegistry( JSON.parse( reportHandlers ) ),
		defaultFilters = JSON.parse( $mainContainer.attr( 'data-default-filter' ) );

	registry.connect( this, {
		registrationComplete: function() {
			var options = [];
			for ( var key in registry.registry ) {
				if ( !registry.registry.hasOwnProperty( key ) ) {
					continue;
				}
				options.push( new OO.ui.MenuOptionWidget( { data: key, label: registry.registry[key].static.label } ) );
			}
			dropdown.getMenu().addItems( options );
			dropdown.setDisabled( false );

			dropdown.getMenu().selectItemByData( Object.keys( registry.registry )[0] );
		}
	} );

	var dropdown = new OO.ui.DropdownWidget( {
		id: "statistic-selector",
		disabled: true
	} ),
		panel = new OO.ui.PanelLayout( { expanded: false });

	var mainLayout = new OO.ui.FieldsetLayout( {
		label: mw.message( "bs-statistics-aggregated-report-picker-label" ).text(),
		align: 'top',
		items: [
			dropdown,
			panel
		]
	} );

	$mainContainer.append( mainLayout.$element );

	dropdown.getMenu().connect( this, {
		select: function( selected ) {
			if ( selected instanceof OO.ui.MenuOptionWidget ) {
				var cls = registry.lookup( selected.getData() );
				var item = new cls( {
					defaultFilterValues: defaultFilters,
					name: selected.getData()
				} );

				panel.$element.html( item.$element );
				item.connect( this, {
					dataset: function ( data ) {
						mw.loader.using( pluginModules ).done( function () {
							mw.hook( 'aggregatedstatistics.addUI' ).fire( data );
						} );
					}
				} );
			}
		}
	} );

})
