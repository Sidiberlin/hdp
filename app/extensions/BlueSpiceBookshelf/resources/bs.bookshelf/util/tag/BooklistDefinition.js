bs.util.registerNamespace( 'bs.bookshelf.util.tag' );
bs.bookshelf.util.tag.BooklistDefinition = function BsVecUtilTagBooklistDefinition() {
	bs.bookshelf.util.tag.BooklistDefinition.super.call( this );
};

OO.inheritClass( bs.bookshelf.util.tag.BooklistDefinition, bs.vec.util.tag.Definition );

bs.bookshelf.util.tag.BooklistDefinition.prototype.getCfg = function() {
	var cfg = bs.bookshelf.util.tag.BooklistDefinition.super.prototype.getCfg.call( this );
	return $.extend( cfg, {
		classname : 'Booklist',
		name: 'booklist',
		tagname: 'bs:booklist',
		descriptionMsg: 'bs-bookshelf-tag-booklist-description',
		menuItemMsg: 'bs-bookshelf-ve-booklistinspector-title',
		attributes: [{
			name: 'filter',
			labelMsg: 'bs-bookshelf-ve-booklist-attr-filter-label',
			helpMsg: 'bs-bookshelf-ve-booklist-attr-filter-help',
			type: 'text'
		}]
	});
};

bs.vec.registerTagDefinition(
	new bs.bookshelf.util.tag.BooklistDefinition()
);
