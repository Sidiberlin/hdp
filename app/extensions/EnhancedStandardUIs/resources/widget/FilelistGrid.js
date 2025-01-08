ext = ext || {};
ext.enhancedUI = ext.enhancedUI || {};
ext.enhancedUI.widget = ext.enhancedUI.widget || {};

ext.enhancedUI.widget.FilelistGrid = function ( cfg ) {
	cfg = cfg || {};
	this.input = cfg.input;
	this.rights = cfg.rights || [];
	this.pageSize = 50;
	cfg.columns = this.getColumnDefinitions();
	ext.enhancedUI.widget.FilelistGrid.super.call( this, cfg );
};

OO.inheritClass( ext.enhancedUI.widget.FilelistGrid, OOJSPlus.ui.data.GridWidget );

ext.enhancedUI.widget.FilelistGrid.prototype.getColumnDefinitions = function () {
	var columnCfg = {
		preview: {
			type: 'image',
			filenameProperty: 'dbkey',
			hidden: !mw.user.options.get( 'filelist-show-preview' ),
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-preview-title' ).text()
		},
		title: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-file-title' ).text(),
			type: 'url',
			sortable: true,
			urlProperty: 'url',
			filterable: false,
			hidden: !mw.user.options.get( 'filelist-show-title' )
		},
		author: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-author-title' ).text(),
			type: 'user',
			showImage: false,
			sortable: false,
			filter: { type: 'user' },
			hidden: !mw.user.options.get( 'filelist-show-author' ),
			autoClosePopup: true
		},
		timestamp: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-date-title' ).text(),
			type: 'date',
			filter: { type: 'date' },
			valueParser: function ( value, row ) {
				return row.formatted_ts;
			},
			hidden: !mw.user.options.get( 'filelist-show-formatted_ts' ),
			autoClosePopup: true
		},
		// eslint-disable-next-line camelcase
		file_extension: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-type-title' ).text(),
			type: 'text',
			sortable: true,
			filter: { type: 'string' },
			hidden: !mw.user.options.get( 'filelist-show-file_extension' ),
			autoClosePopup: true
		},
		// eslint-disable-next-line camelcase
		file_size: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-size-title' ).text(),
			type: 'number',
			sortable: true,
			filter: { type: 'number' },
			hidden: !mw.user.options.get( 'filelist-show-file_size' ),
			autoClosePopup: true
		},
		categories: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-categories-title' ).text(),
			urlProperty: 'category_url',
			type: 'url',
			limitShownData: true,
			limitValue: 2,
			sortable: true,
			filter: { type: 'string' },
			hidden: !mw.user.options.get( 'filelist-show-categories' ),
			autoClosePopup: true
		},
		comment: {
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-comment-title' ).text(),
			type: 'text',
			hidden: !mw.user.options.get( 'filelist-show-comment' ),
			autoClosePopup: true
		}
	};

	mw.hook( 'enhanced.filelist.gridconfig' ).fire( columnCfg );

	columnCfg.info = {
		type: 'action',
		title: mw.message( 'enhanced-standard-uis-filelist-grid-info-title' ).text(),
		actionId: 'info',
		icon: 'info',
		headerText: mw.message( 'enhanced-standard-uis-filelist-grid-info-title' ).text(),
		invisibleHeader: true
	};

	if ( this.rights.indexOf( 'reupload' ) !== -1 &&
		this.rights.indexOf( 'reupload-shared' ) !== -1 ) {
		columnCfg.upload = {
			type: 'action',
			title: mw.message( 'enhanced-standard-uis-filelist-grid-reupload-title' ).text(),
			actionId: 'reupload',
			icon: 'upload',
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-reupload-title' ).text(),
			invisibleHeader: true
		};
	}

	if ( this.rights.indexOf( 'delete' ) !== -1 ) {
		columnCfg.delete = {
			type: 'action',
			title: mw.message( 'enhanced-standard-uis-filelist-grid-delete-title' ).text(),
			actionId: 'delete',
			icon: 'trash',
			headerText: mw.message( 'enhanced-standard-uis-filelist-grid-delete-title' ).text(),
			invisibleHeader: true
		};
	}

	return columnCfg;
};

ext.enhancedUI.widget.FilelistGrid.prototype.setItems = function ( data ) {
	data = this.prepareCategories( data );
	mw.hook( 'enhanced.filelist.setdata' ).fire( data );
	ext.enhancedUI.widget.FilelistGrid.parent.prototype.setItems.call( this, data );
};

ext.enhancedUI.widget.FilelistGrid.prototype.prepareCategories = function ( data ) {
	for ( var element in data ) {
		if ( !data[ element ].categories ) {
			data[ element ].categories = [];
			// eslint-disable-next-line camelcase
			data[ element ].category_url = [];
			continue;
		}
		if ( data[ element ].categories.length === 0 ) {
			// eslint-disable-next-line camelcase
			data[ element ].category_url = [];
			continue;
		}
		var categoryText = data[ element ].categories;
		if ( typeof categoryText !== 'string' && !( categoryText instanceof String ) ) {
			continue;
		}
		var categories = [];
		var categoryArray = categoryText.split( '|' );
		for ( var cat in categoryArray ) {
			var categoryTitle = mw.Title.newFromText( categoryArray[ cat ], 14 );
			var categoryObj = {
				name: categoryArray[ cat ],
				url: categoryTitle.getUrl()
			};
			categories.push( categoryObj );
		}
		data[ element ].categories = categories.map( function ( category ) {
			return category.name;
		} );
		// eslint-disable-next-line camelcase
		data[ element ].category_url = categories.map( function ( category ) {
			return category.url;
		} );
	}
	return data;
};

ext.enhancedUI.widget.FilelistGrid.prototype.setColumnsVisibility = function ( visible ) {
	this.checkForColumnAddition( visible );
	this.checkForColumnRemove( visible );
	ext.enhancedUI.widget.FilelistGrid.parent.prototype.setColumnsVisibility.call( this, visible );
};

ext.enhancedUI.widget.FilelistGrid.prototype.checkForColumnAddition = function ( visible ) {
	var addition = visible.filter( function ( x ) {
		// eslint-disable-next-line es-x/no-string-prototype-includes, es-x/no-array-prototype-includes
		return !this.visibleColumns.includes( x );
	}.bind( this ) );
	for ( var column in addition ) {
		this.setPreference( addition[ column ], '1' );
	}
};

ext.enhancedUI.widget.FilelistGrid.prototype.checkForColumnRemove = function ( visible ) {
	var toRemove = this.visibleColumns.filter( function ( x ) {
		// eslint-disable-next-line es-x/no-string-prototype-includes, es-x/no-array-prototype-includes
		return !visible.includes( x );
	} );
	for ( var column in toRemove ) {
		// eslint-disable-next-line es-x/no-string-prototype-includes, es-x/no-array-prototype-includes
		if ( this.alwaysVisibleColumns.includes( toRemove[ column ] ) ) {
			continue;
		}
		this.setPreference( toRemove[ column ], '0' );
	}
};

ext.enhancedUI.widget.FilelistGrid.prototype.setPreference = function ( preference, value ) {
	if ( !mw.user.isAnon() ) {
		mw.loader.using( 'mediawiki.api' ).done( function () {
			mw.user.options.set( 'filelist-show-' + preference, value );
			new mw.Api().saveOption( 'filelist-show-' + preference, value );
		} );
	}
};
