contentProvisioning.store.ContentProvisioning = function ( cfg ) {
	this.total = 0;
	cfg.remoteSort = true;
	cfg.remoteFilter = true;

	contentProvisioning.store.ContentProvisioning.parent.call( this, cfg );
};

OO.inheritClass( contentProvisioning.store.ContentProvisioning, OOJSPlus.ui.data.store.Store );

contentProvisioning.store.ContentProvisioning.prototype.doLoadData = function () {
	const dfd = $.Deferred();

	contentProvisioning._internal._getApi().done( ( api ) => { // eslint-disable-line no-underscore-dangle
		api.getContentProvisioning( {
			filter: this.filters || {},
			sort: this.sorters || {},
			start: this.offset,
			limit: this.limit,
			_dc: Date.now()
		} ).done( ( response ) => {
			if ( !response.hasOwnProperty( 'results' ) ) {
				return;
			}

			this.total = response.total;
			dfd.resolve( this.indexData( response.results ) );
		} ).fail( ( jqXHR, statusText, error ) => {
			console.dir( jqXHR ); // eslint-disable-line no-console
			console.dir( statusText ); // eslint-disable-line no-console
			console.dir( error ); // eslint-disable-line no-console

			dfd.reject();
		} );
	} );

	return dfd.promise();
};

contentProvisioning.store.ContentProvisioning.prototype.getTotal = function () {
	return this.total;
};
