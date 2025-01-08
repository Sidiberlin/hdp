bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

require( './MetaDataOutlineWidget.js' );

ext.bookshelf.ui.widget.MetaDataLayout = function ( config ) {
	// Configuration initialization
	config = config || {};
	config.outlined = true;
	config.padded = true;
	config.expanded = false;
	// Parent constructor
	ext.bookshelf.ui.widget.MetaDataLayout.super.call( this, config );

	this.metaDataKeys = config.metaDataKeys;
	this.originData = config.originData;

	this.currentPageName = null;
	this.pages = [];
	this.ignoreFocus = false;
	this.stackLayout = new OO.ui.StackLayout( {
		continuous: true,
		padded: true,
		expanded: false
	} );
	this.setContentPanel( this.stackLayout );
	this.autoFocus = config.autoFocus === undefined || !!config.autoFocus;
	this.outlineVisible = false;

	this.outlineControlsWidget = null;
	this.outlineSelectWidget = new ext.bookshelf.ui.widget.MetaDataOutlineWidget( {
		metaDataKeys: this.metaDataKeys,
		metaData: this.originData
	} );

	this.outlinePanel = new OO.ui.PanelLayout( {
		expanded: false,
		scrollable: true
	} );
	this.setMenuPanel( this.outlinePanel );

	this.toggleMenu( true );

	// Events
	this.outlineSelectWidget.connect( this, {
		property_change: function( key ) {
			var page = this.pages[ key ];
			var isVisible = page.isVisible();
			page.toggle( !isVisible );
		}
	} );

	// Initialization
	this.$element.addClass( 'bookshelf-metadata-layout' );
	this.stackLayout.$element.addClass( 'bookshelf-metadata-stackLayout' );

	this.outlinePanel.$element
		.addClass( 'bookshelf-metadata--outlinePanel' )
		.append( this.outlineSelectWidget.$element );
};

OO.inheritClass( ext.bookshelf.ui.widget.MetaDataLayout, OO.ui.MenuLayout );

ext.bookshelf.ui.widget.MetaDataLayout.prototype.addItems = function ( items ) {
	for( var i in items ) {
		this.pages[ items[ i ].key ] = items[i];
	}
	this.outlineSelectWidget.addItems( items );
	this.stackLayout.addItems( items );
};
