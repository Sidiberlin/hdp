/* eslint-disable no-underscore-dangle, no-console */
window.ext = window.ext || {};
ext.contentdroplets = {
	// TODO: Register all namespaces needed here (dm, ce...)
	object: {},
	dm: {},
	ce: {},
	ui: {},
	api: {
		getDroplets: function () {
			var dfd = $.Deferred();

			$.ajax( {
				method: 'GET',
				url: mw.util.wikiScript( 'rest' ) + '/contentdroplets/droplets',
				data: {},
				contentType: 'application/json',
				dataType: 'json'
			} ).done( function ( response ) {
				dfd.resolve( response );
			} ).fail( function ( jgXHR, type, status ) {
				if ( type === 'error' ) {
					dfd.reject( {
						error: jgXHR.responseJSON || jgXHR.responseText
					} );
				}
				dfd.reject( { type: type, status: status } );
			} );

			return dfd.promise();
		}
	},
	registry: new OO.Registry(),
	getDroplets: function ( noCache ) {
		return this._get( 'droplets', noCache );
	},
	getCategories: function ( noCache ) {
		return this._get( 'categories', noCache );
	},
	_get: function ( key, noCache ) {
		var dfd = $.Deferred();

		// eslint-disable-next-line no-prototype-builtins
		if ( ext.contentdroplets._cache.hasOwnProperty( key ) && !noCache ) {
			return dfd.resolve( ext.contentdroplets._cache[ key ] ).promise();
		}
		this._doLoad().done( function ( data ) {
			dfd.resolve( data[ key ] );
		} ).fail( function ( error ) {
			dfd.reject( error );
		} );
		return dfd.promise();
	},
	_doLoad: function () {
		var dfd = $.Deferred();

		ext.contentdroplets.api.getDroplets().done( function ( data ) {
			var instances = {},
				modules = [ 'ext.forms.standalone' ],
				droplets = data.droplets,
				key, config, dropletClass;

			ext.contentdroplets._cache.categories = data.categories;

			for ( key in droplets ) {
				// eslint-disable-next-line no-prototype-builtins
				if ( !droplets.hasOwnProperty( key ) ) {
					continue;
				}
				modules = modules.concat( droplets[ key ].rlModules || [] );
			}

			mw.loader.using( modules, function () {
				for ( key in droplets ) {
					// eslint-disable-next-line no-prototype-builtins
					if ( !droplets.hasOwnProperty( key ) ) {
						continue;
					}
					delete ( droplets[ key ].rlModules );
					config = droplets[ key ];
					config.key = key;
					dropletClass = ext.contentdroplets.registry.lookup( key );
					if ( !dropletClass ) {
						if ( config.content.indexOf( 'target' ) !== -1 ) {
							instances[ key ] =
								new ext.contentdroplets.object.TransclusionDroplet( config );
							continue;
						}
						instances[ key ] = new ext.contentdroplets.object.Droplet( config );
					} else {
						// eslint-disable-next-line new-cap
						instances[ key ] = new dropletClass( config );
					}
				}

				ext.contentdroplets._cache.droplets = instances;
				dfd.resolve( ext.contentdroplets._cache );
			}, function () {
				ext.contentdroplets._cache = {};
				console.error( 'ContentDroplets: Required RL modules failed to load' );
			} );
		} ).fail( function ( error ) {
			ext.contentdroplets._cache = {};
			console.error( 'ContentDroplets: ' + error );
		} );

		return dfd.promise();
	},
	_cache: {}
};

mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init', function () {
	mw.libs.ve.targetLoader.addPlugin( function () {
		var dfd = $.Deferred();
		ext.contentdroplets.getDroplets().done( function () {
			mw.loader.using( [ 'ext.contentdroplets.ve.toolbar' ], function () {
				dfd.resolve();
			}, function () {
				dfd.reject();
			} );
		} ).fail( function () {
			dfd.reject();
		} );

		return dfd.promise();
	} );

} );

mw.hook( 've.collabpad.DropletsActivation' ).add( function () {
	ve.init.mw.CollabTarget.static.toolbarGroups.push( {
		include: [ 'contentdroplet-toolbar' ]
	} );
} );
