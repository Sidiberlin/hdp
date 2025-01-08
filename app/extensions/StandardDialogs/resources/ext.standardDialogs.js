$( document ).on( 'click', '#ca-delete', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.DeleteDialog' ] ).done( function () {
		const msg = require( './deleteDialogMsg.json' );

		const diag = new StandardDialogs.ui.DeleteDialog( {
			id: 'standarddialogs-dlg-delete',
			pageName: mw.config.get( 'wgRelevantPageName' ),
			msg: msg
		} );
		diag.on( 'actioncompleted', function () {
			window.location.reload();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'click', '#ca-move', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.MoveDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.MoveDialog( {
			id: 'standarddialogs-dlg-move',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function ( newTitle ) {
			window.location.href = newTitle.getUrl();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'click', '#ca-protect', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.ProtectDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.ProtectDialog( {
			id: 'standarddialogs-dlg-protect',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function () {
			window.location.reload();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'click', '#ca-purge', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.RefreshDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.RefreshDialog( {
			id: 'standarddialogs-dlg-refresh',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function () {
			window.location.reload();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'click', '#ca-copy', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.DuplicateDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.DuplicateDialog( {
			id: 'standarddialogs-dlg-copy',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function ( newTitle ) {
			window.location.href = newTitle.getUrl();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

window.registryPageInformation = new OO.Registry();
$( document ).on( 'click', '#t-info', function ( e ) {
	const rlModules = require( './pageInfoPanelModuleRegistry.json' );

	mw.loader.using( 'ext.standardDialogs.ui.PageInformationDialog' ).done( function () {
		mw.loader.using( rlModules ).done( function () {
			const diag = new StandardDialogs.ui.PageInformationDialog( {
				id: 'standarddialogs-dlg-pageinformation',
				pageName: mw.config.get( 'wgRelevantPageName' ),
				panelRegistry: registryPageInformation
			} );
			diag.show();
		} );
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'input', '#standarddialogs-dlg-new-page-tf-target input', function ( e ) {
	const params = {
			action: 'query',
			list: 'search',
			srsearch: e.target.value,
			format: 'json'
		},
		api = new mw.Api();

	api.get( params ).done( function ( data ) {
		if ( data.query.search[ 0 ] !== undefined ) {
			$( '.oo-ui-inputWidget-input' ).removeAttr( 'aria-label' );
		} else {
			$( '.oo-ui-inputWidget-input' ).attr( 'aria-label', mw.message( 'standarddialogs-new-page-not-exist-label' ).plain() );
		}
	} );
} );

$( document ).on( 'click', '#ca-new-page, #new-content', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.NewPageDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.NewPageDialog( {
			id: 'standarddialogs-dlg-new-page',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function ( newTitle ) {
			window.location.href = newTitle.getUrl( { action: 'edit' } );
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );

$( document ).on( 'click', '#ca-new-subpage', function ( e ) {
	mw.loader.using( [ 'ext.standardDialogs.ui.NewSubpageDialog' ] ).done( function () {
		const diag = new StandardDialogs.ui.NewSubpageDialog( {
			id: 'standarddialogs-dlg-new-subpage',
			pageName: mw.config.get( 'wgRelevantPageName' )
		} );
		diag.on( 'actioncompleted', function ( newTitle ) {
			window.location.href = newTitle.getUrl();
		} );
		diag.show();
	} );
	e.defaultPrevented = true;
	return false;
} );
