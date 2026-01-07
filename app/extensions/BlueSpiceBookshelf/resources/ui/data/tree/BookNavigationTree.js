bs.util.registerNamespace( 'ext.bookshelf.ui.data.tree' );

require( './BookNavigationTreeItem.js' );

ext.bookshelf.ui.data.tree.BookNavigationTree = function ( cfg ) {
	ext.bookshelf.ui.data.tree.BookNavigationTree.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.data.tree.BookNavigationTree, OOJSPlus.ui.data.NavigationTree );

ext.bookshelf.ui.data.tree.BookNavigationTree.prototype.createItemWidget = function (
	item, lvl, isLeaf, labelledby, expanded ) {
	return new ext.bookshelf.ui.data.tree.BookNavigationTreeItem( Object.assign( {}, {
		level: lvl,
		leaf: isLeaf,
		tree: this,
		labelledby: labelledby,
		expanded: expanded,
		style: this.style
	}, item ) );
};

ext.bookshelf.ui.data.tree.BookNavigationTree.prototype.prepareData = function ( pages ) {
	const data = ext.bookshelf.ui.data.tree.BookNavigationTree.parent.prototype.prepareData.call( this, pages );
	for ( const i in data ) {
		data[ i ].number = pages[ i ].number;
	}
	return data;
};

ext.bookshelf.ui.data.tree.BookNavigationTree.prototype.expandNode = function ( name ) {
	const node = this.getItem( name );
	if ( !node ) {
		return;
	}

	this.store.setActiveChapterNumber( node.buttonCfg.number );
	ext.bookshelf.ui.data.tree.BookNavigationTree.parent.prototype.expandNode.call( this, name );
};
