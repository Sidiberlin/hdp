officeimport.ui.StructurePreviewPage = function ( name, cfg ) {
	officeimport.ui.StructurePreviewPage.parent.call( this, name, cfg );
	this.collapseElements = [];
	this.pageTitles = [];
	this.addPreviewDialog();

	const heading = new OO.ui.FieldLayout( new OO.ui.LabelWidget( {
		label: mw.message( 'importofficefiles-ui-dialog-preview-title' ).text(),
		classes: [ 'label-heading-page' ]
	} ) );

	const description = new OO.ui.FieldLayout( new OO.ui.LabelWidget( {
		label: mw.message( 'importofficefiles-ui-dialog-preview-label' ).text()
	} ) );

	this.$element.append( heading.$element );
	this.$element.append( description.$element );

	this.layout = new OO.ui.PanelLayout( {
		padded: true,
		framed: true,
		expanded: false,
		scrollable: true
	} );

	this.$element.append( this.layout.$element );
};

OO.inheritClass( officeimport.ui.StructurePreviewPage, OO.ui.PageLayout );

officeimport.ui.StructurePreviewPage.prototype.addPreviewDialog = function () {

	this.previewDialog = new officeimport.ui.PreviewDialog( {
		expanded: false,
		scrollable: true,
		padded: true,
		size: 'full'
	} );

	this.windowManager = new OO.ui.WindowManager();
	$( document.body ).append( this.windowManager.$element );

	this.windowManager.addWindows( [ this.previewDialog ] );
};

officeimport.ui.StructurePreviewPage.prototype.generatePagesStructure = function ( pages ) {
	// Do a bit clean up, in case if we went back to previous step and returned to this one.
	// If we don't do it - we may receive pages duplicates
	this.collapseElements = [];
	this.pageTitles = [];
	this.layout.$element.empty();

	for ( const title in pages ) {
		const page = pages[ title ];
		this.pageTitles.push( title );
		const $pageTitleWrap = $( '<div>' ).addClass( 'importoffice-page-title-wrap' );
		$pageTitleWrap.append( $( '<span>' ).html( title ) );

		const toggleButton = new OO.ui.ButtonWidget( {
			icon: 'expand',
			framed: false,
			classes: [ 'toggle-icon' ]
		} );

		const iconPreviewWidget = new OO.ui.IconWidget( {
			icon: 'eye',
			title: 'Preview'
		} );

		const fieldLayout = new OO.ui.FieldsetLayout( {
			items: [
				toggleButton,
				iconPreviewWidget
			]
		} );

		iconPreviewWidget.$element.data( 'page-title', title );

		iconPreviewWidget.$element.on( 'click', function ( e ) {
			const dataTitle = $( e.target ).data( 'page-title' );

			this.showPreview( dataTitle );
		}.bind( this ) );

		$pageTitleWrap.append( fieldLayout.$element );

		this.layout.$element.append( $pageTitleWrap );

		if ( page.files.length === 0 ) {
			toggleButton.toggle( false );
			continue;
		}

		const $fileList = $( '<ul>' );
		let i = '';
		for ( i in page.files ) {
			$fileList.append( $( '<li>' ).html( page.files[ i ] ) );
		}
		this.collapseElements.push( {
			button: toggleButton,
			list: $fileList
		} );
		$fileList.hide();
		this.layout.$element.append( $fileList );
	}

	for ( let i = 0; i < this.collapseElements.length; i++ ) {
		const button = this.collapseElements[ i ].button;
		button.connect( this, {
			click: [ 'onToggle', i ]
		} );
	}

};

officeimport.ui.StructurePreviewPage.prototype.showPreview = function ( title ) {
	const officeApi = new officeimport.api.Api();
	officeApi.getContent( this.uploadId, title ).done( function ( response ) {
		this.generateContentPreview( title, response.content );
	}.bind( this ) ).fail( function () {
		OO.ui.alert(
			'No content preview available'
		);
	} );
};

officeimport.ui.StructurePreviewPage.prototype.generateContentPreview =
	function ( title, content ) {
		const api = new mw.Api();

		api.postWithToken( 'csrf', {
			action: 'parse',
			prop: 'text',
			contentmodel: 'wikitext',
			text: content
		} ).done( function ( response ) {
			// eslint-disable-next-line no-prototype-builtins
			if ( !response.hasOwnProperty( 'parse' ) || !response.parse.hasOwnProperty( 'text' ) ) {
				// eslint-disable-next-line no-console
				console.log( 'Content was not parsed!' );
				return;
			}

			const parsedContent = response.parse.text[ '*' ];

			this.windowManager.openWindow( this.previewDialog, {
				title: title,
				content: parsedContent
			} );
			this.previewDialog.$element.trigger( 'focus' );
		}.bind( this ) )
			.fail( function () {
				// eslint-disable-next-line no-console
				console.log( 'Some error during "action=parse" API call' );
			} );
	};

officeimport.ui.StructurePreviewPage.prototype.onToggle = function ( index ) {
	if ( this.collapseElements[ index ].button.getIcon() === 'expand' ) {
		this.collapseElements[ index ].list.show();
		this.collapseElements[ index ].button.setIcon( 'collapse' );
		this.emit( 'changeContent' );
	} else {
		this.collapseElements[ index ].list.hide();
		this.collapseElements[ index ].button.setIcon( 'expand' );
		this.emit( 'changeContent' );
	}
};

// eslint-disable-next-line no-unused-vars
officeimport.ui.StructurePreviewPage.prototype.getPreview = function ( uploadId, config ) {
	this.uploadId = uploadId;
	mw.loader.using( [ 'ext.importofficefiles.api' ], function () {
		const api = new officeimport.api.Api();
		api.getPagesStructure( uploadId ).done( function ( response ) {
			this.generatePagesStructure( response.pages );
			this.emit( 'changeContent' );
		}.bind( this ) );
	}.bind( this ) );
};

officeimport.ui.StructurePreviewPage.prototype.getPageTitles = function () {
	return this.pageTitles;
};
