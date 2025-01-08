contentProvisioning.store.ContentProvisioning = function ( cfg ) {
	this.total = 0;
	cfg.remoteSort = true;
	cfg.remoteFilter = true;

	contentProvisioning.store.ContentProvisioning.parent.call( this, cfg );
};

OO.inheritClass( contentProvisioning.store.ContentProvisioning, OOJSPlus.ui.data.store.Store );

contentProvisioning.store.ContentProvisioning.prototype.doLoadData = function() {
	var dfd = $.Deferred();

	contentProvisioning._internal._getApi().done( function( api ) {
		api.getContentProvisioning( {
			filter: this.filters || {},
			sort: this.sorters || {},
			start: this.offset,
			limit: this.limit,
			_dc: new Date().getTime()
		} ).done( function( response ) {
			if ( !response.hasOwnProperty( 'results' ) ) {
				return;
			}

			this.total = response.total;
			dfd.resolve( this.indexData( response.results ) );
		}.bind( this ) ).fail( function( jqXHR, statusText, error ) {
			debugger;
			console.dir( jqXHR );
			console.dir( statusText );
			console.dir( error );

			dfd.reject();
		} );
	}.bind( this ) );

	return dfd.promise();
};

contentProvisioning.store.ContentProvisioning.prototype.getTotal = function() {
	return this.total;
};
