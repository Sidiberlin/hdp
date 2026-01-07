( function ( mw, $, bs, d ) {
	bs.util.registerNamespace( 'bs.interwiki.search' );

	bs.interwiki.search.InterwikiWidget = function ( cfg ) {
		cfg = cfg || {};
		this.$element = cfg.$element || $( '<div>' );
		this.sources = cfg.sources || {};
		this.elementSetupDone = false;
		this.itemsAdded = 0;
		this.ongoingRequests = [];

		bs.interwiki.search.InterwikiWidget.parent.call( this, cfg );

		this.$itemCnt = this.$element.find( '#bs-interwiki-search-items' );

		$( d ).on( 'BSExtendedSearchSearchCenterExecSearch', this.querySources.bind( this ) );
		$( '#bs-es-results' ).on( 'resultsReady', this.showInterwikiResults.bind( this ) );
	};

	OO.inheritClass( bs.interwiki.search.InterwikiWidget, OO.ui.Widget );

	bs.interwiki.search.InterwikiWidget.prototype.querySources = function ( e, queryData, search ) {
		this.reset();

		for ( const name in this.sources ) {
			const sourceConfig = this.sources[ name ];
			this.executeQuery( sourceConfig, queryData, search );
		}
	};

	bs.interwiki.search.InterwikiWidget.prototype.executeQuery = function (
		config, queryData, search
	) {
		let request;
		queryData.format = 'json';

		if ( config.hasOwnProperty( 'public-wiki' ) && config[ 'public-wiki' ] === true ) {
			request = this.searchPublic( config, queryData, search );
		} else if ( config.hasOwnProperty( 'same-domain' ) && config[ 'same-domain' ] === true ) {
			request = this.searchSameDomain( config, queryData, search );
		} else {
			request = this.searchProtected( config, queryData, search );
		}

		request.done( ( response ) => {
			this.handleResponse( response, config, search, queryData );
		} ).fail( ( code, response ) => {
			if ( response === 'abort' ) {
				return;
			}
			if ( response.hasOwnProperty( 'exception' ) && response.exception === 'abort' ) {
				return;
			}
			this.handleError( response, config, queryData );
		} );

		this.ongoingRequests.push( request );
	};

	bs.interwiki.search.InterwikiWidget.prototype.searchProtected = function (
		config, queryData
	) {
		const api = new mw.ForeignApi( config[ 'api-endpoint' ] );
		return api.get( queryData );
	};

	bs.interwiki.search.InterwikiWidget.prototype.searchSameDomain = function (
		config, queryData
	) {
		return $.get( config[ 'api-endpoint' ], queryData );
	};

	bs.interwiki.search.InterwikiWidget.prototype.searchPublic = function ( config, queryData ) {
		const params = {
			url: config[ 'api-endpoint' ],
			jsonp: 'callback',
			dataType: 'jsonp',
			data: queryData
		};

		const currentNonce = mw.config.get( 'wgCSPNonce' );
		if ( currentNonce ) {
			params.scriptAttrs = {
				nonce: currentNonce
			};
		}

		return $.ajax( params );
	};

	bs.interwiki.search.InterwikiWidget.prototype.handleResponse = function (
		response, config, search, queryData
	) {
		if ( response.hasOwnProperty( 'error' ) && response.exception === 1 ) {
			return this.handleError( response, config, queryData );
		}
		if ( response.hasOwnProperty( 'error' ) ) {
			return this.handleError( response, config, queryData );
		}
		if ( !response.total ) {
			return;
		}

		const item = new bs.interwiki.search.InterwikiItemWidget( {
			fullURL: config[ 'search-on-wiki-url' ],
			name: config.name,
			query: queryData.q,
			resultsConfig: {
				caller: search,
				spellcheck: response.spellcheck,
				// eslint-disable-next-line camelcase
				total_approximated: response.total_approximated,
				total: response.total,
				results: response.results
			}
		} );

		this.addItem( item );
		this.showInterwikiResults();
	};

	bs.interwiki.search.InterwikiWidget.prototype.handleError = function (
		response, config, queryData
	) {
		let errorText = '';
		let errorCode = '';
		// Target-level setting will override the global setting
		const silent = config.hasOwnProperty( 'silent-on-error' ) ?
			config[ 'silent-on-error' ] :
			mw.config.get( 'bsgInterwikiSearchSilentOnError' );
		if ( silent ) {
			return;
		}
		if ( typeof response !== 'object' ) {
			return;
		}
		if ( response.hasOwnProperty( 'error' ) ) {
			errorCode = response.error.code;
			errorText = response.error.info;
		}

		const item = new bs.interwiki.search.ErrorInterwikiItemWidget( {
			errorText: errorText,
			errorCode: errorCode,
			fullURL: config[ 'search-on-wiki-url' ],
			query: queryData.q || '',
			name: config.name
		} );

		this.addItem( item );
		this.showInterwikiResults();
	};

	bs.interwiki.search.InterwikiWidget.prototype.addItem = function ( item ) {
		this.$itemCnt.append( item.$element );
		this.itemsAdded++;
	};

	bs.interwiki.search.InterwikiWidget.prototype.reset = function () {
		this.$itemCnt.children().remove();
		this.$element.hide();
		this.itemsAdded = 0;
		for ( let i = 0; i < this.ongoingRequests.length; i++ ) {
			this.ongoingRequests[ i ].abort();
		}
		this.ongoingRequests = [];
	};

	bs.interwiki.search.InterwikiWidget.prototype.showInterwikiResults = function () {
		if ( this.elementSetupDone === false ) {
			this.$element.insertBefore( $( '#bs-es-results' ) );
			this.elementSetupDone = true;
		}

		if ( this.itemsAdded > 0 ) {
			// eslint-disable-next-line no-jquery/no-slide
			this.$element.slideDown();
		}
	};

}( mediaWiki, jQuery, blueSpice, document ) );
