bs.util.registerNamespace( 'ext.bookshelf.ui.dialog' );

ext.bookshelf.ui.dialog.AddNewBookDialog = function ( config ) {
	config = config || {};
	ext.bookshelf.ui.dialog.AddNewBookDialog.super.call( this, config );
};
OO.inheritClass( ext.bookshelf.ui.dialog.AddNewBookDialog, OO.ui.ProcessDialog );

ext.bookshelf.ui.dialog.AddNewBookDialog.static.name = 'AddNewBookDialog';
ext.bookshelf.ui.dialog.AddNewBookDialog.static.title = mw.message( 'bs-bookshelf-newbook-dlg-title' ).text();

ext.bookshelf.ui.dialog.AddNewBookDialog.static.size = 'large';

ext.bookshelf.ui.dialog.AddNewBookDialog.static.actions = [
	{
		action: 'save',
		label: mw.message( 'bs-bookshelf-newbook-dlg-action-create-label' ).text(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		title: mw.message( 'cancel' ).text(),
		flags: [ 'safe', 'close' ]
	}
];

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.getSetupProcess = function ( data ) {
	return ext.bookshelf.ui.dialog.AddNewBookDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.saveAction = this.actions.getSpecial().primary;
			this.saveAction.setDisabled( true );
			this.uploadFile = false;
		} );
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.initialize = function () {
	ext.bookshelf.ui.dialog.AddNewBookDialog.super.prototype.initialize.apply( this, arguments );
	const bookshelfdata = require( './bookshelfdata.json' );
	const options = [];
	if ( bookshelfdata.length > 0 ) {
		bookshelfdata.forEach( ( val ) => {
			options.push( {
				data: val
			} );
		} );
	}
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.bookTitleInput = new OO.ui.TextInputWidget( {
		placeholder: mw.message( 'bs-bookshelf-newbook-dlg-input-title-placeholder' ).text(),
		required: true
	} );
	this.bookTitleInput.connect( this, {
		change: function () {
			if ( this.bookTitleInput.getValue().length > 0 ) {
				this.saveAction.setDisabled( false );
				return;
			}
			this.saveAction.setDisabled( true );
		}
	} );
	const bookTitleLayout = new OO.ui.FieldLayout( this.bookTitleInput, {
		label: mw.message( 'bs-bookshelf-newbook-dlg-input-title-label' ).text()
	} );

	this.subTitleInput = new OO.ui.TextInputWidget( {
		placeholder: mw.message( 'bs-bookshelf-newbook-dlg-input-subtitle-placeholder' ).text()
	} );
	const subTitleLayout = new OO.ui.FieldLayout( this.subTitleInput, {
		label: mw.message( 'bs-bookshelf-newbook-dlg-input-subtitle-label' ).text()
	} );

	this.bookshelfInput = new OO.ui.ComboBoxInputWidget( {
		options: options,
		$overlay: this.$overlay,
		placeholder: mw.message( 'bs-bookshelf-newbook-dlg-input-bookshelf-placeholder' ).text()
	} );
	const bookshelfLayout = new OO.ui.FieldLayout( this.bookshelfInput, {
		label: mw.message( 'bs-bookshelf-newbook-dlg-input-bookshelf-label' ).text()
	} );

	this.panel.$element.append( bookTitleLayout.$element );
	this.panel.$element.append( subTitleLayout.$element );
	this.panel.$element.append( bookshelfLayout.$element );
	this.addCoverImageLayout();
	mw.hook( 'bs.bookshelf.newbook.initPanel' ).fire( this.panel );
	this.$body.append( this.panel.$element );
	this.updateSize();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.addCoverImageLayout = function () {
	const imageLayout = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		classes: [ 'bs-bookshelf-new-book-image-panel' ]
	} );
	this.coverImageInput = new OO.ui.SelectFileWidget( {
		showDropTarget: true
	} );
	this.coverImageInput.connect( this, {
		change: function () {
			if ( this.coverImageInput.getFilename() === '' ) {
				this.coverImageFile.setDisabled( false );
			} else {
				this.coverImageFile.setDisabled( true );
			}
		}
	} );
	const coverImageLayout = new OO.ui.FieldLayout( this.coverImageInput, {
		label: mw.message( 'bs-bookshelf-newbook-dlg-input-image-label' ).text()
	} );
	this.coverImageFile = new OOJSPlus.ui.widget.FileSearchWidget( {
		placeholder: mw.message( 'bs-bookshelf-newbook-dlg-input-image-select-placeholder' ).text(),
		extensions: [ 'svg', 'png', 'jpg' ]
	} );
	const coverImageText = new OO.ui.FieldLayout( new OO.ui.LabelWidget( {
		label: mw.message( 'bs-bookshelf-newbook-dlg-cover-image-text' ).text()
	} ), {
		label: ' '
	} );
	this.coverImageFile.connect( this, {
		choose: function () {
			this.coverImageInput.setDisabled( true );
		},
		change: function () {
			if ( this.coverImageFile.getValue() === '' ) {
				this.coverImageInput.setDisabled( false );
			}
		}
	} );
	const coverImageFileLayout = new OO.ui.FieldLayout( this.coverImageFile, {
		label: ' '
	} );

	imageLayout.$element.append( coverImageLayout.$element );
	imageLayout.$element.append( coverImageText.$element );
	imageLayout.$element.append( coverImageFileLayout.$element );
	this.panel.$element.append( imageLayout.$element );
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.getActionProcess = function ( action ) {
	const dialog = this;
	if ( action ) {
		dialog.pushPending();
		dialog.bookData = dialog.getBookData();
		const uploadDfd = dialog.uploadImage();
		uploadDfd.done( () => dialog.makeSaveProcess() ).fail( function ( error ) {
			const handleDfd = dialog.handleUploadErrors( error, arguments );
			handleDfd.done( function ( result ) {
				if ( result === 'save' ) {
					return dialog.makeSaveProcess();
				}
				this.popPending();
			} ).fail( ( error ) => { // eslint-disable-line no-shadow
				dialog.showErrors( new OO.ui.Error( error, { recoverable: false } ) );
				return;
			} );
		} );
	}
	return ext.bookshelf.ui.dialog.AddNewBookDialog.super.prototype.getActionProcess.call( this, action );
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.makeSaveProcess = function () {
	const process = new OO.ui.Process( () => {
		const dfd = new $.Deferred();
		this.save().done( () => {
			dfd.resolve();
		} ).fail( ( error ) => {
			dfd.reject();
			this.showErrors( new OO.ui.Error( error, { recoverable: false } ) );
		} );
		return dfd.promise();
	} );
	mw.hook( 'bs.bookshelf.newbook.actionprocess' ).fire( process, this );
	process.next( () => {
		this.close( { action: 'done' } );
		this.emit( 'book_created', this.bookTitle );
	} );
	return process.execute();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.getBookData = function () {
	const bookdata = {};
	bookdata.title = this.bookTitleInput.getValue();

	const subtitleValue = this.subTitleInput.getValue();
	if ( subtitleValue.length > 0 ) {
		bookdata.subtitle = subtitleValue;
	}
	const bookshelf = this.bookshelfInput.getValue();
	if ( bookshelf.length > 0 ) {
		bookdata.bookshelf = bookshelf;
	}
	const file = this.coverImageInput.getFilename();
	if ( file.length > 0 ) {
		bookdata.bookshelfimage = 'File:' + file;
		this.uploadFile = true;
	}
	const filename = this.coverImageFile.getValue();
	if ( filename.length > 0 && !this.uploadFile ) {
		bookdata.bookshelfimage = filename;
	}

	return bookdata;
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.save = function () {
	const dfd = new $.Deferred();
	this.saveBook().done( () => {
		const saveMetadataDfd = this.saveMetadata();
		saveMetadataDfd.done( () => {
			this.popPending();
			dfd.resolve();
		} )
			.fail( () => {
				this.popPending();
				dfd.reject( [
					new OO.ui.Error(
						mw.message( 'bs-bookshelf-newbook-dlg-error-metadata-save' ).text(),
						{ recoverable: false }
					) ] );
			} );
	} ).fail( ( error ) => {
		this.popPending();
		let errorMsg = mw.message( 'bs-bookshelf-newbook-dlg-error-book-save' ).text();
		if ( error[ 0 ] === 'articleexists' ) {
			errorMsg = mw.message( 'bs-bookshelf-newbook-dlg-error-book-exists' ).text();
			this.bookTitleInput.setValidityFlag( false );
		} else {
			console.error( error ); // eslint-disable-line no-console
		}
		dfd.reject( errorMsg );
	} );
	return dfd.promise();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.saveBook = function () {
	const dfd = new $.Deferred();
	this.bookTitle = 'Book:' + this.bookData.title;
	mw.loader.using( 'mediawiki.api' ).done( () => {
		const mwApi = new mw.Api();
		mwApi.postWithToken( 'csrf', {
			action: 'edit',
			title: this.bookTitle,
			text: '',
			createonly: true,
			summary: mw.message( 'bs-bookshelf-newbook-dlg-book-save-summary' ).text(),
			contentmodel: 'book'
		} ).fail( function () {
			dfd.reject( arguments );
		} )
			.done( function () {
				dfd.resolve( arguments );
			} );
	} );
	return dfd.promise();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.saveMetadata = function () {
	const dfd = $.Deferred();
	mw.loader.using( [ 'bluespice.bookshelf.api' ] ).done( () => {
		const api = new ext.bookshelf.api.Api();
		api.saveBookMetadata( this.bookTitle, this.bookData ).done( () => {
			dfd.resolve();
		} ).fail( () => {
			dfd.reject();
		} );
	} );

	return dfd.promise();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.uploadImage = function () {
	const dfd = $.Deferred();
	if ( !this.bookData.hasOwnProperty( 'bookshelfimage' ) || !this.uploadFile ) {
		dfd.resolve();
	} else {
		mw.loader.using( [ 'mediawiki.api' ] ).done( () => {
			const file = this.coverImageInput.getValue();
			const mwApi = new mw.Api();
			const params = {
				filename: file.name,
				format: file.type,
				ignorewarnings: false
			};

			mwApi.upload( file, params ).done( () => {
				dfd.resolve();
			} ).fail( ( error, result ) => {
				const errorMessage = this.getErrorMsg( result );
				dfd.reject( errorMessage, result );
			} );
		} );
	}

	return dfd.promise();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.handleUploadErrors = function ( error, arguments ) { // eslint-disable-line no-shadow-restricted-names
	const dfd = new $.Deferred();
	if ( error === 'fileexists-no-change' ) {
		dfd.resolve( 'save' );
	} else if ( error === 'duplicate' ) {
		const origFileName = arguments[ 1 ].upload.warnings.duplicate[ 0 ];
		OO.ui.confirm(
			mw.message( 'bs-bookshelf-newbook-dlg-upload-duplicate-confirm-label' ).text() )
			.done( ( confirmed ) => {
				if ( confirmed ) {
					this.bookData.bookshelfimage = origFileName;
					dfd.resolve( 'save' );
				} else {
					this.coverImageInput.setValue( '' );
					delete ( this.bookData.bookshelfimage );
					this.saveAction.setDisabled( false );
					this.popPending();
					dfd.resolve( 'reset' );
				}
			} );
	} else if ( error === 'exists' ) {
		const dialog = this;
		OO.ui.prompt(
			mw.message( 'bs-bookshelf-newbook-dlg-upload-title-exists' ).text(),
			{
				textInput: {
					value: 'Cover-' + this.bookData.title
				}
			} )
			.done( ( result ) => {
				if ( result !== null ) {
					const file = dialog.coverImageInput.getValue();
					const fileFormat = dialog.bookData.bookshelfimage.slice(
						Math.max( 0, dialog.bookData.bookshelfimage.indexOf( '.' ) + 1 )
					);
					const newFileName = result + '.' + fileFormat;
					const mwApi = new mw.Api();
					const params = {
						filename: newFileName,
						format: file.type
					};
					mwApi.upload( file, params ).done( () => {
						dfd.resolve( 'save' );
					} ).fail( ( error, result ) => { // eslint-disable-line no-shadow
						const errorMessage = dialog.getErrorMsg( result );
						if ( errorMessage === 'fileexists-no-change' || errorMessage === 'duplicate' || errorMessage === 'exists' ) {
							const handledDdfd = dialog.handleUploadErrors( error, result );
							handledDdfd.done( () => {
								dfd.resolve( 'save' );
							} ).fail( ( error, result ) => { // eslint-disable-line no-shadow
								const errorMessage = dialog.getErrorMsg( result ); // eslint-disable-line no-shadow
								dialog.popPending();
								dfd.reject( new OO.ui.Error( errorMessage, { recoverable: false } ) );
							} );
						} else {
							dialog.popPending();
							dfd.reject( new OO.ui.Error( error, { recoverable: false } ) );
						}
					} );
				} else {
					this.popPending();
					dfd.reject();
				}
			} );
	} else {
		this.popPending();
		dfd.reject( new OO.ui.Error( error, { recoverable: false } ) );
	}

	return dfd.promise();
};

ext.bookshelf.ui.dialog.AddNewBookDialog.prototype.getErrorMsg = function ( result ) {
	if ( !result.hasOwnProperty( 'upload' ) ) {
		return 'No upload property during upload';
	}
	const upload = result.upload;
	if ( !upload.hasOwnProperty( 'warnings' ) ) {
		return 'No warnings during upload';
	}
	const warnings = result.upload.warnings;
	let errorMessage = mw.message( 'bs-bookshelf-newbook-dlg-upload-error-unhandled' ).text();
	if ( 'exists' in warnings || 'exists-normalized' in warnings ) {
		errorMessage = 'exists';
		if ( 'nochange' in warnings ) {
			errorMessage = 'fileexists-no-change';
		}
	} else if ( 'duplicate' in warnings ) {
		errorMessage = 'duplicate';
	} else if ( 'duplicate-archive' in warnings ) {
		errorMessage = mw.message( 'bs-bookshelf-newbook-dlg-upload-error-duplicate', upload.filename ).text();
	}
	return errorMessage;
};
