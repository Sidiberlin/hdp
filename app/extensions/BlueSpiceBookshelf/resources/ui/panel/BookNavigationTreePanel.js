bs.util.registerNamespace( 'ext.bookshelf.ui.panel' );

require( './../../data/BookNavigationTreeStore.js' );
require( './../data/tree/BookNavigationTree.js' );

ext.bookshelf.ui.panel.BookNavigationTreePanel = function ( cfg ) {
	cfg.store = new ext.bookshelf.data.BookNavigationTreeStore( {
		path: 'bookshelf/navigation'
	} );
	ext.bookshelf.ui.panel.BookNavigationTreePanel.parent.call( this, cfg );
};

OO.inheritClass( ext.bookshelf.ui.panel.BookNavigationTreePanel, OOJSPlus.ui.panel.NavigationTreePanel );

ext.bookshelf.ui.panel.BookNavigationTreePanel.prototype.setupTree = function () {
	this.$treeCnt = $( '<div>' ).addClass(
		'oojsplus-panel-nav-tree-cnt' );
	this.$element.append( this.$treeCnt );

	const pageName = mw.config.get( 'wgPageName' );
	const ns = mw.config.get( 'wgCanonicalNamespace' );

	let root = pageName;
	if ( ns === '' ) {
		root = ':' + pageName;
	}

	this.store.getExpandedPath( root, [ root ] ).done( ( data ) => {
		this.pages = data;
		if ( $( document ).find( '#' + this.skeletonID ) ) {
			$( '#' + this.skeletonID ).empty();
		}
		this.updatePages();
	} );
};

ext.bookshelf.ui.panel.BookNavigationTreePanel.prototype.updatePages = function () {
	this.$treeCnt.children().remove();

	const pageTree = new ext.bookshelf.ui.data.tree.BookNavigationTree( {
		style: {
			IconExpand: 'next',
			IconCollapse: 'expand'
		},
		data: this.pages,
		allowDeletions: false,
		allowAdditions: false,
		store: this.store,
		includeRedirect: false
	} );
	this.$treeCnt.append( pageTree.$element );
};
