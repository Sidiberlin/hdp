window.enhancedUpload = window.enhancedUpload || {};
window.enhancedUpload.ui.panel = window.enhancedUpload.ui.panel || {};

enhancedUpload.ui.panel.WarningList = function enhancedUploadUiPanelWarningList( cfg ) {
	enhancedUpload.ui.panel.WarningList.parent.call( this, cfg );
	this.data = [];

	this.$grid = $( '<div>' );
	this.$element.append( this.$grid );
};
OO.inheritClass( enhancedUpload.ui.panel.WarningList, OO.ui.HorizontalLayout );

enhancedUpload.ui.panel.WarningList.prototype.addGrid = function ( data ) {
	var gridCfg = {}, i, voGrid, page, errorType, filename;

	for ( i = 0; i < data.length; i++ ) {
		errorType = data[ i ][ 0 ];
		filename = data[ i ][ 1 ].name;

		page = new mw.Title( filename, 6 );
		this.data.push( {
			name: filename,
			// eslint-disable-next-line camelcase
			page_link: page.getUrl(),
			error: errorType
		} );
	}

	gridCfg = {
		pageSize: 10,
		border: 'horizontal',
		toolbar: false,
		columns: {
			name: {
				headerText: mw.message( 'enhancedupload-list-header-filename' ).plain(),
				type: 'text'
			},
			error: {
				headerText: mw.message( 'enhancedupload-list-header-warning' ).plain(),
				type: 'text'
			}
		},
		data: this.data
	};

	voGrid = new OOJSPlus.ui.data.GridWidget( gridCfg );
	this.$grid.html( voGrid.$element );
};
