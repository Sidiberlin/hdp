( function( mw, $, d, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.dm' );

	bs.bookshelf.dm.BookNavNode = function BsBookshelfDmBookNavNode() {
		// Parent constructor
		bs.bookshelf.dm.BookNavNode.super.apply( this, arguments );
	};

	/* Inheritance */

	OO.inheritClass( bs.bookshelf.dm.BookNavNode, ve.dm.MWInlineExtensionNode );

	/* Static members */

	bs.bookshelf.dm.BookNavNode.static.name = 'booknav';

	bs.bookshelf.dm.BookNavNode.static.tagName = 'booknav';

	// Name of the parser tag
	bs.bookshelf.dm.BookNavNode.static.extensionName = 'booknav';


	// This tag renders without content
	bs.bookshelf.dm.BookNavNode.static.childNodeTypes = [];
	bs.bookshelf.dm.BookNavNode.static.isContent = false;


	/* Registration */

	ve.dm.modelRegistry.register( bs.bookshelf.dm.BookNavNode );

})( mediaWiki, jQuery, document, blueSpice );
