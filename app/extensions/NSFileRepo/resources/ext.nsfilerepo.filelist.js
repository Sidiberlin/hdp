mw.hook( 'enhanced.filelist.gridconfig' ).add( ( columnCfg ) => {
	const buckets = require( './buckets.json' );
	// eslint-disable-next-line camelcase
	columnCfg.namespace_text = {
		headerText: mw.message( 'nsfilerepo-enhanced-filelist-grid-namespace-title' ).text(),
		type: 'text',
		sortable: false,
		filter: {
			type: 'list',
			list: buckets,
			closePopupOnChange: true
		},
		hidden: !mw.user.options.get( 'filelist-show-namespace_text' )
	};
} );
