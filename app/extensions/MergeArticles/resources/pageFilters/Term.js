( function () {
	window.mergeArticles = window.mergeArticles || {};
	window.mergeArticles.widget = window.mergeArticles.widget || {};

	mergeArticles.widget.TermFilter = function ( cfg ) {
		mergeArticles.widget.TermFilter.parent.call( this, cfg );
	};

	OO.inheritClass( mergeArticles.widget.TermFilter, mergeArticles.widget.Filter );

	mergeArticles.widget.TermFilter.prototype.makeWidget = function ( cfg ) {
		return new OO.ui.TextInputWidget( {
			id: cfg.id,
			icon: 'search'
		} );
	};

	mergeArticles.widget.TermFilter.prototype.filter = function ( pages ) {
		if ( !pages ) {
			return pages;
		}
		const filtered = [], value = this.widget.getValue().toLowerCase().replace( ' ', '_' );
		for ( let i = 0; i < pages.length; i++ ) {
			const page = pages[ i ];
			const target = page.target.text.toLowerCase().replace( ' ', '_' );
			if ( !target.includes( value ) ) {
				continue;
			}
			filtered.push( page );
		}

		return filtered;
	};
}() );
