contentProvisioning.ui.panel.ContentProvisioningOverview = function ( cfg ) {
	cfg = Object.assign( {
		padded: true,
		expanded: false
	}, cfg || {} );
	this.isLoading = false;

	this.singleClickSelect = cfg.singleClickSelect || false;
	this.defaultFilter = cfg.filter || {};
	contentProvisioning.ui.panel.ContentProvisioningOverview.parent.call( this, cfg );
	this.data = [];

	// Could be changed later
	this.filterData = this.defaultFilter;

	this.store = new contentProvisioning.store.ContentProvisioning( {
		pageSize: 25,
		filter: this.filterData,
		sorter: {
			in_sync: { // eslint-disable-line camelcase
				dir: 'ASC'
			}
		}
	} );
	this.store.connect( this, {
		loadFailed: function () {
			this.emit( 'loadFailed' );
		},
		loading: function () {
			if ( this.isLoading ) {
				return;
			}
			this.isLoading = true;
			this.emit( 'loadStarted' );
		}
	} );
	this.grid = this.makeGrid();
	this.grid.connect( this, {
		datasetChange: function () {
			this.isLoading = false;
			this.emit( 'loaded' );
		}
	} );

	this.$element.append( this.$grid );
};

OO.inheritClass( contentProvisioning.ui.panel.ContentProvisioningOverview, OO.ui.PanelLayout );

contentProvisioning.ui.panel.ContentProvisioningOverview.prototype.makeGrid = function () {
	this.$grid = $( '<div>' );

	const gridCfg = {
		deletable: false,
		style: 'differentiate-rows',
		exportable: true,
		columns: {
			page_prefixed_text: { // eslint-disable-line camelcase
				headerText: mw.message( 'contentprovisioning-ui-overview-grid-section-page' ).text(),
				type: 'url',
				urlProperty: 'page_link',
				valueParser: function ( val ) {
					// Truncate long titles
					return val.length > 35 ? val.slice( 0, 34 ) + '...' : val;
				},
				sortable: true,
				filter: {
					type: 'text'
				}
			},
			in_sync: { // eslint-disable-line camelcase
				headerText: mw.message( 'contentprovisioning-ui-overview-grid-section-in-sync' ).text(),
				type: 'boolean',
				sortable: true
			},
			forceSyncAction: {
				headerText: mw.message( 'contentprovisioning-ui-overview-grid-action-force-sync' ).text(),
				type: 'action',
				actionId: 'forceSync',
				title: mw.message( 'contentprovisioning-ui-overview-grid-action-force-sync' ).text(),
				icon: 'upload'
			},
			showDiffAction: {
				headerText: mw.message( 'contentprovisioning-ui-overview-grid-action-show-diff' ).text(),
				type: 'action',
				actionId: 'showDiff',
				title: mw.message( 'contentprovisioning-ui-overview-grid-action-show-diff' ).text(),
				icon: 'articles'
			}
		},
		store: this.store,
		provideExportData: function () {
			const dfd = $.Deferred(),
				store = new contentProvisioning.store.ContentProvisioning( {
					pageSize: -1,
					sorter: {
						page_prefixed_text: { // eslint-disable-line camelcase
							direction: 'ASC'
						}
					}
				} );
			store.load().done( ( response ) => {
				const $table = $( '<table>' );
				let $row = $( '<tr>' );
				let $cell = $( '<td>' );

				$cell.append(
					mw.message( 'contentprovisioning-ui-overview-grid-section-page' ).text()
				);
				$row.append( $cell );

				$cell = $( '<td>' );
				$cell.append(
					mw.message( 'contentprovisioning-ui-overview-grid-section-in-sync' ).text()
				);
				$row.append( $cell );

				$table.append( $row );

				for ( const id in response ) {
					if ( !response.hasOwnProperty( id ) ) {
						continue;
					}
					const record = response[ id ];
					$row = $( '<tr>' );

					$cell = $( '<td>' );
					$cell.append( record.page_prefixed_text );
					$row.append( $cell );

					$cell = $( '<td>' );
					$cell.append( record.in_sync );
					$row.append( $cell );

					$table.append( $row );
				}

				dfd.resolve( '<table>' + $table.html() + '</table>' );
			} ).fail( () => {
				dfd.reject( 'Failed to load data' );
			} );

			return dfd.promise();
		}
	};

	const grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
	grid.connect( this, {
		action: function ( action, row ) {
			if ( action === 'forceSync' ) {
				this.emit( 'sync', row.page_prefixed_text );
				return;
			}
			if ( action === 'showDiff' ) {
				this.emit( 'diff', row.page_prefixed_text );
			}
		}
	} );
	this.$grid.html( grid.$element );

	this.emit( 'gridRendered' );
	return grid;
};
