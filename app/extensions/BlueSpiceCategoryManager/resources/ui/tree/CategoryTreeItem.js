bs.util.registerNamespace( 'bs.categoryManager.ui.tree' );

require( './../dialog/RenameCategory.js' );

bs.categoryManager.ui.tree.CategoryTreeItem = function ( cfg ) {
	cfg = cfg || {};
	cfg.style.IconExpand = 'next';
	cfg.style.IconCollapse = 'expand';
	cfg.classes = cfg.classes || [];
	this.catText = cfg.label || '';
	this.catTitle = mw.Title.newFromText( this.catText, bs.ns.NS_CATEGORY );
	cfg.href = this.catTitle.getUrl();

	bs.categoryManager.ui.tree.CategoryTreeItem.parent.call( this, cfg );
	this.expanded = cfg.expanded;
	this.children = [];
};

OO.inheritClass( bs.categoryManager.ui.tree.CategoryTreeItem, OOJSPlus.ui.data.tree.Item );

bs.categoryManager.ui.tree.CategoryTreeItem.prototype.possiblyAddOptions = function () {
	const options = [];
	this.editBtn = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'bs-categorymanager-category-item-rename-label' ).text(),
		icon: 'edit'
	} );
	this.editBtn.connect( this, {
		click: 'onEditClick'
	} );
	options.push( this.editBtn.$element );

	this.viewButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'bs-categorymanager-category-item-view-label' ).text(),
		icon: 'eye',
		href: this.catTitle.getUrl()
	} );
	options.push( this.viewButton );

	this.removeNodeBtn = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'bs-categorymanager-category-item-delete-label' ).text(),
		icon: 'trash',
		flags: [
			'destructive'
		]
	} );
	this.removeNodeBtn.connect( this, {
		click: 'onRemoveClick'
	} );
	options.push( this.removeNodeBtn );

	this.optionsPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		scrollable: false,
		framed: false,
		content: options
	} );

	this.optionsPopup = new OO.ui.PopupButtonWidget( {
		icon: 'ellipsis',
		framed: false,
		classes: [ 'tree-item-options-btn' ],
		popup: {
			$content: this.optionsPanel.$element,
			width: 'auto',
			align: 'forwards',
			classes: [ 'tree-item-options-popup' ]
		}
	} );

	this.$wrapper.append( this.optionsPopup.$element );
};

bs.categoryManager.ui.tree.CategoryTreeItem.prototype.onEditClick = function () {
	this.optionsPopup.popup.toggle( false );
	this.tree.editCategory( this.catText );
};

bs.categoryManager.ui.tree.CategoryTreeItem.prototype.onRemoveClick = function () {
	this.optionsPopup.popup.toggle( false );
	this.tree.removeCategory( this.catText );
};
