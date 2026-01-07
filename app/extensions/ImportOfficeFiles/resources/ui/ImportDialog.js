officeimport.ui.ImportDialog = function ( cfg ) {
	officeimport.ui.ImportDialog.super.call( this, cfg );
};

OO.inheritClass( officeimport.ui.ImportDialog, OO.ui.ProcessDialog );

officeimport.ui.ImportDialog.static.name = 'importDialog';
officeimport.ui.ImportDialog.static.title = mw.message( 'importofficefiles-ui-import-dialog-title' ).text();
officeimport.ui.ImportDialog.static.actions = [
	{
		action: 'next',
		label: mw.message( 'importofficefiles-ui-dialog-action-next' ).text(),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'SelectFile', 'Configuration' ]
	},
	{
		action: 'import',
		label: mw.message( 'importofficefiles-ui-dialog-action-import' ).text(),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'StructurePreview' ]
	},
	{
		action: 'done',
		label: mw.message( 'importofficefiles-ui-dialog-action-done' ).text(),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'ImportDone' ]
	},
	{
		action: 'back',
		label: mw.message( 'importofficefiles-ui-dialog-action-back' ).text(),
		flags: 'primary',
		modes: [ 'SelectFile', 'Configuration', 'StructurePreview' ]
	},
	{
		label: mw.message( 'importofficefiles-ui-dialog-action-cancel' ).text(),
		flags: 'safe',
		modes: [ 'SelectFile', 'Configuration', 'StructurePreview', 'ImportProgress', 'ImportDone' ]
	}
];

officeimport.ui.ImportDialog.prototype.initialize = function () {
	officeimport.ui.ImportDialog.super.prototype.initialize.apply( this, arguments );

	this.booklet = new officeimport.ui.ImportBooklet( {
		expanded: false,
		outlined: false,
		showMenu: false,
		$overlay: this.$overlay,
		// When auto-focus is enabled - for some reason after changing page is being set twice,
		// which is wrong and breaks stuff.
		// It can be fixed by disabling "autoFocus"
		autoFocus: false
	} );

	this.$body.append( this.booklet.$element );
};

/**
 * @private
 * @param {Array} fileArray
 */
officeimport.ui.ImportDialog.prototype.onFileSelected = function ( fileArray ) {
	if ( fileArray.length ) {
		this.actions.setAbilities( { next: true } );
	} else {
		this.actions.setAbilities( { next: false } );
	}
};

/**
 * @private
 * @param {string} name
 * @param {Object} data
 */
officeimport.ui.ImportDialog.prototype.switchPage = function ( name, data ) {
	const page = this.booklet.getPage( name );
	if ( !page ) {
		return;
	}

	// debugger;

	this.booklet.setPage( name );
	this.actions.setMode( name );
	this.popPending();

	switch ( name ) {
		case 'SelectFile':
			this.setSize( 'medium' );
			this.actions.setAbilities( { import: false, done: false, back: false } );

			page.connect( this, {
				fileSelected: 'onFileSelected'
			} );
			break;
		case 'Configuration':
			this.setSize( 'medium' );
			this.actions.setAbilities( { import: false, done: false, back: true, next: true } );
			page.setDefaultValue( this.fileName );
			page.connect( this, {
				titleValidityChanged: ( isValid ) => {
					this.actions.setAbilities( { next: isValid } );
				}
			} );
			break;
		case 'StructurePreview':
			this.setSize( 'larger' );
			page.getPreview( this.uploadId );
			this.actions.setAbilities( { import: true, done: false, back: true, next: false } );
			page.connect( this, {
				changeContent: function () {
					this.updateSize();
				}
			} );
			break;
		case 'ImportProgress':
			this.setSize( 'medium' );
			this.actions.setAbilities( { import: false, done: false, back: false, next: false } );

			page.startImport( this.uploadId, this.fileName, data.config );
			page.connect( this, {
				// eslint-disable-next-line no-unused-vars
				importRunning: function ( processId, fileName ) {
					const timer = setInterval( () => {
						page.checkImportStatus( processId, timer );
					}, 500 );
				},
				importDone: function ( processId, timer ) {
					clearInterval( timer );

					page.onImportDone( data.pageTitles, data.pageTitles[ 0 ] );
					this.actions.setMode( 'ImportDone' );
					this.actions.setAbilities( { done: true } );

					data.dfd.resolve();
				},
				importFailed: function ( error, importData ) {
					// eslint-disable-next-line no-console
					console.log( 'error', error );
					// eslint-disable-next-line no-console
					console.dir( importData );
					const errorObj = new OO.ui.Error(
						mw.message( 'importofficefiles-ui-import-error' ).text(),
						{ recoverable: false }
					);

					data.dfd.reject( errorObj );
				}
			} );

			break;
	}
};

