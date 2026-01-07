/* eslint-disable camelcase */
bs.wikiexplorer.ui.Panel = function ( cfg ) {
	cfg = Object.assign( {
		padded: true,
		expanded: false
	}, cfg || {} );
	this.isLoading = false;

	this.singleClickSelect = cfg.singleClickSelect || false;
	this.defaultFilter = cfg.filter || {};
	bs.wikiexplorer.ui.Panel.parent.call( this, cfg );
	this.data = [];
	this.filterData = this.defaultFilter;

	this.store = new bs.wikiexplorer.store.WikiExplorer( {
		pageSize: 25,
		filter: this.filterData
	} );
	this.store.connect( this, {
		loadFailed: () => {
			this.emit( 'loadFailed' );
		},
		loading: () => {
			if ( this.isLoading ) {
				return;
			}
			this.isLoading = true;
			this.emit( 'loadStarted' );
		}
	} );

	this.makeGrid().done( () => {
		this.grid.connect( this, {
			datasetChange: () => {
				this.isLoading = false;
				this.emit( 'loaded' );
			}
		} );

		this.$element.append( this.$grid );
	} );
};

OO.inheritClass( bs.wikiexplorer.ui.Panel, OO.ui.PanelLayout );

bs.wikiexplorer.ui.Panel.prototype.makeGrid = function () {
	const dfd = $.Deferred();

	this.$grid = $( '<div>' );

	const pluginModules = require( '../pluginModules.json' );

	mw.loader.using( pluginModules ).done( () => {

		// Default columns
		const columns = {
			page_title: {
				headerText: mw.message( 'bs-wikiexplorer-page-title' ).text(),
				type: 'url',
				urlProperty: 'page_link',
				valueParser: ( val ) => val.length > 35 ? val.slice( 0, 34 ) + '...' : val, // Truncate long titles
				filter: {
					type: 'string'
				},
				sortable: true,
				autoClosePopup: true
			},
			page_namespace: {
				headerText: mw.message( 'bs-wikiexplorer-page-namespace' ).text(),
				type: 'text',
				valueParser: ( val ) => bs.util.getNamespaceText( val ), // Display NS text instead of ID
				filter: {
					type: 'string'
				},
				sortable: true,
				autoClosePopup: true
			},
			page_created: {
				headerText: mw.message( 'bs-wikiexplorer-creation-date' ).text(),
				type: 'date',
				valueParser: ( val ) => {
					// MW to ISO
					// YYYYMMDDHHMMSS => YYYY-MM-DDTHH
					const match = val.match(
						/^(\d{4})(\d{2})(\d{2}).*$/
					);
					const date = new Date( match[ 1 ] + '-' + match[ 2 ] + '-' + match[ 3 ] );
					if ( !date ) {
						return '';
					}

					return date.toLocaleDateString();
				},
				filter: {
					type: 'date'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_is_redirect: {
				headerText: mw.message( 'bs-wikiexplorer-is-redirect' ).text(),
				type: 'boolean',
				filter: {
					type: 'boolean'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			rev_comment_text: {
				headerText: mw.message( 'bs-wikiexplorer-last-comment' ).text(),
				type: 'text',
				valueParser: ( val ) => val.length > 35 ? val.slice( 0, 34 ) + '...' : val, // Truncate long comments
				filter: {
					type: 'string'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_touched: {
				headerText: mw.message( 'bs-wikiexplorer-last-edit-time' ).text(),
				type: 'date',
				valueParser: ( val ) => {
					// MW to ISO
					// YYYYMMDDHHMMSS => YYYY-MM-DDTHH
					const match = val.match(
						/^(\d{4})(\d{2})(\d{2}).*$/
					);
					const date = new Date( match[ 1 ] + '-' + match[ 2 ] + '-' + match[ 3 ] );
					if ( !date ) {
						return '';
					}

					return date.toLocaleDateString();
				},
				filter: {
					type: 'date'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_len: {
				headerText: mw.message( 'bs-wikiexplorer-page-size' ).text(),
				type: 'number',
				valueParser: ( val ) => {
					// Get read-able page length
					// Copied from "resources/mediawiki.inspect.js"
					let i,
						bytes = +val;
					const units = [ ' bytes', ' KB', ' MB', ' GB' ];

					if ( bytes === 0 || isNaN( bytes ) ) {
						return val;
					}

					for ( i = 0; bytes >= 1024; bytes /= 1024 ) {
						i++;
					}
					// Maintain one decimal for KB and above, but don't
					// add ".0" for bytes.
					return bytes.toFixed( i > 0 ? 1 : 0 ) + units[ i ];
				},
				filter: {
					type: 'number'
				},
				sortable: true,
				autoClosePopup: true
			},
			rev_user_text: {
				headerText: mw.message( 'bs-wikiexplorer-last-author' ).text(),
				type: 'user',
				filter: {
					type: 'user'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_categories: {
				headerText: mw.message( 'bs-wikiexplorer-categories' ).text(),
				type: 'text',
				valueParser: ( val ) => {
					if ( val.length !== 0 ) {
						let out = '<ul class="bs-wikiexplorer-list-field">';
						for ( let i = 0; i < val.length; i++ ) {
							if ( !val[ i ] ) {
								continue;
							}
							const link = mw.util.getUrl( val[ i ] );
							out = out + '<li><a href="' + link + '">' + val[ i ] + '</a></li>';
						}
						out = out + '</ul>';
						return new OO.ui.HtmlSnippet( out );
					}
				},
				filter: {
					type: 'text'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_linked_files: {
				headerText: mw.message( 'bs-wikiexplorer-linked-files' ).text(),
				type: 'text',
				valueParser: ( val ) => {
					if ( val.length !== 0 ) {
						let out = '<ul class="bs-wikiexplorer-list-field">';
						for ( let i = 0; i < val.length; i++ ) {
							if ( !val[ i ] ) {
								continue;
							}
							const link = mw.util.getUrl( val[ i ] );
							out = out + '<li><a href="' + link + '">' + val[ i ] + '</a></li>';
						}
						out = out + '</ul>';
						return new OO.ui.HtmlSnippet( out );
					}
				},
				filter: {
					type: 'text'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			},
			page_links: {
				headerText: mw.message( 'bs-wikiexplorer-linked-pages' ).text(),
				type: 'text',
				valueParser: ( val ) => {
					if ( val.length !== 0 ) {
						let out = '<ul class="bs-wikiexplorer-list-field">';
						for ( let i = 0; i < val.length; i++ ) {
							if ( !val[ i ] ) {
								continue;
							}
							const link = mw.util.getUrl( val[ i ] );
							out = out + '<li><a href="' + link + '">' + val[ i ] + '</a></li>';
						}
						out = out + '</ul>';
						return new OO.ui.HtmlSnippet( out );
					}
				},
				filter: {
					type: 'text'
				},
				hidden: true,
				sortable: true,
				autoClosePopup: true
			}
		};

		// Add integrations from other extensions
		mw.hook( 'bs.wikiexplorer.oojs.columns' ).fire( columns );

		const gridCfg = {
			deletable: false,
			style: 'differentiate-rows',
			exportable: true,
			columns: columns,
			store: this.store,
			provideExportData: () => {
				const exportDfd = $.Deferred(),
					store = new bs.wikiexplorer.store.WikiExplorer( {
						pageSize: 99999,
						filter: {},
						sorter: {
							page_title: {
								direction: 'ASC'
							}
						}
					} );
				store.load().done( ( response ) => {
					const $table = $( '<table>' );

					const $thead = $( '<thead>' )
						.append( $( '<tr>' )
							.append( $( '<td>' ).text( mw.message( 'bs-wikiexplorer-page-title' ).text() ) )
							.append( $( '<td>' ).text( mw.message( 'bs-wikiexplorer-page-namespace' ).text() ) )
							.append( $( '<td>' ).text( mw.message( 'bs-wikiexplorer-page-size' ).text() ) )
						);

					const $tbody = $( '<tbody>' );
					for ( const id in response ) {
						if ( !response.hasOwnProperty( id ) ) {
							continue;
						}
						const record = response[ id ];
						$tbody.append( $( '<tr>' )
							.append( $( '<td>' ).text( record.page_title ) )
							.append( $( '<td>' ).text( record.page_namespace ) )
							.append( $( '<td>' ).text( record.page_len ) )
						);
					}

					$table.append( $thead, $tbody );

					exportDfd.resolve( '<table>' + $table.html() + '</table>' );
				} ).fail( () => {
					exportDfd.reject( 'Failed to load data' );
				} );

				return exportDfd.promise();
			}
		};

		this.grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
		this.$grid.html( this.grid.$element );

		this.emit( 'gridRendered' );

		dfd.resolve( this.grid );
	} );

	return dfd.promise();
};
