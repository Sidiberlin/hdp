ext.datetimetools.ui.DateTimeToolsInspectorTool = function ( toolGroup, config ) {
	ext.datetimetools.ui.DateTimeToolsInspectorTool.super.call( this, toolGroup, config );
};

OO.inheritClass( ext.datetimetools.ui.DateTimeToolsInspectorTool, ve.ui.FragmentInspectorTool );

ext.datetimetools.ui.DateTimeToolsInspectorTool.static.name = 'datetimeTool';
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.group = 'meta';
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.autoAddToCatchall = false;
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.icon = 'calendar';
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.title = mw.message( 'datetimetools-ve-inspector-title' ).plain();
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.modelClasses =
	[ ext.datetimetools.dm.DateTimeToolsNode ];
ext.datetimetools.ui.DateTimeToolsInspectorTool.static.commandName = 'datetimeCommand';

ve.ui.toolFactory.register( ext.datetimetools.ui.DateTimeToolsInspectorTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'datetimeCommand', 'window', 'open',
		{ args: [ 'datetimeInspector' ], supportedSelections: [ 'linear' ] }
	)
);

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'datetimeFromSequence', 'window', 'toggle',
		{ args: [ 'datetimeInspector' ], supportedSelections: [ 'linear' ] }
	)
);

ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'dateTime', 'datetimeFromSequence', [ ' ', '/', '/' ], 2 )
);

ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'dateTimeNewline', 'datetimeFromSequence', [ { type: 'paragraph' }, '/', '/' ], 2 )
);
