window.ext = window.ext || {};
ext.simpletasks = window.ext.simpletasks || {};
ext.simpletasks.api = window.ext.simpletasks.api || {};

ext.simpletasks.api.Api = function () {};

OO.initClass( ext.simpletasks.api.Api );

ext.simpletasks.api.Api.prototype.makeUrl = function ( path ) {
	if ( path.charAt( 0 ) === '/' ) {
		path = path.slice( 1 );
	}
	return mw.util.wikiScript( 'rest' ) + '/simple_tasks/' + path;
};

ext.simpletasks.api.Api.prototype.getTasksFromFilter = function ( count, data ) {
	var filter = {};
	for ( var paramKey in data ) {
		if ( data[ paramKey ].length > 0 ) {
			filter[ paramKey ] = JSON.stringify( data[ paramKey ] );
		}
	}
	return this.get( 'report/' + count, filter );
};

ext.simpletasks.api.Api.prototype.get = function ( path, params ) {
	params = params || {};
	return this.ajax( path, params, 'GET' );
};

ext.simpletasks.api.Api.prototype.ajax = function ( path, data, method ) {
	data = data || {};
	var dfd = $.Deferred();

	$.ajax( {
		method: method,
		url: this.makeUrl( path ),
		data: data,
		contentType: 'application/json',
		dataType: 'json'
	} ).done( function ( response ) {
		if ( response.success === false ) {
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
