bs.util.registerNamespace( 'bs.bookshelf.util.tag' );
bs.bookshelf.util.tag.BookshelfDefinition = function BsVecUtilTagBookshelfDefinition() {
	bs.bookshelf.util.tag.BookshelfDefinition.super.call( this );
};

OO.inheritClass( bs.bookshelf.util.tag.BookshelfDefinition, bs.vec.util.tag.Definition );

bs.bookshelf.util.tag.BookshelfDefinition.prototype.getCfg = function() {
	var cfg = bs.bookshelf.util.tag.BookshelfDefinition.super.prototype.getCfg.call( this );
	return $.extend( cfg, {
		classname : 'Bookshelf',
		name: 'bookshelf',
		tagname: 'bs:bookshelf',
		descriptionMsg: 'bs-bookshelf-tag-bookshelf-box-desc',
		menuItemMsg: 'bs-bookshelf-ve-bookshelfinspector-title',
		attributes: [{
			name: 'book',
			labelMsg: 'bs-bookshelf-ve-bookshelf-attr-book-label',
			helpMsg: 'bs-bookshelf-ve-bookshelf-attr-book-help',
			type: 'text'
		}]
	});
};

bs.vec.registerTagDefinition(
	new bs.bookshelf.util.tag.BookshelfDefinition()
);
