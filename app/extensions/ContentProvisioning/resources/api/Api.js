contentProvisioning.api.Api = function() {
	this.currentRequests = {};
};

OO.initClass( contentProvisioning.api.Api );

contentProvisioning.api.Api.prototype.getDiff = function( pagePrefixedText ) {
	pagePrefixedText = this.maskPageTitle( pagePrefixedText );
	return this.get( 'diff/' + encodeURIComponent( pagePrefixedText ) );
};

contentProvisioning.api.Api.prototype.forceSync = function( pagePrefixedText ) {
	pagePrefixedText = this.maskPageTitle( pagePrefixedText );
	return this.post( 'sync/' + encodeURIComponent( pagePrefixedText ) );
};

contentProvisioning.api.Api.prototype.getContentProvisioning = function( params ) {
	params.filter = this.serializeStoreParams( params.filter );
	params.sort = this.serializeStoreParams( params.sort, 'property' );

	if ( params.hasOwnProperty( 'filter' ) ) {
		params.filter = JSON.stringify( params.filter );
	}
	if ( params.hasOwnProperty( 'sort' ) ) {
		params.sort = JSON.stringify( params.sort );
	}
	return this.get( 'list', params );
};

contentProvisioning.api.Api.prototype.get = function( path, params ) {
	params = params || {};
	return this.ajax( path, params, 'GET' );
};

contentProvisioning.api.Api.prototype.post = function( path, params ) {
	params = params || {};
	return this.ajax( path, JSON.stringify( { data: params } ), 'POST' );
};

contentProvisioning.api.Api.prototype.put = function( path, params ) {
	params = params || {};
	return this.ajax( path, JSON.stringify( { data: params } ), 'PUT' );
};

contentProvisioning.api.Api.prototype.delete = function( path, params ) {
	params = params || {};
	return this.ajax( path, JSON.stringify( { data: params } ), 'DELETE' );
};


contentProvisioning.api.Api.prototype.ajax = function( path, data, method ) {
	data = data || {};
	var dfd = $.Deferred();

	this.currentRequests[path] = $.ajax( {
		method: method,
		url: this.makeUrl( path ),
		data: data,
		contentType: "application/json",
		dataType: 'json',
		beforeSend: function() {
			if ( this.currentRequests.hasOwnProperty( path ) ) {
				this.currentRequests[path].abort();
			}
		}.bind( this )
	} ).done( function( response ) {
		delete( this.currentRequests[path] );
		if ( response.success === false ) {
			dfd.reject( response.error );
			return;
		}
		dfd.resolve( response );
	}.bind( this ) ).fail( function( jgXHR, type, status ) {
		delete( this.currentRequests[path] );
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	}.bind( this ) );

	return dfd.promise();
};

contentProvisioning.api.Api.prototype.makeUrl = function ( path ) {
	if ( path.charAt( 0 ) === '/' ) {
		path = path.substring( 1 );
	}
	return mw.util.wikiScript( 'rest' ) + '/content_provisioning/' + path;
};

/**
 * @private
 *
 * @param {String} pageTitle
 */
contentProvisioning.api.Api.prototype.maskPageTitle = function ( pageTitle ) {
	// Subpages may contain slashes, which are not allowed in the URL.
	return pageTitle.replace( /\//g, '|' );
};

/**
 * @private
 */
contentProvisioning.api.Api.prototype.serializeStoreParams = function( data, fieldProperty ) {
	fieldProperty = fieldProperty || 'field';
	var res = [];
	for ( var key in data ) {
		if ( !data.hasOwnProperty( key ) ) {
			continue;
		}
		if ( data[key] ) {
			var objectData = typeof data[key].getValue === 'function' ? data[key].getValue() : data[key];
			var serialized = {};
			serialized[fieldProperty] = key;
			res.push( $.extend( serialized, objectData ) );
		}
	}

	return res;
};
