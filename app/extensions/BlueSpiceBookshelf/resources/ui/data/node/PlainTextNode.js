bs.util.registerNamespace( 'ext.bookshelf.ui.data.node' );

ext.bookshelf.ui.data.node.PlainTextNode = function ( cfg ) {
	ext.bookshelf.ui.data.node.PlainTextNode.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.data.node.PlainTextNode, ext.bookshelf.ui.data.node.BookTreeNode );

ext.bookshelf.ui.data.node.PlainTextNode.static.canHaveChildren = true;

ext.bookshelf.ui.data.node.PlainTextNode.prototype.labelFromData = function ( data ) {
	return data.text;
};

ext.bookshelf.ui.data.node.PlainTextNode.prototype.getIcon = function () {
	return 'textLanguage';
};

// eslint-disable-next-line no-unused-vars
ext.bookshelf.ui.data.node.PlainTextNode.prototype.getFormFields = function ( dialog ) {
	return [
		{
			name: 'text',
			type: 'text',
			required: true,
			label: mw.message( 'bs-bookshelf-chapter-plain-text-input-label' ).text(),
			help: ''
		}
	];
};
