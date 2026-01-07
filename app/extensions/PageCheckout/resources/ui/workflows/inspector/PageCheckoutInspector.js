window.pagecheckout = window.pagecheckout || {};
window.pagecheckout.ui = pagecheckout.ui || {};
window.pagecheckout.ui.workflows = pagecheckout.ui.workflows || {};
window.pagecheckout.ui.workflows.inspector = pagecheckout.ui.workflows.inspector || {};

pagecheckout.ui.workflows.inspector.PageCheckoutInspector = function ( element, dialog ) {
	pagecheckout.ui.workflows.inspector.PageCheckoutInspector.parent.call( this, element, dialog );
};

OO.inheritClass( pagecheckout.ui.workflows.inspector.PageCheckoutInspector, workflows.editor.inspector.ActivityInspector );

pagecheckout.ui.workflows.inspector.PageCheckoutInspector.prototype.getDialogTitle = function () {
	return mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-title' ).text();
};

pagecheckout.ui.workflows.inspector.PageCheckoutInspector.prototype.getItems = function () {
	return [
		{
			type: 'section_label',
			title: mw.message( 'workflows-ui-editor-inspector-properties' ).text()
		},
		{
			type: 'text',
			name: 'properties.user',
			label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-user' ).text(),
			help: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-user-help' ).text(),
			required: true
		},
		{
			type: 'text',
			name: 'properties.pagename',
			label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-pagename' ).text(),
			help: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-pagename-help' ).text()
		},
		{
			type: 'checkbox',
			name: 'properties.force',
			label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-force' ).text(),
			help: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-property-force-help' ).text()
		},
		{
			type: 'text',
			name: 'properties.pageId',
			hidden: true
		}
	];
};

workflows.editor.inspector.Registry.register( 'page_checkout', pagecheckout.ui.workflows.inspector.PageCheckoutInspector );
