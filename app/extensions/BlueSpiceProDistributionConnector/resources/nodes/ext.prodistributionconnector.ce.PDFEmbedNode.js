ext = ext || {};
ext.prodistributionconnector = ext.prodistributionconnector || {};
ext.prodistributionconnector.ce = ext.prodistributionconnector.ce || {};

ext.prodistributionconnector.ce.PDFEmbedNode = function () {
	// Parent constructor
	ext.prodistributionconnector.ce.PDFEmbedNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.prodistributionconnector.ce.PDFEmbedNode, ve.ce.MWInlineExtensionNode );

/* Static properties */

ext.prodistributionconnector.ce.PDFEmbedNode.static.name = 'pdf';

ext.prodistributionconnector.ce.PDFEmbedNode.static.primaryCommandName = 'pdf';

// If body is empty, tag does not render anything
ext.prodistributionconnector.ce.PDFEmbedNode.static.rendersEmpty = true;

/* Registration */

ve.ce.nodeFactory.register( ext.prodistributionconnector.ce.PDFEmbedNode );
