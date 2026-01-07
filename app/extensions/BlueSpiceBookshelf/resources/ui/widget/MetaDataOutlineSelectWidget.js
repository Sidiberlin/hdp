bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

ext.bookshelf.ui.widget.MetaDataOutlineSelectWidget = function ( config ) {
	ext.bookshelf.ui.widget.MetaDataOutlineSelectWidget.super.call( this, $.extend( config, { // eslint-disable-line no-jquery/no-extend
		classes: [ 'bs-metadata-outline-select-widget' ],
		multiselect: true
	} ) );
};

OO.inheritClass( ext.bookshelf.ui.widget.MetaDataOutlineSelectWidget, OO.ui.SelectWidget );

ext.bookshelf.ui.widget.MetaDataOutlineSelectWidget.static.createItem = function ( config ) {
	return new ext.bookshelf.ui.widget.MetaDataOutlineItemWidget( config );
};
