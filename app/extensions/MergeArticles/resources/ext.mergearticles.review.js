( function ( mw, $ ) {
	$( () => {
		const data = mw.config.get( 'maReviewData' );
		const review = new mergeArticles.panel.Review( { // eslint-disable-line no-unused-vars
			originID: data.originID,
			originContent: data.originContent,
			targetText: data.targetText,
			fileData: data.fileData || {},
			$element: $( '#merge-articles-review' )
		} );
	} );
}( mediaWiki, jQuery ) );