/**
 * @private
 * @param {Object} data
 */
officeimport.ui.ImportDialog.prototype.switchNextPage = function ( data ) {
	const curPageName = this.booklet.getCurrentPageName();
	const curPageIndex = this.booklet.pagesOrder.indexOf( curPageName );

	const nextPageIndex = curPageIndex + 1;
	if ( nextPageIndex > ( this.booklet.pagesOrder.length - 1 ) ) {
		return;
	}

	const nextPageName = this.booklet.pagesOrder[ nextPageIndex ];

	this.switchPage( nextPageName, data );
};

/**
 * @private
 * @param {Object} data
 */
officeimport.ui.ImportDialog.prototype.switchPrevPage = function ( data ) {
	const curPageName = this.booklet.getCurrentPageName();
	const curPageIndex = this.booklet.pagesOrder.indexOf( curPageName );

	const prevPageIndex = curPageIndex - 1;
	if ( prevPageIndex < 0 ) {
		return;
	}

	const prevPageName = this.booklet.pagesOrder[ prevPageIndex ];

	this.switchPage( prevPageName, data );
};

officeimport.ui.ImportDialog.prototype.getSetupProcess = function ( data ) {
	return officeimport.ui.ImportDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			// Prevent flickering, disable all actions before init is done
			this.actions.setMode( 'INVALID' );
		}, this );
};

officeimport.ui.ImportDialog.prototype.getReadyProcess = function ( data ) {
	return officeimport.ui.ImportDialog.parent.prototype.getReadyProcess.call( this, data )
		.next(
			function () {
				this.switchPage( 'SelectFile', {} );
				this.actions.setAbilities( {
					import: false,
					done: false,
					back: false,
					next: false
				} );
			},
			this
		);
};

officeimport.ui.ImportDialog.prototype.getActionProcess = function ( action ) {
	return officeimport.ui.ImportDialog.parent.prototype.getActionProcess.call( this, action )
		.next(
			function () {
				const dfd = $.Deferred();
				if ( action === 'next' ) {
					this.pushPending();

					const page = this.booklet.getCurrentPage();
					let data = {};

					if ( page.name === 'SelectFile' ) {
						data = this.booklet.getCurrentPage().getData();

						page.uploadFile( data ).done( ( uploadId, fileName ) => {

							this.uploadId = uploadId;
							this.fileName = fileName;
							this.switchNextPage( {} );

							dfd.resolve();
						} ).fail( ( error, xhr ) => {
							this.popPending();

							// eslint-disable-next-line no-console
							console.log( 'error', error );

							const errorObj = new OO.ui.Error(
								xhr.responseJSON.message,
								{ recoverable: false }
							);

							dfd.reject( errorObj );
						} );
					}
					if ( page.name === 'Configuration' ) {
						data = this.booklet.getCurrentPage().getData();

						page.analyzeFile( this.uploadId, this.fileName, data )
							.done( ( processId, timer ) => {
								clearInterval( timer );

								this.processId = processId;
								this.targetTitle = data.title;
								this.switchNextPage( {} );

								dfd.resolve();
							} ).fail( ( error ) => {
								this.popPending();

								// eslint-disable-next-line no-console
								console.log( 'error', error );

								const msg = mw.message( 'importofficefiles-ui-analyze-error' ).text();
								const errorObj = new OO.ui.Error( msg, { recoverable: false } );

								dfd.reject( errorObj );
							} );
					}

					return dfd.promise();
				} else if ( action === 'import' ) {
					this.pushPending();

					const pageTitles = this.booklet.getCurrentPage().getPageTitles();
					const config = this.booklet.getPage( 'Configuration' ).getData();
					this.switchPage( 'ImportProgress', { pageTitles, config, dfd } );

					return dfd.promise();
				} else if ( action === 'done' ) {
					return this.close();
				} else if ( action === 'back' ) {
					this.pushPending();
					this.switchPrevPage( {} );
				}

				return officeimport.ui.ImportDialog.parent.prototype.getActionProcess.call(
					this,
					action
				);
			},
			this
		);
};

officeimport.ui.ImportDialog.prototype.getBodyHeight = function () {
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

officeimport.ui.ImportDialog.prototype.showErrors = function ( errors ) {
	officeimport.ui.ImportDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};
