window.nsfr = window.nsfr || {};

nsfr.EnhancedUploadParamsProcessor = function () {
	this.config = require( './config.json' );

	// eslint-disable-next-line no-underscore-dangle
	var excludeNS = this._getInvalidNamespaces();
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
	var namespace = prefix.split( ':' );
	var namespaces = mw.config.get( 'wgFormattedNamespaces' );
	for ( var namespaceId in namespaces ) {
		if ( namespaces[ namespaceId ] === namespace[ 0 ] ) {
			this.targetNamespaceSelector.setValue( namespaceId );
			break;
		}
	}
};

nsfr.EnhancedUploadParamsProcessor.prototype.getParams = function ( params, item, skipOption ) {
	var skipNamespace = skipOption || false;
	if ( !skipNamespace ) {
		params.namespace = this.targetNamespaceSelector.getValue();
	}
	// eslint-disable-next-line no-underscore-dangle
	params.filename = this._makeUploadFilenameFromParams( params );
	return params;
};

// eslint-disable-next-line no-underscore-dangle
nsfr.EnhancedUploadParamsProcessor.prototype._makeUploadFilenameFromParams = function ( params ) {
	var prefix = params.prefix || '';
	var filename = params.filename || '';
	var namespace = params.namespace || false;
	var namespaces = mw.config.get( 'wgFormattedNamespaces' );

	var prefixNamespace = '';
	if ( params.namespace ) {
		prefixNamespace = namespaces[ namespace ];
	}
	var prefixParts = prefix.split( ':' );
	if ( prefixParts.length > 1 ) {
		prefixNamespace = prefixParts[ 0 ];
		prefixParts.splice( 0, 1 );

		// Formatted namespaces does not contain underscores, they are replaced with spaces
		// So we should replace all underscores with spaces to correctly recognize namespace
		// Example: Namespace "ZT_ID" after formatting will change to "ZT ID"
		prefixNamespace = prefixNamespace.replace( /_/g, ' ' );
	}

	var prefixStub = prefixParts.join( ':' );
	prefixStub = prefixStub.replace( ':', '_' );

	var validPrefixNamespace = false;
	for ( var index in namespaces ) {
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
	var excludeNS = [];
	var namespaces = mw.config.get( 'wgNamespaceIds' );
	var namespacesThreshold = this.config.egNSFileRepoNamespaceThreshold;
	var namespacesBlacklist = this.config.egNSFileRepoNamespaceBlacklist;
	var skiptalk = this.config.egNSFileRepoSkipTalk;

	for ( var namespace in namespaces ) {
		var nsId = namespaces[ namespace ];
		if ( nsId < namespacesThreshold && nsId !== 0 ) {
			excludeNS.push( nsId );
			continue;
		}
		// eslint-disable-next-line no-restricted-syntax
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
