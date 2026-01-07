bs.util.registerNamespace( 'ext.bookshelf.ui.data.tree' );

ext.bookshelf.ui.data.tree.BookEditorTree = function ( cfg ) {
	cfg.classes = [ 'bs-book-tree' ];
	ext.bookshelf.ui.data.tree.BookEditorTree.parent.call( this, cfg );
	this.numberProcessor = new ext.bookshelf.ui.data.BookNumberProcessor();
	this.bookTitle = mw.config.get( 'wgRelevantPageName' );
	this.metadataManager = new ext.bookshelf.data.BookMetaDataManager( this.bookTitle );

	this.connect( this, {
		nodeRemoved: 'updateNodeNumbers',
		nodeAdded: 'updateNodeNumbers'
	} );

	mw.hook( 'menueditor.toolbar' ).add( ( menuToolbar ) => {
		this.metadataManager.load().done( ( data ) => {
			menuToolbar.toolbar.data = data;
		} );
		menuToolbar.toolbar.connect( this, {
			metadataset: function ( metadata ) {
				this.metadataManager.setData( metadata );
				menuToolbar.toolbar.data = metadata;
			},
			mass_add_pages: function ( pages ) { // eslint-disable-line camelcase
				this.buildLinks( pages );
			}
		} );
	} );
};

OO.inheritClass( ext.bookshelf.ui.data.tree.BookEditorTree, ext.menueditor.ui.data.tree.Tree );

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.getPossibleNodesForLevel = function ( lvl ) { // eslint-disable-line no-unused-vars
	return [ 'bs-bookshelf-chapter-wikilink-with-alias', 'bs-bookshelf-chapter-plain-text' ];
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.getMaxLevels = function () {
	return 100;
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.createItemWidget = function ( item, lvl, isLeaf ) {
	let classname = ext.menueditor.registry.node.registry[ item.type ];
	if ( !classname ) {
		// eslint-disable-next-line no-console
		console.error( 'Node of type ' + item.type + ' is not registered' );
		throw new Error();
	}

	classname = ext.menueditor.util.callbackFromString( classname );
	const maxLevels = this.getMaxLevels();
	// eslint-disable-next-line new-cap
	return new classname( {
		name: this.randomName( item.type ),
		level: lvl,
		tree: this,
		nodeData: item,
		leaf: isLeaf,

		allowAdditions: this.allowAdditions && ( maxLevels ? lvl + 1 < maxLevels : true ) && classname.static.canHaveChildren,
		allowEdits: this.editable,
		allowDeletions: this.allowDeletions
	} );
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.onDragStop = function ( item, $target, e, ui ) {
	ext.bookshelf.ui.data.tree.BookEditorTree.parent.prototype.onDragStop.call( this, item, $target, e, ui );
	this.updateNodeNumbers();
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.getNodes = function () {
	let nodes = ext.menueditor.ui.data.tree.Tree.parent.prototype.getNodes.call( this );
	nodes = nodes.map( ( e ) => $.extend( e.getNodeData(), { level: e.getLevel() + 1, name: e.getName() } ) ); // eslint-disable-line no-jquery/no-extend

	return { nodes: nodes, metadata: this.metadataManager.getData() };
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.updateNodeNumbers = function () {
	const allNodes = this.getNodes();
	const data = allNodes.nodes;
	const numberings = this.numberProcessor.calculateNumbersFromList( data );

	if ( numberings.length === 0 ) {
		return;
	}

	for ( const i in data ) {
		const name = data[ i ].name;
		const item = this.flat[ name ]; // eslint-disable-line es-x/no-array-prototype-flat
		item.updateNumber( numberings[ i ] );
	}
};

ext.bookshelf.ui.data.tree.BookEditorTree.prototype.buildLinks = function ( pages ) {
	pages.forEach( ( page ) => {
		this.addSubnodeWithData( {
			type: 'bs-bookshelf-chapter-wikilink-with-alias',
			target: page.prefixed_text,
			label: ''
		}, '' );
	} );
};
