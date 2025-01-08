contentProvisioning.ui.dialog.ContentDiff = function( diffHtml ) {
	contentProvisioning.ui.dialog.ContentDiff.super.call( this, {
		expanded: false,
		scrollable: true,
		padded: true,
		size: 'full'
	} );

	this.diffHtml = diffHtml;
}

OO.inheritClass( contentProvisioning.ui.dialog.ContentDiff, OO.ui.ProcessDialog );

contentProvisioning.ui.dialog.ContentDiff.static.name = 'diffDialog';
contentProvisioning.ui.dialog.ContentDiff.static.title = mw.message( 'contentprovisioning-ui-content-diff-title' ).text();
contentProvisioning.ui.dialog.ContentDiff.static.actions = [
	{
		title: 'Cancel',
		icon: 'close',
		flags: 'safe'
	}
];

contentProvisioning.ui.dialog.ContentDiff.prototype.initialize = function () {
	contentProvisioning.ui.dialog.ContentDiff.super.prototype.initialize.apply( this, arguments );

	var $headline = $( '<div>' ).addClass( 'diff-headline' );

	var wikiContentLabel = mw.message( 'contentprovisioning-ui-content-diff-headline-wiki-content' ).text();
	var provisionContentLabel = mw.message( 'contentprovisioning-ui-content-diff-headline-provisioner-content' ).text();

	$headline.append( $( '<h3>' ).addClass( 'diff-headline-label' ).html( wikiContentLabel ) );
	$headline.append( $( '<h3>' ).addClass( 'diff-headline-label' ).html( provisionContentLabel ) );

	var $contentContainer = $( '<div>' ).addClass( 'diff-content-container' ).html( this.diffHtml );

	var $contentContainerWrapper = $( '<div>' ).addClass( 'diff-content-container-wrapper' );
	$contentContainerWrapper.append( $headline );
	$contentContainerWrapper.append( $contentContainer );

	this.$body.append( $contentContainerWrapper );
};
