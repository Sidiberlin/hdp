// eslint-disable-next-line no-implicit-globals, no-global-assign
nsfr = window.nsfr || {};

nsfr.EnhancedUploadParamsProcessor = function () {
	this.config = require( './config.json' );

	// eslint-disable-next-line no-underscore-dangle
	const excludeNS = this._getInvalidNamespaces();
	this.targetNamespaceSelector = new mw.widgets.NamespaceInputWidget( {
		exclude: excludeNS,
		dropdown: {
			$overlay: this.$overlay
		}
	} );

	// Add aria-label to 'Namespace' textbox
	this.targetNamespaceSelector.$element.find( 'span[role="textbox"]' ).each( ( index, element ) => {
		const $element = $( element );
		if ( !$element.attr( 'aria-label' ) ) {
			$element.attr( 'aria-label', OO.ui.msg( 'nsfilerepo-upload-file-namespace-namespaceselector-label' ) );
		}
	} );

	this.targetNamespaceSelectorLayout = new OO.ui.FieldLayout( this.targetNamespaceSelector, {
		label: mw.message( 'nsfilerepo-upload-file-namespace-namespaceselector-label' ).plain(),
		align: 'left'
	} );
};

OO.inheritClass( nsfr.EnhancedUploadParamsProcessor, enhancedUpload.UiParamsProcessor );

nsfr.EnhancedUploadParamsProcessor.prototype.getElement = function () {
	return this.targetNamespaceSelectorLayout;
};

nsfr.EnhancedUploadParamsProcessor.prototype.setDefaultPrefix = function ( prefix ) {
	const namespace = prefix.split( ':' );
	const namespaces = mw.config.get( 'wgFormattedNamespaces' );
	for ( const namespaceId in namespaces ) {
		if ( namespaces[ namespaceId ] === namespace[ 0 ] ) {
			this.targetNamespaceSelector.setValue( namespaceId );
			break;
		}
	}
};

nsfr.EnhancedUploadParamsProcessor.prototype.getParams = function ( params, item, skipOption ) {
	const skipNamespace = skipOption || false;
	if ( !skipNamespace ) {
		params.namespace = this.targetNamespaceSelector.getValue();
	}
	// eslint-disable-next-line no-underscore-dangle
	params.filename = this._makeUploadFilenameFromParams( params );
	return params;
};

// eslint-disable-next-line no-underscore-dangle
nsfr.EnhancedUploadParamsProcessor.prototype._makeUploadFilenameFromParams = function ( params ) {
	let prefix = params.prefix || '';
	const filename = params.filename || '';
	let namespace = params.namespace || false;
	const namespaces = mw.config.get( 'wgFormattedNamespaces' );

	let prefixNamespace = '';
	if ( params.namespace ) {
		prefixNamespace = namespaces[ namespace ];
	}
	const prefixParts = prefix.split( ':' );
	if ( prefixParts.length > 1 ) {
		prefixNamespace = prefixParts[ 0 ];
		prefixParts.splice( 0, 1 );

		// Formatted namespaces does not contain underscores, they are replaced with spaces
		// So we should replace all underscores with spaces to correctly recognize namespace
		// Example: Namespace "ZT_ID" after formatting will change to "ZT ID"
		prefixNamespace = prefixNamespace.replace( /_/g, ' ' );
	}

	let prefixStub = prefixParts.join( ':' );
	prefixStub = prefixStub.replace( ':', '_' );

	let validPrefixNamespace = false;
	for ( const index in namespaces ) {
		if ( namespaces[ index ] === prefixNamespace ) {
			namespace = index;
			validPrefixNamespace = true;
			break;
		}
	}

	if ( validPrefixNamespace === false ) {
		prefixStub = prefixNamespace + '_' + prefixStub;
		prefixNamespace = '';
	}

	if ( namespace ) {
		if ( ( namespace !== 0 ) && ( namespaces[ namespace ] !== 'undefined' ) ) {
			prefixNamespace = namespaces[ namespace ];
		}
	}

	if ( prefixNamespace !== '' ) {
		prefixNamespace += ':';
	}

	// Avoid double prefixing
	if ( prefixStub === filename.slice( 0, prefixStub.length ) ) {
		prefixStub = '';
	}

	prefix = prefixNamespace + prefixStub;

	return prefix + filename;
};

// eslint-disable-next-line no-underscore-dangle
nsfr.EnhancedUploadParamsProcessor.prototype._getInvalidNamespaces = function () {
	const excludeNS = [];
	const namespaces = mw.config.get( 'wgNamespaceIds' );
	const namespacesThreshold = this.config.egNSFileRepoNamespaceThreshold;
	const namespacesBlacklist = this.config.egNSFileRepoNamespaceBlacklist;
	const skiptalk = this.config.egNSFileRepoSkipTalk;

	for ( const namespace in namespaces ) {
		const nsId = namespaces[ namespace ];
		if ( nsId < namespacesThreshold && nsId !== 0 ) {
			excludeNS.push( nsId );
			continue;
		}
		// eslint-disable-next-line es-x/no-array-prototype-includes
		if ( namespacesBlacklist.includes( nsId ) ) {
			excludeNS.push( nsId );
			continue;
		}
		if ( skiptalk && nsId % 2 !== 0 ) {
			excludeNS.push( nsId );
		}
	}

	return excludeNS;
};
