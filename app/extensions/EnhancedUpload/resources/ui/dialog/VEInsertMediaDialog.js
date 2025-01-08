window.enhancedUpload = window.enhancedUpload || {};
window.enhancedUpload.ui = window.enhancedUpload.ui || {};
window.enhancedUpload.ui.dialog = window.enhancedUpload.ui.dialog || {};

enhancedUpload.ui.dialog.VEInsertMediaDialog = function ( cfg ) {
	enhancedUpload.ui.dialog.VEInsertMediaDialog.super.call( this, cfg );
};

OO.inheritClass( enhancedUpload.ui.dialog.VEInsertMediaDialog, ve.ui.NodeDialog );

enhancedUpload.ui.dialog.VEInsertMediaDialog.static.name = 'mediaUpload';

enhancedUpload.ui.dialog.VEInsertMediaDialog.static.title = OO.ui.deferMsg( 'enhancedupload-ve-dialog-title' );

enhancedUpload.ui.dialog.VEInsertMediaDialog.static.size = 'medium';

/**
 * @inheritdoc
 */
enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.initialize = function () {
	enhancedUpload.ui.dialog.VEInsertMediaDialog.super.prototype.initialize.call( this );
	var panel;
	this.pageName = mw.config.get( 'wgPageName' );

	// Slashes are no valid chars in a filename
	this.pageName = this.pageName.replace( /\//g, '_' );

	this.targetTitle = new OO.ui.TextInputWidget( {
		value: this.pageName + '_' + Date.now()
	} );

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: true
	} );

	panel = new OO.ui.FieldLayout( this.targetTitle, {
		label: mw.message( 'enhancedupload-ve-dialog-filename-label' ).plain(),
		align: 'top'
	} );
	this.content.$element.append( panel.$element );

	this.$body.append( this.content.$element );
};

/**
 * @inheritdoc
 */
enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.getSetupProcess = function ( data ) {
	this.file = data.file;
	this.targetTitle.setValue( this.pageName + '_' + Date.now() );
	return enhancedUpload.ui.dialog.VEInsertMediaDialog.super.prototype.getSetupProcess.call(
		this, data
	);
};

/**
 * @inheritdoc
 */
enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.getBodyHeight = function () {
	// eslint-disable-next-line no-jquery/no-class-state
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}

	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight + 10;
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.getActionProcess = function ( action ) {
	var me = this;
	if ( action === 'done' ) {
		var doneActionProcess = this.makeDoneProcess();
		doneActionProcess.next( function () {
			me.close( { action: action } );
		} );
		return doneActionProcess;
	}
	return enhancedUpload.ui.dialog.VEInsertMediaDialog.parent.prototype.getActionProcess.call(
		this,
		action
	);
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.showErrors = function ( errors ) {
	enhancedUpload.ui.dialog.VEInsertMediaDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.makeDoneProcess = function () {
	var dfd = new $.Deferred(),
		me = this,
		params, dfdUpload, fileName, fileType, fileFormat;

	fileType = this.file.type;
	// eslint-disable-next-line unicorn/prefer-string-slice
	fileFormat = this.file.name.substring( this.file.name.indexOf( '.' ) + 1 );
	fileName = this.targetTitle.getValue() + '.' + fileFormat;

	params = {
		filename: fileName,
		format: fileType,
		ignorewarnings: false
	};

	params = this.sanitizeFilename( params );
	params = this.preprocessParams( params );

	dfdUpload = this.doUpload( me.file, params );

	dfdUpload.done( function ( resp ) {
		me.insertMedia( params.filename, resp.upload.imageinfo.url, me.file );
		dfd.resolve.apply( me );
	} ).fail( function ( error ) {
		if ( error === 'fileexists-no-change' || error === 'duplicate' || error === 'exists' ) {
			me.handleErrors( error, arguments, params.filename, me.fragment );
			dfd.resolve.apply( me );
		} else {
			dfd.reject(
				[ new OO.ui.Error( error, { recoverable: true } ) ]
			);
		}
	} );

	return new OO.ui.Process( dfd.promise(), this );
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.doUpload = function ( file, params ) {
	var mwApi = new mw.Api(),
		dfd = new $.Deferred();
	mwApi.upload( file, params ).done( function ( resp ) {
		dfd.resolve( resp );
	} ).fail( function ( error, result ) {
		var warnings = [],
			errorMessage = mw.message( 'enhancedupload-upload-error-unhandled' ).plain();

		if ( result.error !== undefined ) {
			dfd.reject( result.error.info, result );
		}
		if ( arguments[ 1 ] && arguments[ 1 ].upload && arguments[ 1 ].upload.warnings ) {
			warnings = arguments[ 1 ].upload.warnings;
		}
		if ( 'exists' in warnings || 'exists-normalized' in warnings ) {
			errorMessage = 'exists';
			if ( 'nochange' in warnings ) {
				errorMessage = 'fileexists-no-change';
			}
		} else if ( 'duplicate' in warnings ) {
			errorMessage = 'duplicate';
		} else if ( 'duplicate-archive' in warnings ) {
			errorMessage = mw.message( 'enhancedupload-upload-error-duplicate', params.filename ).plain();
		} else if ( 'badfilename' in warnings ) {
			errorMessage = mw.message( 'enhancedupload-upload-error-badfilename', params.filename ).plain();
		}
		dfd.reject( errorMessage, result );
	} );

	return dfd.promise();
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.insertMedia =
	function ( fileName, url, file, fragment ) {
		var title, annotationTitle, surfaceModel;
		// eslint-disable-next-line no-restricted-syntax
		if ( file.type.includes( 'image' ) ) {
			if ( !fragment ) {
				fragment = this.getFragment();
			}
			title = mw.Title.newFromText( 'File:' + fileName );
			var config = require( './insertMediaConfig.json' );
			var alignConfig = config.imagesAlignment;
			var typeConfig = config.imagesType;
			var heightConfig = config.imagesHeight;
			if ( heightConfig !== 'auto' ) {
				heightConfig = parseInt( heightConfig );
			}
			var widthConfig = config.imagesWidth;
			if ( widthConfig !== 'auto' ) {
				widthConfig = parseInt( widthConfig );
			}
			var isDefaultSize = false;
			if ( heightConfig === 'auto' && widthConfig === 'auto' ) {
				isDefaultSize = true;
			}
			this.imageModel = ve.dm.MWImageModel.static.newFromImageAttributes(
				{
					src: url,
					href: './' + title.getPrefixedText(),
					width: widthConfig,
					height: heightConfig,
					resource: title.getPrefixedText(),
					mediaType: file.type,
					type: typeConfig,
					align: alignConfig,
					defaultSize: isDefaultSize
				},
				fragment.getDocument()
			);
			this.imageModel.insertImageNode( fragment );
		} else {
			title = mw.Title.newFromText( 'Media:' + fileName );

			surfaceModel = ve.init.target.getSurface().getModel();
			fragment = surfaceModel.getFragment();

			annotationTitle = ve.dm.MWInternalLinkAnnotation.static.newFromTitle(
				title, title.getPrefixedText()
			);

			fragment.insertContent( title.getMainText(), true );
			fragment.annotateContent( 'set', 'link/mwInternal', annotationTitle.element );
		}
	};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.insertExistingMedia =
	function ( fragment, fileName ) {
		var me = this,
			url = '', imageInfoApi = new mw.Api(), apiParams, title;
		// eslint-disable-next-line no-restricted-syntax
		if ( this.file.type.includes( 'image' ) ) {
			title = mw.Title.newFromText( 'File:' + fileName );
			apiParams = {
				action: 'query',
				format: 'json',
				prop: 'imageinfo',
				iiprop: 'url',
				titles: title.getPrefixedText()
			};

			imageInfoApi.get( apiParams ).done( function ( data ) {
				var pages = data.query.pages, p;
				for ( p in pages ) {
					url = pages[ p ].imageinfo[ 0 ].url;
				}
				me.insertMedia( fileName, url, me.file, fragment );
			} );
		} else {
			me.insertMedia( fileName, url, me.file );
		}
	};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.handleErrors =
// eslint-disable-next-line no-shadow-restricted-names
	function ( error, arguments, fileName, fragment ) {
		var me = this,
			dfd = $.Deferred();
		if ( error === 'fileexists-no-change' ) {
			me.insertExistingMedia( fragment, fileName );
			dfd.resolve.apply( me );
		}
		if ( error === 'duplicate' ) {
			var origFileName = arguments[ 1 ].upload.warnings.duplicate[ 0 ];
			OO.ui.confirm(
				mw.message( 'enhancedupload-ve-dialog-duplicate-confirm' ).plain() )
				.done( function ( confirmed ) {
					if ( confirmed ) {
						me.insertExistingMedia( fragment, origFileName );
						dfd.resolve.apply( me );
					}
				} );
		}
		if ( error === 'exists' ) {
			OO.ui.prompt(
				mw.message( 'enhancedupload-ve-dialog-title-exists' ).plain(),
				{
					textInput: {
						placeholder: Date.now()
					}
				} )
				.done( function ( result ) {
					/* eslint-disable-next-line unicorn/prefer-string-slice */
					var fileFormat = me.file.name.substring( me.file.name.indexOf( '.' ) + 1 ),
						newFileName = result + '.' + fileFormat,
						params, dfdReUpload;
					if ( result !== null ) {
						params = {
							filename: newFileName,
							format: me.file.type,
							ignorewarnings: true
						};

						params = this.sanitizeFilename( params );
						params = me.preprocessParams( params );
						dfdReUpload = me.doUpload( me.file, params );
						dfdReUpload.done( function ( resp ) {
							me.insertMedia(
								params.filename,
								resp.upload.imageinfo.url,
								me.file,
								fragment
							);
							// eslint-disable-next-line no-undef
							me.close( { action: action } );
							dfd.resolve.apply( me );
						} )
							// eslint-disable-next-line no-shadow-restricted-names
							.fail( function ( err, arguments ) {
								if ( err === 'fileexists-no-change' || err === 'duplicate' || err === 'exists' ) {
									me.handleErrors( err, arguments, params.filename, fragment );
									dfd.resolve.apply( me );
								} else {
									dfd.reject(
										[ new OO.ui.Error( err, { recoverable: false } ) ]
									);
								}
							} );
					}
				} );
		}
		return new OO.ui.Process( dfd.promise(), this );
	};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.sanitizeFilename = function ( params ) {
	// Split namespace prefix and filename
	var fileNameParts = params.filename.split( ':' );

	var partsLength = fileNameParts.length;

	params.prefix = '';
	for ( var i = 0; i < partsLength - 1; i++ ) {
		params.prefix += fileNameParts[ i ] + ':';
	}
	params.filename = fileNameParts[ partsLength - 1 ];

	// Replace invalid characters in filename
	params.filename = params.filename.replace( /\//g, '_' );

	return params;
};

enhancedUpload.ui.dialog.VEInsertMediaDialog.prototype.preprocessParams = function ( params ) {
	var paramsProcessor = { processor: new enhancedUpload.ParamsProcessor() };
	mw.hook( 'enhancedUpload.makeParamProcessor' ).fire( paramsProcessor );
	this.paramsProcessor = paramsProcessor.processor;

	var item = { name: params.filename };
	var skipOption = true;

	params = this.paramsProcessor.getParams( params, item, skipOption );

	return params;
};

/* Registration */
ve.ui.windowFactory.register( enhancedUpload.ui.dialog.VEInsertMediaDialog );
