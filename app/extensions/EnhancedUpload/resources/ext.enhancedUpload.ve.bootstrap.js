mw.loader.using( 'ext.visualEditor.desktopArticleTarget.init' ).done( function () {
	var pluginModules = require( './pluginModules.json' );
	mw.loader.using( pluginModules ).done( function () {
		mw.loader.using( 'ext.enhancedUpload.ve.transferHandler' );
	} );
} );
