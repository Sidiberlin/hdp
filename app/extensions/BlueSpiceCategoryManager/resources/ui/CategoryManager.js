bs.util.registerNamespace( 'bs.categoryManager.ui' );

require( './tree/CategoryTree.js' );
require( './dialog/RenameCategory.js' );
require( './../api/CategoryActions.js' );
require( './toolbar/CategoryToolbar.js' );
require( './dialog/CreateCategory.js' );

bs.categoryManager.ui.CategoryManager = function ( cfg ) {
	cfg = cfg || {};
	bs.categoryManager.ui.CategoryManager.super.call( this, cfg );

	this.actionApi = new bs.categoryManager.api.CategoryActions();
	this.setupToolbar();
	this.setupTree();
};

OO.inheritClass( bs.categoryManager.ui.CategoryManager, OO.ui.Widget );

bs.categoryManager.ui.CategoryManager.prototype.setupToolbar = function () {
	this.toolbar = new bs.categoryManager.ui.toolbar.CategoryToolbar();
	this.toolbar.connect( this, {
		create: 'addCategory'
	} );
	this.$element.append( this.toolbar.$element );
};

bs.categoryManager.ui.CategoryManager.prototype.setupTree = function () {
	this.categoryTree = new bs.categoryManager.ui.tree.CategoryTree(
		{
			store: {
				action: 'bs-category-treestore',
				rootNode: 'src'
			},
			$containTo: null,
			allowDeletions: true,
			allowAdditions: true,
			fixed: false,
			expanded: false,
			id: 'category-manager-tree',
			style: {
				IconExpand: 'next',
				IconCollapse: 'expand'
			}
		}
	);
	this.categoryTree.connect( this, {
		edit: 'editCategory',
		delete: 'deleteCategory',
		move: 'moveCategory'
	} );

	this.$element.append( this.categoryTree.$element );
};

bs.categoryManager.ui.CategoryManager.prototype.moveCategory = function ( category, newParentCategory, oldParentCategory ) {
	this.actionApi.move( category, newParentCategory, oldParentCategory ).done( () => {
		this.categoryTree.reload();
		mw.notify( mw.message( 'bs-categorymanager-movecategory-confirm', category ).text() );
	} );
};

bs.categoryManager.ui.CategoryManager.prototype.editCategory = function ( category ) {
	if ( !this.windowManager ) {
		this.windowManager = new OO.ui.WindowManager( {
			modal: true
		} );
		$( document.body ).append( this.windowManager.$element );
	}
	const dialog = new bs.categoryManager.ui.dialog.RenameCategory( {
		category: category
	} );
	dialog.connect( this, {
		close: function () {
			this.categoryTree.reload();
		}
	} );
	this.windowManager.addWindows( [ dialog ] );
	this.windowManager.openWindow( dialog );
};

bs.categoryManager.ui.CategoryManager.prototype.deleteCategory = function ( category ) {
	bs.util.confirm( 'RemoveCategory', {
		text: mw.message( 'bs-categorymanager-removecategoryconfirm-text' ).text(),
		title: mw.message( 'bs-categorymanager-removecategoryconfirm-title' ).text()
	},
	{
		ok: () => {
			this.actionApi.deleteCategory( category ).done( () => {
				this.categoryTree.reload();
				mw.notify( mw.message( 'bs-categorymanager-removecategory-confirm', category ).text() );
			} );
		}
	} );
};

bs.categoryManager.ui.CategoryManager.prototype.addCategory = function () {
	let selectedCategory = '';
	if ( this.categoryTree.selectedItem ) {
		selectedCategory = this.categoryTree.selectedItem.catText;
	}

	if ( !this.windowManager ) {
		this.windowManager = new OO.ui.WindowManager( {
			modal: true
		} );
		$( document.body ).append( this.windowManager.$element );
	}
	const dialog = new bs.categoryManager.ui.dialog.CreateCategory( {
		selectedCategory: selectedCategory
	} );
	dialog.connect( this, {
		close: function () {
			this.categoryTree.reload();
		}
	} );
	this.windowManager.addWindows( [ dialog ] );

	this.windowManager.openWindow( dialog );
};
