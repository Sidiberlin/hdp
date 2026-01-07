bs.util.registerNamespace( 'ext.bookshelf.ui.data.node' );

ext.bookshelf.ui.data.node.BookTreeNode = function ( cfg ) {
	this.numberProcessor = new ext.bookshelf.ui.data.BookNumberProcessor();
	ext.bookshelf.ui.data.node.BookTreeNode.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.data.node.BookTreeNode, ext.menueditor.ui.data.node.TreeNode );

ext.bookshelf.ui.data.node.BookTreeNode.prototype.addLabel = function () {
	const iconWidget = new OO.ui.IconWidget( {
		icon: this.getIcon()
	} );

	this.$wrapper.append( iconWidget.$element );
	this.chapterNumberWidget = new OOJSPlus.ui.widget.LabelWidget( {
		classes: [ 'bs-book-tree-chapter-number' ],
		label: this.calculateChapterNumber()
	} );
	this.$wrapper.append( this.chapterNumberWidget.$element );
	this.labelWidget = new OOJSPlus.ui.widget.LabelWidget(
		Object.assign( {}, this.buttonCfg )
	);

	this.$wrapper.append( this.labelWidget.$element );
};

ext.bookshelf.ui.data.node.BookTreeNode.prototype.calculateChapterNumber = function () {
	return this.numberProcessor.calculateNumberForElement( this.tree.data, this.nodeData );
};

ext.bookshelf.ui.data.node.BookTreeNode.prototype.updateNumber = function ( number ) {
	this.chapterNumberWidget.setLabel( number );
};
