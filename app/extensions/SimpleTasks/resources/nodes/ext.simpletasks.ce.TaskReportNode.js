ext.simpletasks.ce.TaskReportNode = function () {
	// Parent constructor
	ext.simpletasks.ce.TaskReportNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.simpletasks.ce.TaskReportNode, ve.ce.MWInlineExtensionNode );

/* Static properties */

ext.simpletasks.ce.TaskReportNode.static.name = 'taskreport';

ext.simpletasks.ce.TaskReportNode.static.primaryCommandName = 'taskreport';

// If body is empty, tag does not render anything
ext.simpletasks.ce.TaskReportNode.static.rendersEmpty = false;

/* Registration */

ve.ce.nodeFactory.register( ext.simpletasks.ce.TaskReportNode );
