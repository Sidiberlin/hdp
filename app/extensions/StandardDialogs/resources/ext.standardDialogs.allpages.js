window.registryPageInformation = new OO.Registry();
$( document ).on( 'click', '.page-tree-action-info', function ( e ) {
	const rlModules = require( './pageInfoPanelModuleRegistry.json' );
	if ( !e.currentTarget.dataset.title ) {
		return;
	}
	var title = e.currentTarget.dataset.title;

	mw.loader.using( 'ext.standardDialogs.ui.PageInformationDialog' ).done( function () {
		mw.loader.using( rlModules ).done( function () {
			const diag = new StandardDialogs.ui.PageInformationDialog( {
				pageName: title,
				panelRegistry: registryPageInformation
			} );
			diag.show();
		} );
	} );
	e.defaultPrevented = true;
	return false;
} );
