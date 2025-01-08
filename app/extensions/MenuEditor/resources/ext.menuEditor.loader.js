( function ( mw, $ ) {
	ext.menueditor.init.loadEntities = function () {
		var dfd = $.Deferred();

		function register( value, registry ) {
			var registerDfd = $.Deferred(), modules = [];
			for ( var name in value ) {
				if ( !value.hasOwnProperty( name ) ) { // eslint-disable-line no-prototype-builtins
					continue;
				}
				modules.push( value[ name ].module );
				registry.register( name, value[ name ].classname );
			}

			mw.loader.using( $.uniqueSort( modules ), function () {
				registerDfd.resolve();
			}, function () {
				registerDfd.reject( 'Cannot load modules:' + $.uniqueSort( modules ) );
			} );

			return registerDfd.promise();
		}

		ext.menueditor.registry.menu = new OO.Registry();
		ext.menueditor.registry.node = new OO.Registry();

		$.when(
			register( require( './menus.json' ), ext.menueditor.registry.menu, dfd ),
			register( require( './nodes.json' ), ext.menueditor.registry.node, dfd )
		).then( function () {
			dfd.resolve( require( './menus.json' ) );
		}, function ( e ) {
			console.error( e ); // eslint-disable-line no-console
			dfd.reject( e );
		} );
		return dfd.promise();
	};
}( mediaWiki, jQuery ) );
