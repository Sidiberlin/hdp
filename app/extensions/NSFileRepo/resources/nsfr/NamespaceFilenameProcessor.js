window.nsfr = window.nsfr || {};

nsfr.NamespaceFilenameProcessor = function () {};

/**
 * @inheritDoc
 */
nsfr.NamespaceFilenameProcessor.prototype.initializeFilename = function () {
	let filename = mw.config.get( 'wgPageName' ) + '-' + ( Math.floor( Math.random() * 100000000 ) + 1 );
	// filename must only contain alphanumeric characters, colons (for namespaces),
	// dashes and underscores
	filename = this.sanitizeFilename( filename );

	return filename;
};

/**
 * @inheritDoc
 */
nsfr.NamespaceFilenameProcessor.prototype.validateFilename = function ( filename ) {
	if ( filename === '' ) {
		return false;
	}
	if ( !filename.match( /^[\w,-.:\s]+$/ ) ) {
		return false;
	}
	return true;
};

/**
 * @inheritDoc
 */
nsfr.NamespaceFilenameProcessor.prototype.sanitizeFilename = function ( filename ) {
	// filename must only contain alphanumeric characters, colons (for namespaces),
	// dashes and underscores
	filename = filename.replace( /[^a-zA-Z0-9_:-]/g, '_' );

	return filename;
};
