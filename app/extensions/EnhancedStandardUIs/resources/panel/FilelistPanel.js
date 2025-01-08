ext = ext || {};
ext.enhancedUI = ext.enhancedUI || {};
ext.enhancedUI.panel = ext.enhancedUI.panel || {};

require( './../widget/FilelistGrid.js' );
require( './../widget/Paginator.js' );
require( './../dialog/FileInfoDialog.js' );

ext.enhancedUI.panel.FilelistPanel = function ( cfg ) {
	ext.enhancedUI.panel.FilelistPanel.super.apply( this, cfg );
	this.$element = $( '<div>' ).addClass( 'enhanced-ui-filelist-panel' );

	this.rights = cfg.rights || [];
	this.mode = 'list';
	this.pageSize = 25;
	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'mws/v1/file-query-store',
		pageSize: this.pageSize,
		sorter: {
			timestamp: { direction: 'desc' }
		}
	} );

	this.setupWidgets();
	this.store.connect( this, {
		loaded: function ( data ) {
			var visibleData = {};
			for ( var item in data ) {
				if ( item < this.page ) {
					continue;
				}
				if ( item > this.page + this.pageSize ) {
					continue;
				}
				visibleData[ item ] = data[ item ];
			}
			// eslint-disable-next-line es-x/no-object-values
			this.setItems( Object.values( visibleData ) );
		}
	} );
	this.page = 0;
	this.paginatorChange = false;
};

OO.inheritClass( ext.enhancedUI.panel.FilelistPanel, OO.ui.PanelLayout );

ext.enhancedUI.panel.FilelistPanel.prototype.setupWidgets = function () {
	this.setupTools();
	this.setupTilesView();

	this.grid = new ext.enhancedUI.widget.FilelistGrid( {
		store: this.store,
		rights: this.rights
	} );
	this.grid.connect( this, {
		action: 'onGridAction',
		preview: 'onGridPreview'
	} );
	this.$element.append( this.grid.$element );
};

ext.enhancedUI.panel.FilelistPanel.prototype.setupTools = function () {
	var toolsItems = [];
	this.input = new OO.ui.SearchInputWidget( {
		placeholder: mw.message( 'enhanced-standard-uis-filelist-panel-search-placeholder-label' ).text()
	} );

	this.typingTimer = null;
	this.typingDoneInterval = 200;
	this.input.connect( this, {
		change: 'onInputChange'
	} );

	if ( this.rights.indexOf( 'upload' ) !== -1 ) {
		this.uploadBtn = new OO.ui.ButtonWidget( {
			data: 'upload',
			label: mw.message( 'enhanced-standard-uis-filelist-panel-new-file-label' ).text(),
			icon: 'upload',
			id: 'enhanced-filelist-upload-file',
			href: mw.util.getUrl( 'Special:Upload' ),
			invisibleLabel: true
		} );
		toolsItems.push( new OO.ui.FieldLayout( this.uploadBtn ) );
	}

	this.typeSwitch = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				data: 'tiles',
				label: mw.message( 'enhanced-standard-uis-filelist-panel-tiles-label' ).text(),
				title: mw.message( 'enhanced-standard-uis-filelist-panel-tiles-label' ).text(),
				icon: 'viewCompact'
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'list',
				label: mw.message( 'enhanced-standard-uis-filelist-panel-list-label' ).text(),
				title: mw.message( 'enhanced-standard-uis-filelist-panel-list-label' ).text(),
				icon: 'listBullet'
			} )
		],
		classes: [ '' ]
	} );
	$( this.typeSwitch.$element ).attr( 'aria-label', 'Select view mode' );
	this.typeSwitch.selectItemByData( this.mode );
	this.typeSwitch.connect( this, {
		select: 'onTypeSwitchChange'
	} );

	toolsItems.push( new OO.ui.FieldLayout( this.typeSwitch, {
		classes: [ 'enhanced-filelist-tools-btn-select' ]
	} ) );

	this.toolsLayout = new OO.ui.HorizontalLayout( {
		classes: [ 'enhanced-filelist-tools' ],
		items: [
			this.input,
			new OO.ui.HorizontalLayout( {
				classes: [ 'enhanced-filelist-tools-align-right' ],
				items: toolsItems
			} )
		]
	} );
	this.$element.append( this.toolsLayout.$element );
};

