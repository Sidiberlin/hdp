ext.menueditor.ui.data.tree.MediawikiSidebarTree = function ( cfg ) {
	ext.menueditor.ui.data.tree.MediawikiSidebarTree.parent.call( this, cfg );
};

// eslint-disable-next-line max-len
OO.inheritClass( ext.menueditor.ui.data.tree.MediawikiSidebarTree, ext.menueditor.ui.data.tree.Tree );

// eslint-disable-next-line max-len
ext.menueditor.ui.data.tree.MediawikiSidebarTree.prototype.getPossibleNodesForLevel = function ( lvl ) {
	switch ( lvl ) {
		case 0:
			return [ 'menu-raw-text' ];
		case 1:
			return [ 'menu-two-fold-link-spec', 'mediawiki-sidebar-keyword' ];
		default:
			return [];
	}
};

ext.menueditor.ui.data.tree.MediawikiSidebarTree.prototype.getMaxLevels = function () {
	return 2;
};
