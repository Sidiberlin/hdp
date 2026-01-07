( function ( mw, $ ) {
	$( () => {
		const data = mw.config.get( 'maCompareData' );
		const compare = new mergeArticles.panel.Compare( { // eslint-disable-line no-unused-vars
			originID: data.originID,
			targetID: data.targetID,
			diffData: data.diffData || {},
			fileData: data.fileData || {},
			$element: $( '#merge-articles-compare' )
		} );
	} );
}( mediaWiki, jQuery ) );