ext.enhancedUI.panel.FilelistPanel.prototype.setupTilesView = function () {
	this.$tileContainer = $( '<div>' ).attr( 'id', 'tileview' );
	this.$element.append( this.$tileContainer );
	this.$tileContainer.hide();
	this.paginator = new ext.enhancedUI.widget.Paginator( {
		pageSize: this.pageSize
	} );
	this.paginator.connect( this, {
		selectPage: function ( nextPage ) {
			this.paginatorChange = true;
			this.store.start = this.pageSize;
			this.store.offset = nextPage * this.pageSize;
			this.store.data = {};
			this.page = nextPage;
			this.store.load();
		}
	} );
	this.$element.append( this.paginator.$element );
	this.paginator.$element.hide();
};

ext.enhancedUI.panel.FilelistPanel.prototype.onGridAction = function ( action, row ) {
	var data = {
		action: action,
		row: row
	};
	mw.hook( 'enhanced.filelist.action' ).fire( data );
	action = data.action;
	if ( action === 'info' ) {
		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		var infoDialog = new ext.enhancedUI.dialog.FileInfoDialog( {
			data: row,
			page: 'Data'
		} );
		windowManager.addWindows( [ infoDialog ] );
		windowManager.openWindow( infoDialog );
	}
	if ( action === 'reupload' ) {
		var reuploadUrl = mw.util.getUrl( 'Special:Upload',
			{
				wpDestFile: row.dbkey,
				wpForReUpload: 1
			}
		);
		window.location.href = reuploadUrl;
	}
	if ( action === 'delete' ) {
		var deleteUrl = mw.util.getUrl( 'File:' + row.dbkey,
			{
				action: 'delete'
			}
		);
		window.location.href = deleteUrl;
	}
};

ext.enhancedUI.panel.FilelistPanel.prototype.onGridPreview = function ( action, row ) {
	var windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	var infoDialog = new ext.enhancedUI.dialog.FileInfoDialog( {
		data: row,
		page: 'Preview'
	} );
	windowManager.addWindows( [ infoDialog ] );
	windowManager.openWindow( infoDialog );
};

ext.enhancedUI.panel.FilelistPanel.prototype.onTypeSwitchChange = function ( selected ) {
	if ( !selected ) {
		return;
	}
	this.mode = selected.getData();
	if ( this.mode === 'tiles' ) {
		this.grid.$element.hide();
		this.$tileContainer.show();
		this.paginator.$element.show();
	} else {
		this.$tileContainer.hide();
		this.paginator.$element.hide();
		this.grid.$element.show();
	}
	this.store.reload();
};

ext.enhancedUI.panel.FilelistPanel.prototype.setItems = function ( data ) {
	if ( this.mode === 'tiles' ) {
		if ( data.length <= 0 ) {
			return;
		}
		this.grid.$element.hide();
		var Vue = require( 'vue' ),
			FileCard = require( './../vue/Card.vue' );
		this.$tileContainer.empty();
		for ( var item in data ) {
			data[ item ].thumbnail = {
				width: 200,
				height: 180,
				url: data[ item ].preview_url
			};
		}
		var vm = Vue.createMwApp( FileCard, {
			cards: data
		} );
		vm.mount( '#tileview' );

		this.$tileContainer.show();
		if ( !this.paginatorChange ) {
			this.paginator.init( Math.ceil( this.store.getTotal() / this.pageSize ) );
		}
		this.paginatorChange = false;
		this.paginator.$element.show();
	} else {
		this.grid.setItems( data );
	}
};

ext.enhancedUI.panel.FilelistPanel.prototype.onInputChange = function ( value ) {
	clearTimeout( this.typingTimer );
	if ( value === '' ) {
		this.store.clearQuery();
		this.store.reload();
		return;
	}

	this.typingTimer = setTimeout( function () {
		this.store.query( value );
	}.bind( this ), this.typingDoneInterval );
};
