( function ( mw ) {
	const title = mw.Title.newFromText( mw.config.get( 'wgRelevantPageName' ) );
	document.title = mw.message(
		'pagetitle',
		mw.message( 'editing', title.getPrefixedText() ).text()
	).text();
}( mediaWiki ) );
