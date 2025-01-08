bs.util.registerNamespace( 'ext.bookshelf.ui.tools' );

ext.bookshelf.ui.tools.MassAddTool = function () {
	ext.bookshelf.ui.tools.MassAddTool.super.apply( this, arguments );
};

OO.inheritClass( ext.bookshelf.ui.tools.MassAddTool, OO.ui.Tool );
ext.bookshelf.ui.tools.MassAddTool.static.name = 'massAdd';
ext.bookshelf.ui.tools.MassAddTool.static.icon = 'articles';
ext.bookshelf.ui.tools.MassAddTool.static.title = mw.message( 'bs-bookshelfui-extjs-tooltip-add-mass' ).text();
ext.bookshelf.ui.tools.MassAddTool.static.label = mw.message( 'bs-bookshelfui-extjs-tooltip-add-mass' ).text();
ext.bookshelf.ui.tools.MassAddTool.static.flags = [];
ext.bookshelf.ui.tools.MassAddTool.static.displayBothIconAndLabel = true;

ext.bookshelf.ui.tools.MassAddTool.prototype.onSelect = function () {
	this.setActive( false );
	mw.loader.using( 'ext.bookshelf.massadd.dialog' ).done( function () {
		if ( !this.windowManager ) {
			this.windowManager = new OO.ui.WindowManager( {
				modal: true
			} );
			$( document.body ).append( this.windowManager.$element );
		}
		var dialog = new ext.bookshelf.ui.dialog.MassAddDialog();
		this.windowManager.addWindows( [ dialog ] );
		this.windowManager.openWindow( dialog );
		dialog.on( 'mass_add_pages', function ( pages ) {
			this.toolbar.emit( 'mass_add_pages', pages );
		}.bind( this ) );
		this.toolbar.emit( 'updateState' );
	}.bind( this ) );
};

ext.bookshelf.ui.tools.MassAddTool.prototype.onUpdateState = function () {};
