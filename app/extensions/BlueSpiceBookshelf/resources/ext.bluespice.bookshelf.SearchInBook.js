bs.util.registerNamespace( 'bs.bookshelf.tag' );
bs.bookshelf.tag.SearchInBookDefinition = function () {
	bs.bookshelf.tag.SearchInBookDefinition.super.call( this );
};

OO.inheritClass( bs.bookshelf.tag.SearchInBookDefinition, bs.extendedSearch.vec.util.tag.TagSearchDefinition );

bs.bookshelf.tag.SearchInBookDefinition.prototype.getCfg = function () {
	const cfg = bs.bookshelf.tag.SearchInBookDefinition.parent.prototype.getCfg.call( this );
	return $.extend( cfg, { // eslint-disable-line no-jquery/no-extend
		classname: 'SearchInBook',
		name: 'searchinbook',
		tagname: 'bs:searchinbook',
		descriptionMsg: 'bs-bookshelf-droplet-search-description',
		menuItemMsg: 'bs-bookshelf-droplet-search-name',
		attributes: [ {
			name: 'placeholder',
			labelMsg: 'bs-extendedsearch-tagsearch-ve-tagsearch-tb-placeholder',
			helpMsg: 'bs-extendedsearch-tagsearch-ve-tagsearch-tb-placeholder-help',
			type: 'text',
			default: '',
			placeholderMsg: 'bs-extendedsearch-tagsearch-ve-tagsearch-tb-placeholder-placeholder'
		}, {
			name: 'book',
			labelMsg: 'bs-bookshelf-droplet-search-book',
			helpMsg: 'bs-bookshelf-droplet-search-book-help',
			type: 'custom',
			widgetClass: ext.bookshelf.ui.widget.BookInputWidget,
			default: ''
		} ]
	} );
};

bs.vec.registerTagDefinition(
	new bs.bookshelf.tag.SearchInBookDefinition()
);
