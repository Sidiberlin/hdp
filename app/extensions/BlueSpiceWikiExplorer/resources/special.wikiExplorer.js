$( function() {
	var $container = $( '#wikiexplorer' );
	if ( $container.length === 0 ) {
		return;
	}
	var $loader = $( '#wikiexplorer-loader' );

	var panel = new bs.wikiexplorer.ui.Panel( {
		expanded: false
	} );

	function setLoading( loading ) {
		if ( loading ) {
			$loader.html(
				new OO.ui.ProgressBarWidget( {
					progress: false
				} ).$element
			);
		} else {
			$loader.children().remove();
		}
	}

	$container.append( panel.$element );
} );
