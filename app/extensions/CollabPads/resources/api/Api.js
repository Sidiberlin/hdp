window.collabpads = {
	api: {}
};

collabpads.api.Api = function () {
};

OO.initClass( collabpads.api.Api );

collabpads.api.Api.prototype.get = function ( path, data ) {
	data = data || {};
	return this.ajax( path, data, 'GET' );
};

collabpads.api.Api.prototype.post = function ( path, params ) {
	params = params || {};
	return this.ajax( path, params, 'POST' );
};

collabpads.api.Api.prototype.ajax = function ( path, data, method ) {
	data = data || {};
	const dfd = $.Deferred();

	$.ajax( {
		method: method,
		url: this.makeUrl( path ),
		data: data,
		contentType: 'application/json',
		dataType: 'json'
	} ).done( ( response ) => {
		dfd.resolve( response );
	} ).fail( ( xhr, type, status ) => {
		// eslint-disable-next-line no-console
		console.dir( status );
		dfd.reject();
	} );

	return dfd.promise();
};

collabpads.api.Api.prototype.makeUrl = function ( path ) {
	if ( path.charAt( 0 ) === '/' ) {
		path = path.slice( 1 );
	}

	return mw.util.wikiScript( 'rest' ) + '/collabpads/' + path;
};

collabpads.api.Api.prototype.maskPageTitle = function ( pageTitle ) {
	// Subpages may contain slashes, which are not allowed in the URL.
	return pageTitle.replace( /\//g, '|' ).replace( / /g, '_' );
};

collabpads.api.Api.prototype.getSessionByPageTitle = function ( pageNamespace, pageTitle ) {
	pageTitle = this.maskPageTitle( pageTitle );
	return this.get( 'session/start/' + pageNamespace + '/' + pageTitle );
};

collabpads.api.Api.prototype.setSessionByPageTitle = function ( data ) {
	return this.post( 'session/create', JSON.stringify( data ) );
};

collabpads.api.Api.prototype.getSessionExists = function ( pageNamespace, pageTitle ) {
	pageTitle = this.maskPageTitle( pageTitle );
	return this.get( 'session/exists/' + pageNamespace + '/' + pageTitle );
};

collabpads.api.Api.prototype.getAllSessions = function ( owner ) {
	return this.get( 'allsessions?owner=' + owner );
};

collabpads.api.Api.prototype.deleteSession = function ( pageNamespace, pageTitle ) {
	pageTitle = this.maskPageTitle( pageTitle );
	return this.get( 'session/delete/' + pageNamespace + '/' + pageTitle );
};

collabpads.api.Api.prototype.recordParticipants = function ( pageNamespace, pageTitle, revisionId, data ) { // eslint-disable-line max-len
	pageTitle = this.maskPageTitle( pageTitle );
	return this.post(
		'session/recordrevisionparticipants/' +
			pageNamespace + '/' +
			pageTitle + '/' +
			revisionId,
		JSON.stringify( data )
	);
};
