( function ( mw, $, bs, d, undefined ) {
	const Vue = require( 'vue' );
	const BooksApp = require( './components/BooksApp.vue' );

	function render() {
		var deferred = $.Deferred();
		var dfdList = getStoreData();

		let filter = '';
		let query = window.location.search;
		let queryParams = new URLSearchParams( query );
		if ( queryParams.has( 'filter' ) ) {
			filter = queryParams.get( 'filter' );
		}
		dfdList.done( function( response ) {
			var h = Vue.h;

			var vm = Vue.createMwApp( {
				mounted: function () {
					deferred.resolve( this.$el );
				},
				render: function() {
					var books = [];
					var searchableData  = [];

					if ( response.length > 0 ) {
						books = response;
						// Sort books alphabetically
						books.sort( function( bookA, bookB ) {
							return bookA.displaytitle.toLowerCase().localeCompare( bookB.displaytitle.toLowerCase() );
						} );
						searchableData = setSearchableData( books );
					}

					return h( BooksApp, {
						items: books,
						searchableData: searchableData,
						searchPlaceholderLabel: mw.message( 'bs-books-overview-page-book-search-placeholder' ).plain(),
						filter: filter
					} );
				}
			} );

			vm.mount( '#bs-books-wrapper' );
			$( '#bs-books-wrapper' ).removeClass( 'loading' );
		} );

		return deferred;
	};

	function getStoreData() {
		var dfd = $.Deferred();

		mw.loader.using( 'mediawiki.api' ).done( function() {
			var api = new mw.Api();
			api.abort();
			api.get( {
					"action": "bs-books-overview-store",
					"limit": -1
			} )
			.done( function( response ) {
				var modules = getModules( response.results );
				mw.loader.using( modules ).done( function () {
					dfd.resolve( response.results );
				} );
			} ).fail( function() {
				console.log( 'fail' );
				dfd.reject()
			} );
		} );

		return dfd.promise();
	}

	/**
	 * Set up an array with keywords used by the codex search
	 * @param {*} items
	 * @returns
	 */
	function setSearchableData ( items ) {
		var data = [];
		items.forEach( function ( item )  {
			let title = mw.Title.makeTitle( item.book_namespace, item.book_title );
			let prefText = '';
			if ( title !== null ) {
				prefText = title.getPrefixedDb();
			}

			data.push(
				prefText.toLowerCase() + " "
				+ item.bookshelf.toLowerCase() + " "
				+ item.book_title.toLowerCase() + " "
				+ item.displaytitle.toLowerCase() + " "
				+ item.subtitle.toLowerCase()
			);
		} );

		return data;
	}

	function getModules( results ) {
		var allModules = [];
		results.forEach( function ( result ) {
			if ( result.modules.length <= 0 ) {
				return;
			}
			allModules = allModules.concat( result.modules );
		} );

		var modules = allModules.filter( function ( v, i, self ) {
			return i == self.indexOf( v );
		} );
		return modules;
	}

	render();


} )( mediaWiki, jQuery, blueSpice, document );