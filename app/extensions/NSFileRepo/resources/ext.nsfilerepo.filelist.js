mw.hook( 'enhanced.filelist.gridconfig' ).add( function ( columnCfg ) {
	var nsBuckets = mw.config.get( 'bsgNSFileRepoAvailableNamespaces' ) || [];
	// eslint-disable-next-line camelcase
	columnCfg.namespace_text = {
		headerText: mw.message( 'nsfilerepo-enhanced-filelist-grid-namespace-title' ).text(),
		type: 'text',
		sortable: true,
		filter: {
			type: 'list',
			list: nsBuckets,
			closePopupOnChange: true
		},
		hidden: !mw.user.options.get( 'filelist-show-namespace_text' )
	};
} );
