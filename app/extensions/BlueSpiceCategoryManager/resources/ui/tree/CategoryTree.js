bs.util.registerNamespace( 'bs.categoryManager.ui.tree' );

require( './CategoryTreeItem.js' );

bs.categoryManager.ui.tree.CategoryTree = function ( cfg ) {
	cfg = cfg || {};
	cfg.classes = [ 'bs-categorymanager-tree' ];
	bs.categoryManager.ui.tree.CategoryTree.parent.call( this, cfg );
	this.rootNode = cfg.store.rootNode;
};

OO.inheritClass( bs.categoryManager.ui.tree.CategoryTree, OOJSPlus.ui.data.StoreTree );

bs.categoryManager.ui.tree.CategoryTree.prototype.createItemWidget = function (
	item, lvl, isLeaf, labelledby, expanded ) {
	return new bs.categoryManager.ui.tree.CategoryTreeItem( Object.assign( {}, {
		level: lvl,
		leaf: isLeaf,
		tree: this,
		labelledby: labelledby,
		expanded: expanded,
		style: this.style
	}, item ) );
};

bs.categoryManager.ui.tree.CategoryTree.prototype.editCategory = function ( category ) {
	this.emit( 'edit', category );
};

bs.categoryManager.ui.tree.CategoryTree.prototype.removeCategory = function ( category ) {
	this.emit( 'delete', category );
};

bs.categoryManager.ui.tree.CategoryTree.prototype.reload = function () {
	this.load( this.rootNode ).done( ( data ) => {
		this.data = data;
		this.loaded = true;
		this.draw( this.build( this.data ) );
		this.emit( 'loaded' );
	} ).fail( ( response ) => {
		this.emit( 'load-fail', response );
	} );
};

bs.categoryManager.ui.tree.CategoryTree.prototype.onDrop = function ( $targetList, item, previous, parent, crossDrop ) { // eslint-disable-line no-unused-vars
	const movedCategory = item.catText;
	if ( item.level === 0 && parent === null ) {
		return;
	}

	const parentCategoryText = ( parent && parent.catText ) || '';
	const oldParentText = this.getOldParentText( item );
	this.emit( 'move', movedCategory, parentCategoryText, oldParentText );
};

bs.categoryManager.ui.tree.CategoryTree.prototype.getOldParentText = function ( item ) {
	let parentText = '';
	const nameParts = item.getName().split( '/' );
	if ( nameParts.length > 2 ) {
		parentText = nameParts[ nameParts.length - 2 ];
	}
	return parentText;
};

bs.categoryManager.ui.tree.CategoryTree.prototype.doDraw = function ( items, parent, labelledby, expanded ) { // eslint-disable-line no-unused-vars
	const $ul = $( '<ul>' ).addClass( 'tree-node-list' );
	$ul.attr( 'id', this.idGenerator.generate() );
	$ul.attr( 'role', 'tree' );

	if ( labelledby ) {
		$ul.attr( 'aria-labelledby', labelledby );
	}

	if ( !this.fixed ) {
		const tree = this;
		$ul.attr( 'data-level', parent ? parent.getLevel() + 1 : 0 );
		$ul.addClass( 'tree-sortable' ).sortable( {
			connectWith: '.tree-sortable',
			containment: this.$containTo,
			placeholder: 'drop-target',
			forceHelperSize: true,
			items: '.oojs-ui-data-tree-item',
			forcePlaceholderSize: true,
			start: function ( e, ui ) {
				tree.$itemsContainer.addClass( 'in-drag' );
				$( ui.item ).addClass( 'dragged' );
				tree.onDragStart( tree.flat[ $( ui.item ).data( 'name' ) ], $( this ), e, ui ); // eslint-disable-line es-x/no-array-prototype-flat
			},
			over: function ( e, ui ) {
				tree.onDragOver( $( this ), e, ui );
			},
			out: function ( e, ui ) {
				tree.onDragOut( $( this ), e, ui );
			},
			stop: function ( e, ui ) {
				tree.$itemsContainer.removeClass( 'in-drag' );
				$( ui.item ).removeClass( 'dragged' );
				tree.onDragStop( tree.flat[ $( ui.item ).data( 'name' ) ], $( this ), e, ui ); // eslint-disable-line es-x/no-array-prototype-flat
			},
			receive: function ( e, ui ) {
				// When dropping to another level
				tree._onListUpdate( $( this ), e, ui, true ); // eslint-disable-line no-underscore-dangle
			},
			remove: function ( e, ui ) { // eslint-disable-line no-unused-vars
				const $source = $( this ),
					$parent = $source.data( 'level' ) === 0 ? null : $source.parent( 'li.oojs-ui-data-tree-item' );

				if ( $parent ) {
					tree.reEvaluateParent( $parent.data( 'name' ) );
				}
			}
		} ).disableSelection();
	}
	for ( const name in items ) {
		if ( !items.hasOwnProperty( name ) ) {
			continue;
		}
		if ( !parent ) {
			$ul.addClass( 'tree-root' );
		}
		const $li = items[ name ].widget.$element;
		const $labelEl = $( $li ).find( '> div > .oojsplus-data-tree-label' );
		const itemId = $labelEl.attr( 'id' );
		// expanded as true necessary to allow drop in child list
		$li.append( this.doDraw( items[ name ].children || {}, items[ name ].widget, itemId, true ) );
		$ul.append( $li );
		// Once we add children, re-evaluate parent
		this.reEvaluateParent( name );
	}

	// hide all lists to have them collpased at first but have empty lists expanded for drop
	if ( $( $ul )[ 0 ].childElementCount > 0 && !$( $ul ).hasClass( 'tree-root' ) ) {
		$ul.hide();
	}

	return $ul;
};
