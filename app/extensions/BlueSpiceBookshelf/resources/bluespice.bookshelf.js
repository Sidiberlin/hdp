( function ( mw, $, d ) {
	$( function () {
		_renderBookNavigation();
	} );

	function _renderBookNavigation() {
		var bsBookshelfNavDeps = window.bsBookshelfNavDeps || [];
		bsBookshelfNavDeps.push( 'ext.bluespice.extjs' );

		//In some cases rendering fails because the code is executed to early.
		//As an ugly workaround we use a little timeout. This may be tweaked by
		//an global JavaScript variable;
		if ( typeof bsBookshelfTagDeferTime === 'undefined' ) {
			bsBookshelfTagDeferTime = 200;
		}

		$( '.bs-bookshelf-toc' ).each( function () {
			if ( $( this ).is( ':visible' ) === false ) {
				return;
			}

			var me = this;
			mw.loader.using( bsBookshelfNavDeps, function () {
				Ext.onReady( function () {
					var opts = {
						deferTime: bsBookshelfTagDeferTime
					};

					var config = {
						renderTo: me,
						treeData: $( me ).data( 'bs-tree' ),
						bookSrc: $( me ).data( 'bs-src' )
					};

					$( d ).trigger( 'BSBookshelfBeforeCreateNavigation', [ me, config, opts ] );

					Ext.defer( function() {
						Ext.create( 'BS.Bookshelf.BookNavigation', config );
					}, opts.deferTime, me );
				});
			});
		});
	}

} )( mediaWiki, jQuery, document );

//Note from old implementation:
/* Workaround for very strange bug: The TreePanel always scrolled to the
 * bottom even though CurrentArticleNode was selected in "afterrender"
 * (Ext.tree.TreeNode::select()). Maybe it would be better to set the
 * timeout in "show" event handler?
 */
//setTimeout("BsBookshelfTOC.oHTOCTreePanel.oCurrentArticleNode.select();",500);'
