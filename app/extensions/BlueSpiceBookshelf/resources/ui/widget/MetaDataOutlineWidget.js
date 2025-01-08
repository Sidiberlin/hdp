bs.util.registerNamespace( 'ext.bookshelf.ui.widget' );

require( './MetaDataOutlineSelectWidget.js' );
require( './MetaDataOutlineItemWidget.js' );

ext.bookshelf.ui.widget.MetaDataOutlineWidget = function ( config ) {
	ext.bookshelf.ui.widget.MetaDataOutlineWidget.super.call( this, config );
	this.metaKeys = config.metaDataKeys || [];
	this.metaData = config.metaData || [];

	this.dataList = new ext.bookshelf.ui.widget.MetaDataOutlineSelectWidget( {} );
	this.$element.append( this.dataList.$element );
};

OO.inheritClass( ext.bookshelf.ui.widget.MetaDataOutlineWidget, OO.ui.Widget );

ext.bookshelf.ui.widget.MetaDataOutlineWidget.prototype.addItems = function ( items ) {
	var index = this.dataList.items.length;
	for ( var i in items ) {
		var item = this.createSelectItemWidget( items[i] );
		if ( item.active ) {
			this.dataList.selectItem( item );
		}
		item.connect( this, {
			property_selection: function ( key, state ) {
				if ( state ) {
					this.dataList.selectItem( item );
				} else {
					this.dataList.unselectItem( item );
				}
				this.emit( 'property_change', key );
			}
		} );
		this.dataList.addItems( [ item ], index );
		index++;
	}
};

ext.bookshelf.ui.widget.MetaDataOutlineWidget.prototype.createSelectItemWidget = function ( item ) {
	return new ext.bookshelf.ui.widget.MetaDataOutlineItemWidget( {
		active: item.active,
		label: item.getOutlineLabel(),
		key: item.key
	} );
};
