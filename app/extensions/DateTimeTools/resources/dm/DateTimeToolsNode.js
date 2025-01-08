ext.datetimetools.dm.DateTimeToolsNode = function () {
	// Parent constructor
	ext.datetimetools.dm.DateTimeToolsNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.datetimetools.dm.DateTimeToolsNode, ve.dm.MWInlineExtensionNode );

/* Static members */

ext.datetimetools.dm.DateTimeToolsNode.static.name = 'datetime';

ext.datetimetools.dm.DateTimeToolsNode.static.tagName = 'datetime';

// Name of the parser tag
ext.datetimetools.dm.DateTimeToolsNode.static.extensionName = 'datetime';

// This tag renders without content
ext.datetimetools.dm.DateTimeToolsNode.static.childNodeTypes = [];
ext.datetimetools.dm.DateTimeToolsNode.static.isContent = true;

/* Registration */

ve.dm.modelRegistry.register( ext.datetimetools.dm.DateTimeToolsNode );
