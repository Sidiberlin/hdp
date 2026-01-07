window.collabpad = window.collabpad || {};
window.collabpad.store = window.collabpad.store || {};

collabpad.store.Sessions = function ( cfg ) {
	this.total = 0;
	cfg.remoteSort = true;
	cfg.remoteFilter = true;
	this.sessionOwner = cfg.sessionOwner;

	collabpad.store.Sessions.parent.call( this, cfg );
};

OO.inheritClass( collabpad.store.Sessions, OOJSPlus.ui.data.store.Store );

collabpad.store.Sessions.prototype.doLoadData = function () {
	const api = new collabpads.api.Api();
	const dfd = new $.Deferred();

	api.getAllSessions( this.sessionOwner ).done( ( response ) => {
		if ( !response || !response.hasOwnProperty( 'sessions' ) ) { // eslint-disable-line no-prototype-builtins
			return;
		}

		response.sessions.forEach( ( element ) => {
			// render URLs for Grid
			element.s_page_url = mw.util.getUrl( element.s_page_title );

			if ( element.s_page_namespace == bs.ns.NS_MAIN ) { // eslint-disable-line eqeqeq
				element.s_ns_url = mw.util.getUrl( 'Special:AllPages' );
				element.s_page_url = mw.util.getUrl( element.s_page_title );
				element.s_page_namespace = mw.message( 'blanknamespace' ).plain();
			} else {
				element.s_ns_url = mw.util.getUrl( 'Special:AllPages', {
					namespace: element.s_page_namespace
				} );
				element.s_page_url = mw.util.getUrl(
					mw.config.get( 'wgFormattedNamespaces' )[ element.s_page_namespace ] +
					':' + element.s_page_title
				);
				element.s_page_namespace = mw.config.get( 'wgFormattedNamespaces' )[ element.s_page_namespace ];
			}
		} );

		// filter the results
		response.sessions = response.sessions.filter( ( session ) => {
			for ( const field in session ) {
				// if this filter not exist go to next element
				if ( !this.filters[ field ] ) {
					continue;
				}

				const fieldValue = session[ field ];
				const filterValue = this.filters[ field ].value.value;
				if (
					Array.isArray( fieldValue ) &&
					fieldValue.some( ( v ) => v.indexOf( filterValue ) >= 0 )
				) {
					return false;
				}
				if ( !fieldValue.includes( filterValue ) ) {
					return false;
				}
			}
			return true;
		} );

		this.total = response.sessions.length;
		dfd.resolve( this.indexData( response.sessions ) );
	} ).fail( ( e ) => {
		dfd.reject( e );
	} );

	return dfd.promise();
};

/*
collabpad.store.Sessions.prototype.filter = function( filter, field ) {
	switch ( field ) {
		case 's_page_namespace':
			filter.value.value =
			return dataRow.s_page_namespace;
		case 's_page_title':
			return dataRow.s_page_title;
		case 's_owner':
			return dataRow.s_owner;
		case 's_participants':
			return dataRow.s_participants;
		default:
			return null;
	}

	collabpad.store.Sessions.parent.prototype.filter.call(this, filter, field);
} */

collabpad.store.Sessions.prototype.getTotal = function () {
	return this.total;
};
