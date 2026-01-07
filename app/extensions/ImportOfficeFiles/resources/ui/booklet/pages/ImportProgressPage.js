officeimport.ui.ImportProgressPage = function ( name, cfg ) {
	officeimport.ui.ImportProgressPage.parent.call( this, name, cfg );

	this.progressBar = new OO.ui.ProgressBarWidget( {
		progress: 0
	} );

	this.$status = $( '<span>' ).addClass( 'import-progress-status' );

	this.fieldLayout = new OO.ui.FieldLayout( this.progressBar, {
		label: mw.message( 'importofficefiles-ui-import-in-progress' ).plain(),
		align: 'top',
		classes: [ 'import-progress-layout' ]
	} );

	this.currentStep = 0;
	this.steps = [
		'import-images-step',
		'import-pages-step',
		'remove-files-step'
	];

	/**
	 * Process status API calls are executed with some intervals.
	 * If current process step is finished - then the API call to
	 * proceed to the next step is executed.
	 *
	 * And there can be a case when one API call was not finished yet, but another one starts.
	 * It may cause some problems, like when we try to proceed to the next step twice in a row.
	 * So we need to know if process status API call is running at the moment, to execute them
	 * strictly one by one.
	 *
	 * @type {boolean}
	 */
	this.isStatusApiCallRunning = false;

	this.progressBarValue = 0;
	this.progressBarStep = 33;

	this.updateProgressUI();

	this.$element.append( this.$status );
	this.$element.append( this.fieldLayout.$element );
};

OO.inheritClass( officeimport.ui.ImportProgressPage, OO.ui.PageLayout );

/**
 * @param {Array} pages
 * @param {string} title
 * @private
 */
officeimport.ui.ImportProgressPage.prototype.onImportDone = function ( pages, title ) {
	const namespaceIds = mw.config.get( 'wgNamespaceIds' );
	const namespaceKey = namespaceIds.mediawiki;
	const params = { prefix: 'Page collection' };

	mw.hook( 'importOffice.collectionPrefix' ).fire( params );

	const pageTitle = params.prefix + '/' + title;
	this.targetTitle = mw.Title.newFromText( pageTitle, namespaceKey );

	const linklist = this.getPageList( pages );

	let pageCollectionCreated = true;

	this.exportList( linklist ).fail( () => {
		// Error code we catch here:
		// protectednamespace-interface
		pageCollectionCreated = false;
	} ).always( () => {
		this.setImportDoneUI( pageCollectionCreated, pages[ 0 ] );
	} );
};

/**
 * Starts import process.
 *
 * This process is split to several steps, which are executed on background one by one.
 * To check import status and proceed to the next step (if available) see:
 * {@link officeimport.ui.ImportProgressPage.checkImportStatus}
 *
 * @param {string} uploadId ID of workspace directory, where file was uploaded to
 * @param {string} fileName Uploaded filename
 * @param {Object} data Some additional data/configuration
 */
officeimport.ui.ImportProgressPage.prototype.startImport = function ( uploadId, fileName, data ) {
	mw.loader.using( [ 'ext.importofficefiles.api' ], () => {
		const api = new officeimport.api.Api();
		api.startImport( uploadId, fileName, data ).done( ( response ) => {
			if ( response.processId ) {
				this.emit( 'importRunning', response.processId, fileName );
			} else {
				// Import process failed to start
				this.emit( 'importFailed', 'Import process failed to start', response.error );
			}
		} ).fail( ( error ) => {
			// Probably API is unreachable currently, or there is some fatal
			this.emit( 'importFailed', 'Import process failed to start', error );
		} );
	} );
};

/**
 * Checks status of import process and proceed to the next process step if previous was finished.
 *
 * Process is split to several process steps, which are executed one be one.
 * If process is terminated (which means that all steps were executed) - then import is done.
 *
 * @param {string} processId Process UUID
 * @param {number} timer Timer ID, used to turn it off in case of errors or if process was finished
 */
