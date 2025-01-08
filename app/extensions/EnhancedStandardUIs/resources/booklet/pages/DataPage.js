ext.enhancedUI.booklet.DataPage = function ( name, cfg ) {
	this.fileName = cfg.fileData.dbkey;
	ext.enhancedUI.booklet.DataPage.parent.call( this, name, cfg );
	this.versions = [];
	this.getElements().done( function () {
		if ( this.versions.length === 0 ) {
			var label = new OO.ui.LabelWidget( {
				label: mw.message( 'enhanced-standard-uis-filelist-dialog-page-data-no-metadata-label' ).text()
			} );
			this.$element.append( label.$element );
			this.emit( 'update' );
		}
		var $table = $( '<ul>' ).addClass( 'wikitable' );
		for ( var key in this.versions ) {
			var $tr = $( '<tr>' );
			var $tdName = $( '<td>' ).text( this.versions[ key ].name );
			var $tdValue = $( '<td>' ).text( this.versions[ key ].value );
			$tr.append( $tdName ).append( $tdValue );
			$table.append( $tr );
			this.$element.append( $table );
			this.emit( 'update' );
		}
	}.bind( this ) );
};

OO.inheritClass( ext.enhancedUI.booklet.DataPage, OO.ui.PageLayout );

ext.enhancedUI.booklet.DataPage.prototype.setupOutlineItem = function () {
	this.outlineItem.setLabel( mw.message( 'enhanced-standard-uis-filelist-dialog-page-data-label' ).text() );
};

ext.enhancedUI.booklet.DataPage.prototype.getElements = function () {
	var dfd = $.Deferred();
	mw.loader.using( [ 'ext.enhancedstandarduis.api' ], function () {
		var metadataApi = new ext.enhancedUI.api.Api();
		metadataApi.getFileMetadata( this.fileName ).done( function ( data ) {
			this.versions = data;
			dfd.resolve();
		}.bind( this ) ).fail( function () {
			dfd.resolve();
		} );
	}.bind( this ) );

	return dfd.promise();
};
