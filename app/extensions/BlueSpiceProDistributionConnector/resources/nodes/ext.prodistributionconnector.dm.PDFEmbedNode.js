bs.util.registerNamespace( 'ext.prodistributionconnector.dm' );

ext.prodistributionconnector.dm.PDFEmbedNode = function () {
	// Parent constructor
	ext.prodistributionconnector.dm.PDFEmbedNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.prodistributionconnector.dm.PDFEmbedNode, ve.dm.MWInlineExtensionNode );

/* Static members */

ext.prodistributionconnector.dm.PDFEmbedNode.static.name = 'pdf';

ext.prodistributionconnector.dm.PDFEmbedNode.static.tagName = 'pdf';

// Name of the parser tag
ext.prodistributionconnector.dm.PDFEmbedNode.static.extensionName = 'pdf';

// This tag renders without content
ext.prodistributionconnector.dm.PDFEmbedNode.static.childNodeTypes = [];
ext.prodistributionconnector.dm.PDFEmbedNode.static.isContent = true;

/* Registration */

ve.dm.modelRegistry.register( ext.prodistributionconnector.dm.PDFEmbedNode );
