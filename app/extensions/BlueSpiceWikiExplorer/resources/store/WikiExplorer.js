bs.wikiexplorer.store.WikiExplorer = function ( cfg ) {
	this.total = 0;
	cfg.remoteSort = true;
	cfg.remoteFilter = true;

	this.request = null;

	bs.wikiexplorer.store.WikiExplorer.parent.call( this, cfg );
};

OO.inheritClass( bs.wikiexplorer.store.WikiExplorer, OOJSPlus.ui.data.store.RemoteStore );

bs.wikiexplorer.store.WikiExplorer.prototype.doLoadData = function() {
	var dfd = $.Deferred();

	var data = this.getRequestData();

	this.request = $.ajax( {
		method: 'GET',
		url: mw.util.wikiScript( 'api' ),
		data: data,
		contentType: 'application/json',
		dataType: 'json',
		beforeSend: function() {
			if ( this.request ) {
				this.request.abort();
			}
		}.bind( this ),
		timeout: 30 * 60 * 1000 // 30 minutes
	} ).done( function( response ) {
		this.request = null;
		if ( response.hasOwnProperty( 'results' ) ) {
			this.total = response.total;
			dfd.resolve( this.indexData( response.results ) );
			return;
		}
		dfd.reject();
	}.bind( this ) ).fail( function( jgXHR, type, status ) {
		this.request = null;
		dfd.reject( { type: type, status: status } );
	}.bind( this ) );

	return dfd.promise();
};

bs.wikiexplorer.store.WikiExplorer.prototype.getRequestData = function() {
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

bs.wikiexplorer.store.WikiExplorer.prototype.getColumnsMeta = function() {
	var dfd = $.Deferred();

	$.ajax( {
		method: 'GET',
		url: mw.util.wikiScript( 'api' ),
		data: {
			action: 'bs-wikiexplorer-store',
			metaLoaded: false
		},
		contentType: 'application/json',
		dataType: 'json'
	} ).done( function( response ) {
		if ( response.hasOwnProperty( 'metadata' ) ) {
			dfd.resolve( response.metadata.columns );
		}

		dfd.reject();
	}.bind( this ) ).fail( function( jgXHR, type, status ) {
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};
