( function ( $, bs ) {

	$( () => {
		require( './ui/CategoryManager.js' );

		const manager = new bs.categoryManager.ui.CategoryManager();
		$( '#bs-categorymanager-tree' ).append( manager.$element );
	} );

}( jQuery, blueSpice ) );
