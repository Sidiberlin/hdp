$( document ).on( 'BSContextMenuGetItems', ( e, $element, items, forTitle ) => {
	const title = new mw.Title( forTitle );
	if ( title.getNamespaceId() === bs.ns.NS_BOOK ) {
		items.push( {
			id: 'bs-bookshelfui-widget-editor-link',
			href: title.getUrl( { action: 'edit' } ),
			label: mw.message( 'bs-bookshelfui-widget-editor-link-text' ).text(),
			icon: 'book',
			primary: true,
			overrides: 'bs-cm-item-edit',
			flags: [ 'progressive' ]
		} );
	}
} );
