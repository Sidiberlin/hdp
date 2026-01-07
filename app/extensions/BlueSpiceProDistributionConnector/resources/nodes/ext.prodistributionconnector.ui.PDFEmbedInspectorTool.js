bs.util.registerNamespace( 'ext.prodistributionconnector.ui' );

ext.prodistributionconnector.ui.PDFEmbedInspectorTool = function ( toolGroup, config ) {
	ext.prodistributionconnector.ui.PDFEmbedInspectorTool.super.call( this, toolGroup, config );
};

OO.inheritClass( ext.prodistributionconnector.ui.PDFEmbedInspectorTool, ve.ui.FragmentInspectorTool );

ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.name = 'pdfTool';
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.group = 'none';
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.autoAddToCatchall = false;
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.icon = 'bluespice';
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.title = OO.ui.deferMsg(
	'bs-pro-distribution-pdfembed-inspector-title'
);
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.modelClasses = [ ext.prodistributionconnector.dm.PDFEmbedNode ];
ext.prodistributionconnector.ui.PDFEmbedInspectorTool.static.commandName = 'pdfCommand';

ve.ui.toolFactory.register( ext.prodistributionconnector.ui.PDFEmbedInspectorTool );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'pdfCommand', 'window', 'open',
		{ args: [ 'pdfInspector' ], supportedSelections: [ 'linear' ] }
	)
);
