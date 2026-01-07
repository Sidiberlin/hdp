/* eslint-disable no-jquery/no-global-selector */

( async function ( mw, $ ) {
	$( '#mw-content-text' ).empty();

	const selectFirstContentOffset = function ( surface ) {
		var firstOffset = surface.getDocument().data.getNearestContentOffset(
			surface.getAttachedRoot().getOffset(),
			1
		);
		if ( firstOffset !== -1 ) {
			// Found a content offset
			surface.setLinearSelection( new ve.Range( firstOffset ) );
		} else {
			// Document is full of structural nodes, just give up
			surface.setNullSelection();
		}
	};

	try {
		const conf = mw.config.get( 'wgVisualEditorConfig' );
		const uri = require( './backendServiceURL.json' ).backendServiceURL;
		const pluginModules = require( './pluginModules.json' );

		const modules = [ OO.ui.isMobile() ? 'ext.CollabPads.collabTarget.mobile' : 'ext.CollabPads.collabTarget.desktop' ]
		// Add modules from $wgVisualEditorPluginModules
			.concat( conf.pluginModules.filter( mw.loader.getState ) )
			.concat( pluginModules );

		await mw.loader.using( modules );

		const pageExists = mw.config.get( 'wgArticleId' ) !== 0;
		const importTitle = mw.Title.newFromText( mw.config.get( 'wgPageName' ) );
		const username = mw.user.getName();
		const accessToken = mw.config.get( 'wgCollabPadsUserAccessToken' );

		// Fetch real name
		const apiResponse = await new mw.Api().get( {
			action: 'query',
			meta: 'userinfo',
			uiprop: 'realname',
			format: 'json'
		} );

		const realName = ( apiResponse.query && apiResponse.query.userinfo && apiResponse.query.userinfo.realname ) || '';
		const preloadModules = [ ...conf.preloadModules, 'ext.visualEditor.targetLoader' ];

		await mw.loader.using( preloadModules );
		await mw.libs.ve.targetLoader.loadModules( 'visual' );

		const target = ve.init.mw.targetFactory.create( 'collab', importTitle, uri, { importTitle } );

		$( 'body' ).addClass( 've-activated ve-active' );
		$( '#content' ).prepend( target.$element );
		target.transformPage();
		$( '#firstHeading' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );

		// Add a dummy surface while the doc is loading
		const dummySurface = target.addSurface( ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '' ) ) );
		dummySurface.setReadOnly( true );

		// TODO: Create the correct model surface type (ve.ui.Surface#createModel)
		const surfaceModel = new ve.dm.Surface( ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '' ) ) );
		surfaceModel.createSynchronizer(
			`${ mw.config.get( 'wgScriptPath' ) }|${ accessToken }|${ importTitle }`,
			{
				server: uri,
				defaultName: username,
				realName: realName
			}
		);

		dummySurface.createProgress( Promise.resolve(), ve.msg( 'visualeditor-rebase-client-connecting' ), true );

		surfaceModel.synchronizer.once( 'initDoc', async ( error ) => {
			// Wait until surfaces are cleared before adding new ones
			setTimeout( async () => {
				target.clearSurfaces();
				target.addSurface( surfaceModel );

				if ( error ) {
					OO.ui.alert(
						$( '<p>' ).append(
							ve.htmlMsg( 'visualeditor-rebase-corrupted-document-error', $( '<pre>' ).text( error.stack ) )
						),
						{ title: ve.msg( 'visualeditor-rebase-corrupted-document-title' ), size: 'large' }
					);
					return;
				}

				try {
					target.once( 'surfaceReady', async () => {
						try {
							await handleInitialisation( target, surfaceModel, pageExists, importTitle );
							selectFirstContentOffset( surfaceModel );
						} catch ( e ) {
							surfaceModel.synchronizer.initFailed();
						}
					} );
				} catch ( err ) {
					throw new Error( err );
				}
			} );
		} );
	} catch ( e ) {
		mw.log.warn( `VisualEditor failed to load: ${ e }` );
	}

	async function handleInitialisation( target, surfaceModel, pageExists, importTitle ) {
		if ( pageExists && !surfaceModel.getDocument().getCompleteHistoryLength() ) {
			const response = await mw.libs.ve.targetLoader.requestParsoidData( importTitle.toString(), { targetName: 'collabpad' } );
			const data = response.visualeditor;

			if ( data && data.content ) {
				const doc = target.constructor.static.parseDocument( data.content );
				const dmDoc = target.constructor.static.createModelFromDom( doc );
				const fragment = surfaceModel.getLinearFragment( new ve.Range( 0, 2 ) );
				fragment.insertDocument( dmDoc );
			} else {
				throw new Error( `No content for ${ importTitle }` );
			}
		}
	}
}( mediaWiki, jQuery ) );