officeimport.ui.ImportProgressPage.prototype.checkImportStatus = function ( processId, timer ) {
	this.updateProgressUI();

	if ( this.isStatusApiCallRunning ) {
		return;
	}

	mw.loader.using( [ 'ext.importofficefiles.api' ], () => {
		this.isStatusApiCallRunning = true;

		const api = new officeimport.api.Api();
		api.getImportStatus( processId ).done( ( response ) => {
			if ( response.state === 'terminated' ) {
				if ( response.exitCode === 0 ) {
					this.emit( 'importDone', response.pid, timer );
				} else {
					// Wrong exit code after process termination, something went wrong
					this.emit( 'importFailed', 'Wrong exit code after process termination', response );
					clearInterval( timer );
				}
			} else if ( response.state === 'interrupted' ) {
				// If one process step is already finished, we should not check status again,
				// before next step will start
				// We will re-start this timer after next step starts
				// clearInterval( timer );

				if ( response.exitCode === 0 ) {
					this.emit( 'importDone', response.pid, timer );
				}

				if ( !response.output.lastStep ) {
					// No information about last step, it means that something went wrong
					this.emit( 'importFailed', 'No information about last step provided', response );
					clearInterval( timer );
				}

				this.progressBarValue += this.progressBarStep;

				// Even if the last step was already executed - we anyway need to proceed
				// with import process to correctly finish this process.
				// eslint-disable-next-line no-shadow
				api.importNextStep( processId ).done( ( response ) => {
					if ( !response.success ) {
						// One of the steps failed to start, we should not continue in such case
						this.emit( 'importFailed', response.error, response );
						clearInterval( timer );
					}

					this.isStatusApiCallRunning = false;

					// If this is the last step - we should not increment that
					if ( this.currentStep !== this.steps.length - 1 ) {
						this.currentStep++;
					}
				} ).fail( ( error ) => {
					// Some unexpected exception was thrown when one of steps tried to start
					this.emit( 'importFailed', 'Next process step failed to start', error );
					clearInterval( timer );
				} );
			} else {
				this.isStatusApiCallRunning = false;
			}
		} ).fail( ( error ) => {
			// Getting import process status failed
			this.emit( 'importFailed', 'Getting import process status failed', error );
		} );
	} );
};

/**
 * @private
 */
officeimport.ui.ImportProgressPage.prototype.updateProgressUI = function () {
	const statusMessageKey = 'importofficefiles-ui-progress-' + this.steps[ this.currentStep ];
	// The following messages are used here
	// * importofficefiles-ui-progress-import-images-step
	// * importofficefiles-ui-progress-import-pages-step
	// * importofficefiles-ui-progress-remove-files-step
	this.$status.text( mw.message( statusMessageKey ).text() );

	this.progressBar.setProgress( this.progressBarValue );
};

officeimport.ui.ImportProgressPage.prototype.setImportDoneUI = function ( pageCollectionCreated, pagezero ) {
	this.progressBar.setProgress( 100 );
	this.$status.text( '' );
	this.fieldLayout.toggle( false );

	const labelSuccess = new OO.ui.LabelWidget( {
		label: mw.message( 'importofficefiles-ui-import-completed' ).plain()
	} );

	this.$element.append( labelSuccess.$element );

	if ( pageCollectionCreated ) {
		const $link = $( '<a>' ).attr( 'href', this.targetTitle.getUrl() );
		$link.attr( 'title', this.targetTitle.getPrefixedText() );
		$link.html( this.targetTitle.getPrefixedText() );

		const $linkHtml = $( '<div>' ).addClass( 'import-list' ).append(
			mw.message( 'importofficefiles-ui-dialog-link-import-files-text', $link ).parse()
		);
		this.$element.append( $linkHtml );
	} else {
		const pageZeroTitle = mw.Title.newFromText( pagezero );
		const pageZeroUrl = pageZeroTitle.getUrl();
		const $link = $( '<a>' ).attr( 'href', pageZeroUrl );
		$link.attr( 'title', pagezero );
		$link.html( pagezero );
		const $errorLabel = $( '<div>' ).addClass( 'import-list' ).append(
			mw.message(
				'importofficefiles-ui-dialog-link-imported-page-text',
				$link
			).parse()
		);
		this.$element.append( $errorLabel );
	}
};

officeimport.ui.ImportProgressPage.prototype.getPageList = function ( pages ) {
	let formatContent = '';

	for ( let i = 0; i < pages.length; i++ ) {
		let link = '* ';
		link += '[[' + pages[ i ] + ']]\n';
		formatContent += link;
	}

	return formatContent;
};

officeimport.ui.ImportProgressPage.prototype.exportList = function ( linklist ) {
	const me = this;
	const dfd = $.Deferred();
	mw.loader.using( 'mediawiki.api' ).done( () => {
		const api = new mw.Api();
		api.postWithToken( 'csrf', {
			action: 'edit',
			title: me.targetTitle.getPrefixedText(),
			text: linklist
		} ).done( ( response ) => {
			dfd.resolve( response );
		} ).fail( ( code, err ) => {
			dfd.reject( code, err );
		} );
	} );

	return dfd.promise();
};
