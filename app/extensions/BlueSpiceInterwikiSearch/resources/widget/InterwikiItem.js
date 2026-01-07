( function ( mw, bs ) {
	bs.util.registerNamespace( 'bs.interwiki.search' );

	bs.interwiki.search.InterwikiItemWidget = function ( cfg ) {
		cfg = cfg || {};

		this.name = cfg.name;
		this.resultsConfig = cfg.resultsConfig;
		this.total = this.resultsConfig.total || 0;
		this.fullURL = cfg.fullURL;
		this.query = cfg.query;

		cfg.label = this.getLabelText();
		cfg.framed = true;

		bs.interwiki.search.InterwikiItemWidget.parent.call( this, cfg );

		this.on( 'click', this.openResults.bind( this ) );
	};

	OO.inheritClass( bs.interwiki.search.InterwikiItemWidget, OO.ui.ButtonWidget );

	bs.interwiki.search.InterwikiItemWidget.prototype.openResults = function () {
		const windowManager = OO.ui.getWindowManager(),
			dialog = new bs.interwiki.search.ResultsDialog( {
				fullURL: this.fullURL,
				name: this.name,
				resultsConfig: this.resultsConfig,
				query: this.query,
				size: 'larger'
			}, this );

		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	};

	bs.interwiki.search.InterwikiItemWidget.prototype.getLabelText = function () {
		return mw.message( 'bs-interwikisearch-item-button-label', this.name, this.total ).text();
	};

}( mediaWiki, blueSpice ) );
