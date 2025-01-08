ext.simpletasks.dm.TaskReportNode = function () {
	// Parent constructor
	ext.simpletasks.dm.TaskReportNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.simpletasks.dm.TaskReportNode, ve.dm.MWInlineExtensionNode );

/* Static members */

ext.simpletasks.dm.TaskReportNode.static.name = 'taskreport';

ext.simpletasks.dm.TaskReportNode.static.tagName = 'taskreport';

// Name of the parser tag
ext.simpletasks.dm.TaskReportNode.static.extensionName = 'taskreport';

// This tag renders without content
ext.simpletasks.dm.TaskReportNode.static.childNodeTypes = [];
ext.simpletasks.dm.TaskReportNode.static.isContent = false;

/* Registration */

ve.dm.modelRegistry.register( ext.simpletasks.dm.TaskReportNode );
