officeimport.api.Api = function () {
};

OO.initClass( officeimport.api.Api );

officeimport.api.Api.prototype.getPagesStructure = function ( uploadId ) {
	return this.get( 'file_structure/' + uploadId );
};

officeimport.api.Api.prototype.get = function ( path, data ) {
	data = data || {};
	return this.ajax( path, data, 'GET' );
};

officeimport.api.Api.prototype.post = function ( path, params ) {
	params = params || {};
	return this.ajax( path, params, 'POST' );
};

officeimport.api.Api.prototype.ajax = function ( path, data, method ) {
	data = data || {};
	const dfd = $.Deferred();

	$.ajax( {
		method: method,
		url: this.makeUrl( path ),
		data: data,
		contentType: 'application/json',
		dataType: 'json'
	} ).done( function ( response ) {
		dfd.resolve( response );
	} ).fail( function ( xhr, type, status ) {
		// eslint-disable-next-line no-console
		console.dir( status );
		dfd.reject();
	} );

	return dfd.promise();
};

officeimport.api.Api.prototype.makeUrl = function ( path ) {
	if ( path.charAt( 0 ) === '/' ) {
		path = path.slice( 1 );
	}

	return mw.util.wikiScript( 'rest' ) + '/officeimport/' + path;
};

officeimport.api.Api.prototype.uploadFile = function ( file ) {
	const dfd = $.Deferred();

	const formData = new FormData();
	formData.append( 'file', file[ 0 ], file[ 0 ].name );

	$.ajax( {
		method: 'POST',
		url: this.makeUrl( 'file_storage' ),
		data: formData,
		contentType: false,
		processData: false
	} ).done( function ( response ) {
		if ( response.success === false ) {
			dfd.reject();
			return;
		}
		dfd.resolve( response );
	} ).fail( function ( xhr, type, status ) {
		// eslint-disable-next-line no-console
		console.dir( xhr );
		// eslint-disable-next-line no-console
		console.dir( type );
		// eslint-disable-next-line no-console
		console.dir( status );
		dfd.reject( status, xhr );
	} );

	return dfd.promise();
};

officeimport.api.Api.prototype.startAnalyze = function ( uploadId, filename, data ) {
	return this.get( 'file_analyze/start/' + uploadId + '/' + encodeURIComponent( filename ),
		JSON.stringify( { config: data } )
	);
};

officeimport.api.Api.prototype.getAnalyzeStatus = function ( processId ) {
	return this.get( 'file_analyze/status/' + processId );
};

officeimport.api.Api.prototype.getContent = function ( uploadId, title ) {
	return this.get( 'file_content/' + uploadId,
		JSON.stringify( { title: title } )
	);
};

officeimport.api.Api.prototype.startImport = function ( uploadId, filename, config ) {
	return this.get( 'file_import/start/' + uploadId + '/' + encodeURIComponent( filename ),
		JSON.stringify( { config: config } )
	);
};

officeimport.api.Api.prototype.getImportStatus = function ( processId ) {
	return this.get( 'file_import/status/' + processId );
};

officeimport.api.Api.prototype.importNextStep = function ( processId ) {
	return this.post( 'file_import/proceed/' + processId );
};
