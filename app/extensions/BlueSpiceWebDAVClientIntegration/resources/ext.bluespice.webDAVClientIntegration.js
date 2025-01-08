( function( mw, $, bs, d, undefined ) {
	$( d ).bind( 'BSContextMenuBeforeCreate', function( event, anchor, items ) {
		var _config = null;
		var _getAppProtocol = function( fileExtension ) {
			for( var appMsg in _config.clientapps ) {
				var appExtensions = _config.clientapps[ appMsg ].extensions;
				if( appExtensions && $.inArray( fileExtension, appExtensions ) !== -1 ){
					return _config.clientapps[ appMsg ].protocol;
				}
			}
		};

		var _handleOpenFile = function( webdavUrl ) {
			//1. Check if url begins with file protocol, if so, do nothing
			if( webdavUrl.indexOf( 'file://' ) !== -1 ) {
				window.location.href = webdavUrl;
				return false;
			}

			try {
				//2. Try opening with ActiveX
				_ed = new ActiveXObject('SharePoint.OpenDocuments.3');
				_ed.EditDocument( webdavUrl );
			} catch (ex) {
				//3. Open using app protocol
				var urlBits = webdavUrl.split( '.' );
				var fileExtension = urlBits[ urlBits.length - 1 ];
				_config = mw.config.get( 'bsWebDAVConfig' );

				var appProtocol = _getAppProtocol( fileExtension );
				if( !appProtocol ) {
					bs.util.alert(
						'bs-webdav-alert',
						{
							textMsg: 'bs-webdav-ci-cannot-open-file'
						}
					);
					return false;
				}
				var url = appProtocol + ':ofe|u|' + encodeURI( webdavUrl );
				window.location.href = url;
				return false;
			}
		};

		var _showConfirmDialog = function( response, webdavUrl ) {
			if( response['success'] === false || response['payload']['locked'] === false ) {
				_handleOpenFile( webdavUrl );
				return;
			}

			var userName = response['payload']['user_name'];
			if( userName === mw.config.get( 'wgUserName' ) ) {
				_handleOpenFile( webdavUrl );
				return;
			}

			bs.util.confirm(
				'bs-webdav-ci-locked-file-alert',
				{
					titleMsg: "bs-webdav-ci-file-locked-title",
					text: mw.message( 'bs-webdav-ci-file-locked', userName ).plain()
				},
				{
					ok: function() {
						_handleOpenFile( webdavUrl );
					},
					scope: this
				}
			);
		}

		$.each( items, function( key, item ) {
			if( item.id === 'bs-webdav-show-history' ) {
				item.handler = function() {
					mw.loader.using( 'ext.bluespice.extjs' ).done( function() {
						Ext.onReady( function() {
							Ext.require( 'BS.WebDAVClientIntegration.window.FileHistory', function() {
								var window = new BS.WebDAVClientIntegration.window.FileHistory({
									bsFileName: anchor.data('bs-filename') || anchor.attr('title') //Fallback to MediaWiki standard
								});
								window.show( anchor[0] );
							}, this );
						} );
					} );
				}
				item.scope = anchor;
			}

			if( item.id === 'bs-webdav-edit-file' ) {
				item.__href = item.href;
				item.href = '';
				item.handler = function( activeItem ) {
					var webdavUrl = activeItem.__href;
					var api = new mw.Api();
					api.postWithToken( 'csrf', {
						action: 'bs-file-tasks',
						task: 'getLock',
						format: 'json',
						taskData: JSON.stringify( {
							webdavUrl: webdavUrl
						} )
					} ).done( function( response ) {
						_showConfirmDialog( response, webdavUrl );
					} ).fail( function( response ) {
						_handleOpenFile( webdavUrl );
					});
				};
				item.scope = anchor;
			}
		} );
	} );

} )( mediaWiki, jQuery, blueSpice, document );