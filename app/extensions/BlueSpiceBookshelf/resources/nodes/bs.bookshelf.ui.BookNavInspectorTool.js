( function( mw, $, d, bs ) {
	bs.util.registerNamespace( 'bs.bookshelf.ui' );

	bs.bookshelf.ui.BookNavInspectorTool = function BsBookshelfUiBookNavInspectorTool( toolGroup, config ) {
		bs.bookshelf.ui.BookNavInspectorTool.super.call( this, toolGroup, config );
	};
	OO.inheritClass( bs.bookshelf.ui.BookNavInspectorTool, ve.ui.FragmentInspectorTool );
	bs.bookshelf.ui.BookNavInspectorTool.static.name = 'booknavTool';
	bs.bookshelf.ui.BookNavInspectorTool.static.group = 'none';
	bs.bookshelf.ui.BookNavInspectorTool.static.autoAddToCatchall = false;
	bs.bookshelf.ui.BookNavInspectorTool.static.icon = 'bluespice';
	bs.bookshelf.ui.BookNavInspectorTool.static.title = OO.ui.deferMsg( 'bs-bookshelf-booknav-title' );
	bs.bookshelf.ui.BookNavInspectorTool.static.modelClasses = [ bs.bookshelf.dm.BookNavNode ];
	bs.bookshelf.ui.BookNavInspectorTool.static.commandName = 'booknavCommand';
	ve.ui.toolFactory.register( bs.bookshelf.ui.BookNavInspectorTool );

	ve.ui.commandRegistry.register(
		new ve.ui.Command(
			'booknavCommand', 'window', 'open',
			{ args: [ 'booknavInspector' ], supportedSelections: [ 'linear' ] }
		)
	);

})( mediaWiki, jQuery, document, blueSpice );
