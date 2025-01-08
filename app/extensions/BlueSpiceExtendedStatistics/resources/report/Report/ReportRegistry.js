( function( mw, bs ) {
	bs.util.registerNamespace( 'bs.aggregatedStatistics' );

	bs.aggregatedStatistics.ReportRegistry = function( reports ) {
		bs.aggregatedStatistics.ReportRegistry.parent.call( this );
		OO.EventEmitter.call( this );

		var modules = [];
		var classes = {};
		for ( var key in reports ) {
			if ( !reports.hasOwnProperty( key ) ) {
				continue;
			}
			modules = modules.concat( reports[key].rlModules );
			classes[key] = reports[key].class;
		}

		mw.loader.using( modules, function() {
			for ( var key in classes ) {
				if ( !classes.hasOwnProperty( key ) ) {
					continue;
				}
				this.register( key, this.callbackFromString( classes[key] ) );
			}
			this.emit( 'registrationComplete' );
		}.bind( this ) );
	};

	OO.inheritClass( bs.aggregatedStatistics.ReportRegistry, OO.Registry );
	OO.mixinClass( bs.aggregatedStatistics.ReportRegistry, OO.EventEmitter );

	bs.aggregatedStatistics.ReportRegistry.prototype.callbackFromString = function( callback ) {
		var parts = callback.split( '.' );
		var func = window[parts[0]];
		for( var i = 1; i < parts.length; i++ ) {
			func = func[parts[i]];
		}

		return func;
	};

} )( mediaWiki, blueSpice );