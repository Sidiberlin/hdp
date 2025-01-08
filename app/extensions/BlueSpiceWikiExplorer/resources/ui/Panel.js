bs.wikiexplorer.ui.Panel = function( cfg ) {
	cfg = $.extend( {
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
		loadFailed: function() {
			this.emit( 'loadFailed' );
		},
		loading: function() {
			if ( this.isLoading ) {
				return;
			}
			this.isLoading = true;
			this.emit( 'loadStarted' );
		}
	} );

	this.makeGrid().done( function( grid ) {
		this.grid.connect( this, {
			datasetChange: function() {
				this.isLoading = false;
				this.emit( 'loaded' );
			}
		} );

		this.$element.append( this.$grid );
	}.bind( this ) );
};

OO.inheritClass( bs.wikiexplorer.ui.Panel, OO.ui.PanelLayout );

bs.wikiexplorer.ui.Panel.prototype.makeGrid = function() {
	var dfd = $.Deferred();

	this.$grid = $( '<div>' );

	var pluginModules = require( '../pluginModules.json' );

	mw.loader.using( pluginModules ).done( function () {

		// Default columns
		var columns = {
			page_title: {
				headerText: mw.message( 'bs-wikiexplorer-page-title' ).text(),
				type: 'url',
				urlProperty: 'page_link',
				valueParser: function ( val ) {
					// Truncate long titles
					return val.length > 35 ? val.substr( 0, 34 ) + '...' : val;
				},
				filter: {
					type: 'string'
				},
				sortable: true,
				autoClosePopup: true
			},
			page_namespace: {
				headerText: mw.message( 'bs-wikiexplorer-page-namespace' ).text(),
				type: 'text',
				valueParser: function ( val ) {
					// Display NS text instead of ID
					return bs.util.getNamespaceText( val );
				},
				filter: {
					type: 'string'
				},
				sortable: true,
				autoClosePopup: true
			},
			page_created: {
				headerText: mw.message( 'bs-wikiexplorer-creation-date' ).text(),
				type: 'date',
				valueParser: function ( val ) {
					// MW to ISO
					// YYYYMMDDHHMMSS => YYYY-MM-DDTHH
					var match = val.match(
						/^(\d{4})(\d{2})(\d{2}).*$/
					);
					var date = new Date( match[1] + '-' + match[2] + '-' + match[3] );
					if( !date ) {
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
				valueParser: function ( val ) {
					// TODO: Do we need to truncate long comments?
					// return val.length > 35 ? val.substr(0, 34) + '...' : val;
				},
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
				valueParser: function ( val ) {
					// MW to ISO
					// YYYYMMDDHHMMSS => YYYY-MM-DDTHH
					var match = val.match(
						/^(\d{4})(\d{2})(\d{2}).*$/
					);
					var date = new Date( match[1] + '-' + match[2] + '-' + match[3] );
					if( !date ) {
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
				valueParser: function ( val ) {
					// Get read-able page length
					// Copied from "resources/mediawiki.inspect.js"
					var i,
						bytes = +val,
						units = [ ' bytes', ' KB', ' MB', ' GB' ];

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
				headerText: mw.message('bs-wikiexplorer-categories').text(),
				type: 'text',
				valueParser: function ( val ) {
					if ( val.length !== 0 ) {
						var out = '<ul class="bs-wikiexplorer-list-field">';
						for ( var i = 0; i < val.length; i++ ) {
							if ( !val[i] ) {
								continue;
							}
							var link = mw.util.getUrl( val[i] );
							out = out + '<li><a href="' + link + '">' + val[i] + '</a></li>';
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
				headerText: mw.message('bs-wikiexplorer-linked-files').text(),
				type: 'text',
				valueParser: function ( val ) {
					if ( val.length !== 0 ) {
						var out = '<ul class="bs-wikiexplorer-list-field">';
						for ( var i = 0; i < val.length; i++ ) {
							if ( !val[i] ) {
								continue;
							}
							var link = mw.util.getUrl( val[i] );
							out = out + '<li><a href="' + link + '">' + val[i] + '</a></li>';
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
				headerText: mw.message('bs-wikiexplorer-linked-pages').text(),
				type: 'text',
				valueParser: function ( val ) {
					if ( val.length !== 0 ) {
						var out = '<ul class="bs-wikiexplorer-list-field">';
						for ( var i = 0; i < val.length; i++ ) {
							if ( !val[i] ) {
								continue;
							}
							var link = mw.util.getUrl( val[i] );
							out = out + '<li><a href="' + link + '">' + val[i] + '</a></li>';
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
		mw.hook('bs.wikiexplorer.oojs.columns').fire( columns );

		var gridCfg = {
			deletable: false,
			style: 'differentiate-rows',
			exportable: true,
			columns: columns,
			store: this.store,
			provideExportData: function() {
				var dfd = $.Deferred(),
					store = new bs.wikiexplorer.store.WikiExplorer( {
						pageSize: 99999,
						filter: {},
						sorter: {
							page_title: {
								direction: 'ASC'
							}
						}
					} );
				store.load().done( function( response ) {
					var $table = $( '<table>' ),
						$row = $( '<tr>' ),
						$cell = $( '<td>' );
					$cell.append(
						mw.message( 'bs-wikiexplorer-page-title' ).text()
					);
					$row.append( $cell );

					$cell = $( '<td>' );
					$cell.append(
						mw.message( 'bs-wikiexplorer-page-namespace' ).text()
					);
					$row.append( $cell );

					$cell = $( '<td>' );
					$cell.append(
						mw.message( 'bs-wikiexplorer-page-size' ).text()
					);
					$row.append( $cell );

					$table.append( $row );

					for ( var id in response ) {
						if ( !response.hasOwnProperty( id ) ) {
							continue;
						}
						var record = response[id];
						$row = $( '<tr>' );

						$cell = $( '<td>' );
						$cell.append( record.page_title );
						$row.append( $cell );

						$cell = $( '<td>' );
						$cell.append( record.page_namespace );
						$row.append( $cell );

						$cell = $( '<td>' );
						$cell.append( record.page_len );
						$row.append( $cell );

						$table.append( $row );
					}

					dfd.resolve( '<table>' + $table.html() + '</table>' );
				} ).fail( function() {
					dfd.reject( 'Failed to load data' );
				} );

				return dfd.promise();
			}
		};

		this.grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
		this.$grid.html( this.grid.$element );

		this.emit( 'gridRendered' );

		dfd.resolve( this.grid );
	}.bind( this ) );

	return dfd.promise();
};
