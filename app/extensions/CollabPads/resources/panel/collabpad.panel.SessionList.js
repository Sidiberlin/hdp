window.collabpad = window.collabpad || {};
window.collabpad.panel = window.collabpad.panel || {};

collabpad.panel.SessionList = function ( cfg ) {
	cfg = Object.assign( {
		padded: true,
		expanded: false
	}, cfg || {} );
	this.isLoading = false;
	this.singleClickSelect = cfg.singleClickSelect || false;
	this.defaultFilter = cfg.filter || {};
	this.sessionOwner = cfg.sessionOwner;

	collabpad.panel.SessionList.parent.call( this, cfg );
	this.data = [];
	this.filterData = Object.assign(
		{ state: { type: 'list', operator: 'in', value: [ 'running' ] } }, this.defaultFilter
	);

	this.store = new collabpad.store.Sessions( {
		remoteFilter: false,
		remoteSort: false,
		pageSize: 10,
		filter: this.filterData,
		sessionOwner: this.sessionOwner
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

OO.inheritClass( collabpad.panel.SessionList, OO.ui.PanelLayout );

collabpad.panel.SessionList.prototype.makeGrid = function () {
	this.$grid = $( '<div>' );
	const gridCfg = {
		deletable: false,
		style: 'differentiate-rows',
		columns: {
			s_page_namespace: {
				headerText: mw.msg( 'collabpads-namespace-label' ),
				type: 'url',
				urlProperty: 's_ns_url',
				filter: {
					type: 'text'
				}
			},
			s_page_title: {
				headerText: mw.msg( 'collabpads-page-label' ),
				type: 'url',
				urlProperty: 's_page_url',
				valueParser: function ( value ) {
					// Truncate long titles
					const truncatedVal = value.length > 35 ? value.slice( 0, 34 ) + '...' : value;
					return truncatedVal;
				},
				filter: {
					type: 'text'
				}
			},
			s_owner: {
				headerText: mw.msg( 'collabpads-owner-label' ),
				type: 'user',
				valueParser: function ( value ) {
					return new OOJSPlus.ui.widget.UserWidget( {
						user_name: value,
						showImage: true,
						showLink: true,
						showRawUsername: false
					} );
				},
				filter: {
					type: 'user'
				}
			},
			s_participants: {
				headerText: mw.msg( 'collabpads-participants-label' ),
				type: 'text',
				valueParser: function ( value ) {
					const $htmlElement = $( '<tr>' );
					value.forEach( ( element ) => {
						const widget = new OOJSPlus.ui.widget.UserWidget( {
							user_name: element,
							showImage: true,
							showLink: true,
							showRawUsername: false
						} );
						widget.$element.addClass( 'session-participant' );
						$htmlElement.append( $( '<th>' ).append( widget.$element ) );
					} );
					return $htmlElement;
				}
			}
		},
		store: this.store
	};

	const grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
	grid.connect( this, {
		action: function ( action, row ) {
			if ( action !== 'details' ) {
				return;
			}
			this.emit( 'selected', row.id );
		}
	} );
	this.$grid.html( grid.$element );

	this.emit( 'gridRendered' );
	return grid;
};
