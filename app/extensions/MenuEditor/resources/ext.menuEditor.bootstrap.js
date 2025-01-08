window.ext = window.ext || {};

ext.menueditor = {
	init: {
		getPanelForPage: function ( pagename, menuType, revision, mode, cfg ) {
			var dfd = $.Deferred();
			mw.loader.using( [ 'ext.menuEditor.loader', 'ext.menuEditor.panel' ], function () {
				var loadPromise = ext.menueditor.init.loadEntities(),
					parsePromise = ext.menueditor.api.parse( pagename, revision ).then(
						function ( response ) {
							return response;
						},
						function ( error ) {
							console.error( error ); // eslint-disable-line no-console
						}
					);

				$.when( loadPromise, parsePromise ).then(
					function ( loadResult, nodes ) {
						// TODO: Check results, that menuType is registered...
						dfd.resolve(
							new ext.menueditor.ui.panel.MenuPanel( $.extend( {
								pagename: pagename, expanded: false, mode: mode,
								toolbar: loadResult[ menuType ].toolbar
							}, cfg ), nodes, menuType )
						);
					},
					function () {
						dfd.reject();
					}
				);
			}, function () {
				dfd.reject();
			} );

			return dfd.promise();
		}
	},
	ui: {
		data: {
			tree: {},
			node: {}
		},
		panel: {},
		dialog: {},
		widget: {},
		tools: {}
	},
	registry: {},
	api: {
		parse: function ( pagename, revision, flat ) {
			var dfd = $.Deferred();
			mw.loader.using( 'ext.menuEditor.api', function () {
				var api = new ext.menueditor.api.Api();
				api.get( 'parse/{0}'.format( ext.menueditor.util.escapePagenameForRest( pagename ) ), {
					revid: revision || 0,
					flat: typeof flat === 'undefined' ? false : !!flat
				} ).done( function ( data ) {
					dfd.resolve( data );
				} ).fail( function ( error ) {
					dfd.reject( error );
				} );
			} );
			return dfd.promise();
		},
		save: function ( pagename, nodes ) {
			var dfd = $.Deferred();
			mw.loader.using( 'ext.menuEditor.api', function () {
				var api = new ext.menueditor.api.Api();
				api.post( ext.menueditor.util.escapePagenameForRest( pagename ), nodes )
					.done( function ( response ) {
						dfd.resolve( response );
					} )
					.fail( function ( error ) {
						dfd.reject( error );
					} );
			} );

			return dfd.promise();
		}
	},
	util: {
		callbackFromString: function ( str ) {
			var parts = str.split( '.' );
			var func = window[ parts[ 0 ] ];
			for ( var i = 1; i < parts.length; i++ ) {
				func = func[ parts[ i ] ];
			}

			return func;
		},
		escapePagenameForRest: function ( pagename ) {
			return mw.util.rawurlencode( mw.util.rawurlencode( pagename ) );
		}
	}
};
