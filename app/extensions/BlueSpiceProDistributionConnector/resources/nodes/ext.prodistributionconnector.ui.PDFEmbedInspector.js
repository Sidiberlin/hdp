ext = ext || {};
ext.prodistributionconnector = ext.prodistributionconnector || {};
ext.prodistributionconnector.ui = ext.prodistributionconnector.ui || {};

ext.prodistributionconnector.ui.PDFEmbedInspector = function ( config ) {
	// Parent constructor
	ext.prodistributionconnector.ui.PDFEmbedInspector.super.call( this, ve.extendObject( { padded: true }, config ) );
};

/* Inheritance */

OO.inheritClass( ext.prodistributionconnector.ui.PDFEmbedInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ext.prodistributionconnector.ui.PDFEmbedInspector.static.name = 'pdfInspector';

ext.prodistributionconnector.ui.PDFEmbedInspector.static.title = OO.ui.deferMsg( 'bs-pro-distribution-pdfembed-inspector-title' );

ext.prodistributionconnector.ui.PDFEmbedInspector.static.modelClasses = [ ext.prodistributionconnector.dm.PDFEmbedNode ];

ext.prodistributionconnector.ui.PDFEmbedInspector.static.dir = 'ltr';

// This tag does not have any content
ext.prodistributionconnector.ui.PDFEmbedInspector.static.allowedEmpty = true;
ext.prodistributionconnector.ui.PDFEmbedInspector.static.selfCloseEmptyBody = false;

/**
 * @inheritdoc
 */
ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.initialize = function () {
	ext.prodistributionconnector.ui.PDFEmbedInspector.super.prototype.initialize.call( this );
	// set to invisible: have to keep to be able to set content of node
	// but content should be selected from file search
	this.input.toggle( false );

	this.indexLayout = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.createFields();

	this.setLayouts();

	// Initialization
	this.$content.addClass( 'pdfembed-inspector-content' );

	this.indexLayout.$element.append(
		this.inputLayout.$element,
		this.widthLayout.$element,
		this.heightLayout.$element,
		this.pageLayout.$element
	);
	this.form.$element.append(
		this.indexLayout.$element
	);
};

ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.createFields = function () {
	this.inputPDF = new OOJSPlus.ui.widget.FileSearchWidget( {
		extensions: ['pdf']
	} );
	this.inputPDF.on( 'change', function () {
		let value = this.inputPDF.getValue();
		if ( value.indexOf( 'File:') === -1 ) {
			value = 'File:' + value;
		}
		this.input.setValue( value );
	}.bind( this) );

	this.widthInput = new OO.ui.TextInputWidget( {
		placeholder: '500'
	} );
	this.heightInput = new OO.ui.TextInputWidget( {
		placeholder: '300'
	} );
	this.pageInput = new OO.ui.TextInputWidget( {
		placeholder: '1'
	} );
};

ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.setLayouts = function () {
	this.inputLayout = new OO.ui.FieldLayout( this.inputPDF, {
		label: mw.message( 'bs-pro-distribution-pdfembed-inspector-input-label' ).plain(),
		help: mw.message( 'bs-pro-distribution-pdfembed-inspector-input-help' ).plain()
	} );

	this.widthLayout = new OO.ui.FieldLayout( this.widthInput, {
		label: mw.message( 'bs-pro-distribution-pdfembed-inspector-width-label' ).plain(),
		help: mw.message( 'bs-pro-distribution-pdfembed-inspector-width-help' ).plain()
	} );

	this.heightLayout = new OO.ui.FieldLayout( this.heightInput, {
		label: mw.message( 'bs-pro-distribution-pdfembed-inspector-height-label' ).plain(),
		help: mw.message( 'bs-pro-distribution-pdfembed-inspector-height-help' ).plain()
	} );

	this.pageLayout = new OO.ui.FieldLayout( this.pageInput, {
		label: mw.message( 'bs-pro-distribution-pdfembed-inspector-page-label' ).plain(),
		help: mw.message( 'bs-pro-distribution-pdfembed-inspector-page-help' ).plain()
	} );

};

/**
 * @inheritdoc
 */
ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.getSetupProcess = function ( data ) {
	this.updateSize();
	return ext.prodistributionconnector.ui.PDFEmbedInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode.getAttribute( 'mw' ).attrs;
			if ( this.input.getValue() !== '' ) {
				this.inputPDF.setValue( this.input.getValue() );
			}
			this.widthInput.setValue( attributes.width || '' );
			this.heightInput.setValue( attributes.height || '' );
			this.pageInput.setValue( attributes.page || '' );
			this.actions.setAbilities( { done: true } );
		}, this );
};

ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.updateMwData = function ( mwData ) {
	ext.prodistributionconnector.ui.PDFEmbedInspector.super.prototype.updateMwData.call( this, mwData );

	if ( this.widthInput.getValue() !== '' ) {
		mwData.attrs.width = this.widthInput.getValue();
	} else {
		delete ( mwData.attrs.width );
	}

	if ( this.heightInput.getValue() !== '' ) {
		mwData.attrs.height = this.heightInput.getValue();
	} else {
		delete ( mwData.attrs.height );
	}

	if ( this.pageInput.getValue() !== '' ) {
		mwData.attrs.page = this.pageInput.getValue();
	} else {
		delete ( mwData.attrs.page );
	}
};

/**
 * @inheritdoc
 */
ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.formatGeneratedContentsError = function ( $element ) {
	return $element.text().trim();
};

/**
 * Append the error to the current tab panel.
 */
ext.prodistributionconnector.ui.PDFEmbedInspector.prototype.onTabPanelSet = function () {
	this.indexLayout.getCurrentTabPanel().$element.append( this.generatedContentsError.$element );
};

/* Registration */

ve.ui.windowFactory.register( ext.prodistributionconnector.ui.PDFEmbedInspector );
