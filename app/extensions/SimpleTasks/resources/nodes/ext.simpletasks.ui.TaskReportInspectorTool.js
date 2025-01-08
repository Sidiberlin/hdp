ext.simpletasks.ui.TaskReportInspectorTool = function ( toolGroup, config ) {
	ext.simpletasks.ui.TaskReportInspectorTool.super.call( this, toolGroup, config );
};

OO.inheritClass( ext.simpletasks.ui.TaskReportInspectorTool, ve.ui.FragmentInspectorTool );

ext.simpletasks.ui.TaskReportInspectorTool.static.name = 'taskReportTool';
ext.simpletasks.ui.TaskReportInspectorTool.static.group = 'none';
ext.simpletasks.ui.TaskReportInspectorTool.static.autoAddToCatchall = false;
ext.simpletasks.ui.TaskReportInspectorTool.static.icon = 'taskreport';
ext.simpletasks.ui.TaskReportInspectorTool.static.title = mw.message( 'simple-tasks-taskreport-inspector-title' ).text();
ext.simpletasks.ui.TaskReportInspectorTool.static.modelClasses = [
	ext.simpletasks.dm.TaskReportNode
];
ext.simpletasks.ui.TaskReportInspectorTool.static.commandName = 'taskReportCommand';

ve.ui.toolFactory.register( ext.simpletasks.ui.TaskReportInspectorTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'taskReportCommand', 'window', 'open',
		{ args: [ 'taskReportInspector' ], supportedSelections: [ 'linear' ] }
	)
);
