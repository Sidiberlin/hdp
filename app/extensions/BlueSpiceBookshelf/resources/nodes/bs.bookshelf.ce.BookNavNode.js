( function ( mw, $, d, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.ce' );

	bs.bookshelf.ce.BookNavNode = function BsBookshelfCeBookNavNode() {
		// Parent constructor
		bs.bookshelf.ce.BookNavNode.super.apply( this, arguments );
	};

	/* Inheritance */

	OO.inheritClass( bs.bookshelf.ce.BookNavNode, ve.ce.MWInlineExtensionNode );

	/* Static properties */

	bs.bookshelf.ce.BookNavNode.static.name = 'booknav';

	bs.bookshelf.ce.BookNavNode.static.primaryCommandName = 'booknav';

	// If body is empty, tag does not render anything
	bs.bookshelf.ce.BookNavNode.static.rendersEmpty = true;

	/**
	 * @inheritdoc bs.bookshelf.ce.GeneratedContentNode
	 */
	bs.bookshelf.ce.BookNavNode.prototype.validateGeneratedContents = function ( $element ) {
		if ( $element.is( 'div' ) && $element.children( '.bsErrorFieldset' ).length > 0 ) {
			return false;
		}
		return true;
	};

	/* Registration */
	ve.ce.nodeFactory.register( bs.bookshelf.ce.BookNavNode );

}( mediaWiki, jQuery, document, blueSpice ) );
