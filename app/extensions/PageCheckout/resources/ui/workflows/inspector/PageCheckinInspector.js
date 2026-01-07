window.pagecheckout = window.pagecheckout || {};
window.pagecheckout.ui = pagecheckout.ui || {};
window.pagecheckout.ui.workflows = pagecheckout.ui.workflows || {};
window.pagecheckout.ui.workflows.inspector = pagecheckout.ui.workflows.inspector || {};

pagecheckout.ui.workflows.inspector.PageCheckinInspector = function ( element, dialog ) {
	pagecheckout.ui.workflows.inspector.PageCheckinInspector.parent.call( this, element, dialog );
};

OO.inheritClass( pagecheckout.ui.workflows.inspector.PageCheckinInspector, workflows.editor.inspector.ActivityInspector );

pagecheckout.ui.workflows.inspector.PageCheckinInspector.prototype.getDialogTitle = function () {
	return mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-title' ).text();
};

pagecheckout.ui.workflows.inspector.PageCheckinInspector.prototype.getItems = function () {
	return [
		{
			type: 'section_label',
			title: mw.message( 'workflows-ui-editor-inspector-properties' ).text()
		},
		{
			type: 'text',
			name: 'properties.user',
			label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-property-user' ).text(),
			help: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-property-user-help' ).text(),
			required: true
		},
		{
			type: 'text',
			name: 'properties.pagename',
			label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-property-pagename' ).text(),
			help: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-property-pagename-help' ).text()
		},
		{
			type: 'text',
			name: 'properties.pageId',
			hidden: true
		}
	];
};

workflows.editor.inspector.Registry.register( 'page_checkin', pagecheckout.ui.workflows.inspector.PageCheckinInspector );
