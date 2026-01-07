bs.util.registerNamespace( 'ext.bookshelf.ui.data.tree' );

ext.bookshelf.ui.data.tree.BookNavigationTreeItem = function ( cfg ) {
	cfg = cfg || {};
	ext.bookshelf.ui.data.tree.BookNavigationTreeItem.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.data.tree.BookNavigationTreeItem, OOJSPlus.ui.data.tree.NavigationTreeItem );

ext.bookshelf.ui.data.tree.BookNavigationTreeItem.prototype.addLabel = function () {
	if ( this.buttonCfg.href === '' ) {
		this.labelWidget = new OOJSPlus.ui.widget.LabelWidget(
			Object.assign( {},
				{
					framed: false,
					icon: this.getIcon()
				}, this.buttonCfg )
		);
		this.$wrapper.append( this.labelWidget.$element );
	} else {
		ext.bookshelf.ui.data.tree.BookNavigationTreeItem.parent.prototype.addLabel.call( this );
	}
	this.addChapterNumber();
};

ext.bookshelf.ui.data.tree.BookNavigationTreeItem.prototype.addChapterNumber = function () {
	this.$chapterNumber = $( '<span>' ).addClass( 'bs-chapter-number' );
	this.$chapterNumber.text( this.buttonCfg.number );
	this.labelWidget.$element.prepend( this.$chapterNumber );
};
