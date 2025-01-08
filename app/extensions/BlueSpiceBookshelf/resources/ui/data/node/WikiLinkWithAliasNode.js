bs.util.registerNamespace( 'ext.bookshelf.ui.data.node' );

ext.bookshelf.ui.data.node.WikiLinkWithAliasNode = function ( cfg ) {
	ext.bookshelf.ui.data.node.WikiLinkWithAliasNode.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.data.node.WikiLinkWithAliasNode, ext.bookshelf.ui.data.node.BookTreeNode );

ext.bookshelf.ui.data.node.WikiLinkWithAliasNode.static.canHaveChildren = true;

ext.bookshelf.ui.data.node.WikiLinkWithAliasNode.prototype.labelFromData = function ( data ) {
	if ( data.label ) {
		return data.label;
	}
	return data.target;
};

// eslint-disable-next-line no-unused-vars
ext.bookshelf.ui.data.node.WikiLinkWithAliasNode.prototype.getIcon = function ( data ) {
	return 'wikiText';
};

// eslint-disable-next-line no-unused-vars
ext.bookshelf.ui.data.node.WikiLinkWithAliasNode.prototype.getFormFields = function ( dialog ) {
	return [
		{
			name: 'target',
			type: 'title',
			required: true,
			label: mw.message( 'bs-bookshelf-chapter-wikilink-with-alias-target-input-label' ).text(),
			help: ''
		},
		{
			name: 'label',
			type: 'text',
			label: mw.message( 'bs-bookshelf-chapter-wikilink-with-alias-text-input-label' ).text(),
			help: ''
		}
	];
};
