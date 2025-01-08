bs.util.registerNamespace( 'ext.bookshelf.api' );

ext.bookshelf.api.Api = function () {

};

ext.bookshelf.api.Api.prototype.ajax = function ( path, data, method ) {
	data = data || {};
	var dfd = $.Deferred();
	$.ajax( {
		method: method,
		url: this.makeUrl( path ),
		data: data,
		contentType: 'application/json',
		dataType: 'json'
	} ).done( function ( response ) {
		if ( typeof response === 'object' && response.success === false ) {
			dfd.reject();
			return;
		}
		dfd.resolve( response );
	} ).fail( function ( jgXHR, type, status ) {
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};

ext.bookshelf.api.Api.prototype.makeUrl = function ( path ) {
	if ( path.charAt( 0 ) === '/' ) {
		path = path.slice( 1 );
	}
	return mw.util.wikiScript( 'rest' ) + '/bookshelf/' + path;
};

ext.bookshelf.api.Api.prototype.get = function ( path, params ) {
	params = params || {};
	return this.ajax( path, params, 'GET' );
};

ext.bookshelf.api.Api.prototype.post = function ( path, params ) {
	params = params || {};
	return this.ajax( path, JSON.stringify( { meta: params } ), 'POST' );
};

ext.bookshelf.api.Api.prototype.getBookMetadata = function ( bookTitle ) {
	return this.get( 'metadata/' + encodeURIComponent( bookTitle ) );
};

ext.bookshelf.api.Api.prototype.saveBookMetadata = function ( bookTitle, data ) {
	return this.post( 'metadata/' + encodeURIComponent( bookTitle ), data );
};
