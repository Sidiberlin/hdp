ext.datetimetools.ce.DateTimeToolsNode = function () {
	// Parent constructor
	ext.datetimetools.ce.DateTimeToolsNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.datetimetools.ce.DateTimeToolsNode, ve.ce.MWInlineExtensionNode );

/* Static properties */

ext.datetimetools.ce.DateTimeToolsNode.static.name = 'datetime';

ext.datetimetools.ce.DateTimeToolsNode.static.primaryCommandName = 'datetime';

// If body is empty, tag does not render anything
ext.datetimetools.ce.DateTimeToolsNode.static.rendersEmpty = false;

/* Registration */

ve.ce.nodeFactory.register( ext.datetimetools.ce.DateTimeToolsNode );
