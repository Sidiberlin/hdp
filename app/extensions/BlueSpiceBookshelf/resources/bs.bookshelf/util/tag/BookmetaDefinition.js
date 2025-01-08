bs.util.registerNamespace( 'bs.bookshelf.util.tag' );
bs.bookshelf.util.tag.BookmetaDefinition = function BsVecUtilTagBookmetaDefinition() {
	bs.bookshelf.util.tag.BookmetaDefinition.super.call( this );
};

OO.inheritClass( bs.bookshelf.util.tag.BookmetaDefinition, bs.vec.util.tag.Definition );

bs.bookshelf.util.tag.BookmetaDefinition.prototype.getCfg = function() {
	var cfg = bs.bookshelf.util.tag.BookmetaDefinition.super.prototype.getCfg.call( this );
	return $.extend( cfg, {
		classname : 'Bookmeta',
		name: 'bookmeta',
		tagname: 'bs:bookmeta',
		descriptionMsg: 'bs-bookshelf-tag-bookmeta-description',
		menuItemMsg: 'bs-bookshelf-ve-bookmetainspector-title',
		attributes: [{
			name: 'title',
			labelMsg: 'bs-bookshelf-ve-bookmeta-attr-title-label',
			helpMsg: 'bs-bookshelf-ve-bookmeta-attr-title-help',
			type: 'text',
			default: 'Installation manual'
		},{
			name: 'subtitle',
			labelMsg: 'bs-bookshelf-ve-bookmeta-attr-subtitle-label',
			helpMsg: 'bs-bookshelf-ve-bookmeta-attr-subtitle-help',
			type: 'text',
			default: 'BlueSpice pro'
		},{
			name: 'author',
			labelMsg: 'bs-bookshelf-ve-bookmeta-attr-author-label',
			helpMsg: 'bs-bookshelf-ve-bookmeta-attr-author-help',
			type: 'text',
			default: 'Hallo Welt!'
		},{
			name: 'version',
			labelMsg: 'bs-bookshelf-ve-bookmeta-attr-version-label',
			helpMsg: 'bs-bookshelf-ve-bookmeta-attr-version-help',
			type: 'text',
			default: '1.0'
		}]
	});
};

bs.vec.registerTagDefinition(
	new bs.bookshelf.util.tag.BookmetaDefinition()
);
