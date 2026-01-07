bs.util.registerNamespace( 'bs.categoryManager.ui.toolbar' );

bs.categoryManager.ui.toolbar.CategoryToolbar = function ( config ) {
	config = config || {};
	config.classes = [ 'bs-categorymanager-toolbar' ];
	bs.categoryManager.ui.toolbar.CategoryToolbar.super.call( this,
		new OO.ui.ToolFactory(), new OO.ui.ToolGroupFactory(), config
	);

	this.addNewItemTool();
	this.setup( [
		{
			name: 'create-action',
			type: 'bar',
			classes: [ 'toolbar-actions' ],
			include: [ 'create' ]
		}
	] );

	this.initialize();
	this.emit( 'updateState' );
};

OO.inheritClass( bs.categoryManager.ui.toolbar.CategoryToolbar, OO.ui.Toolbar );

bs.categoryManager.ui.toolbar.CategoryToolbar.prototype.addNewItemTool = function () {
	this.toolFactory.register( bs.categoryManager.ui.toolbar.CreateTool );
};

bs.categoryManager.ui.toolbar.CreateTool = function () {
	bs.categoryManager.ui.toolbar.CreateTool.super.apply( this, arguments );
	this.data = 'create';
};

OO.inheritClass( bs.categoryManager.ui.toolbar.CreateTool, OO.ui.Tool );

bs.categoryManager.ui.toolbar.CreateTool.static.name = 'create';
bs.categoryManager.ui.toolbar.CreateTool.static.icon = 'add';
bs.categoryManager.ui.toolbar.CreateTool.static.title = mw.message( 'bs-configmanager-tool-create-category-label' ).text(); // create category';

bs.categoryManager.ui.toolbar.CreateTool.static.flags = [ 'progressive' ];
bs.categoryManager.ui.toolbar.CreateTool.static.displayBothIconAndLabel = true;
bs.categoryManager.ui.toolbar.CreateTool.prototype.onSelect = function () {
	this.setActive( false );
	this.toolbar.emit( 'create' );
};
bs.categoryManager.ui.toolbar.CreateTool.prototype.onUpdateState = function () {};
