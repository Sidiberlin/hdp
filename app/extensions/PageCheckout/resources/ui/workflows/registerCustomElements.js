workflows.editor.element.registry.register( 'page_checkout', {
	isUserActivity: false,
	class: 'activity-page-checkout activity-bootstrap-icon',
	label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkout-title' ).text(),
	defaultData: {
		properties: {
			user: '',
			pageId: '',
			pagename: '',
			force: ''
		}
	}
} );

workflows.editor.element.registry.register( 'page_checkin', {
	isUserActivity: false,
	class: 'activity-page-checkin activity-bootstrap-icon',
	label: mw.message( 'pagecheckout-ui-workflows-inspector-activity-page-checkin-title' ).text(),
	defaultData: {
		properties: {
			user: '',
			pageId: '',
			pagename: ''
		}
	}
} );
