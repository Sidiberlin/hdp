bs.wikiexplorer.store.WikiExplorer = function ( cfg ) {
	this.total = 0;
	cfg.remoteSort = true;
	cfg.remoteFilter = true;

	this.request = null;

	bs.wikiexplorer.store.WikiExplorer.parent.call( this, cfg );
};

OO.inheritClass( bs.wikiexplorer.store.WikiExplorer, OOJSPlus.ui.data.store.RemoteStore );

bs.wikiexplorer.store.WikiExplorer.prototype.doLoadData = function () {
	const dfd = $.Deferred();

	const data = this.getRequestData();

	this.request = $.ajax( {
		method: 'GET',
		url: mw.util.wikiScript( 'api' ),
		data: data,
		contentType: 'application/json',
		dataType: 'json',
		beforeSend: () => {
			if ( this.request ) {
				this.request.abort();
			}
		},
		timeout: 30 * 60 * 1000 // 30 minutes
	} ).done( ( response ) => {
		this.request = null;
		if ( response.hasOwnProperty( 'results' ) ) {
			this.total = response.total;
			dfd.resolve( this.indexData( response.results ) );
			return;
		}
		dfd.reject();
	} ).fail( ( jgXHR, type, status ) => {
		this.request = null;
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};

bs.wikiexplorer.store.WikiExplorer.prototype.getRequestData = function () {
	return {
		action: 'bs-wikiexplorer-store',
		start: this.offset,
		limit: this.limit,
		filter: this.getFiltersForRemote(),
		sort: this.getSortForRemote(),
		// Get actual data here
		metaLoaded: true
	};
};

bs.wikiexplorer.store.WikiExplorer.prototype.getColumnsMeta = function () {
	const dfd = $.Deferred();

	$.ajax( {
		method: 'GET',
		url: mw.util.wikiScript( 'api' ),
		data: {
			action: 'bs-wikiexplorer-store',
			metaLoaded: false
		},
		contentType: 'application/json',
		dataType: 'json'
	} ).done( ( response ) => {
		if ( response.hasOwnProperty( 'metadata' ) ) {
			dfd.resolve( response.metadata.columns );
		}

		dfd.reject();
	} ).fail( ( jgXHR, type, status ) => {
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};
