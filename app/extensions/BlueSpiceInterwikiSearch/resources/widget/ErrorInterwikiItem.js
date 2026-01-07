( function ( bs ) {
	bs.util.registerNamespace( 'bs.interwiki.search' );

	bs.interwiki.search.ErrorInterwikiItemWidget = function ( cfg ) {
		cfg = cfg || {};

		this.name = cfg.name || '';
		this.errorCode = cfg.errorCode || '';
		this.errorText = cfg.errorText || '';
		this.query = cfg.query || '';
		this.fullURL = cfg.fullURL || '';

		cfg.framed = true;

		bs.interwiki.search.ErrorInterwikiItemWidget.parent.call( this, cfg );

		this.setUpAction();
		this.$button.addClass( 'with-icon' );
	};

	OO.inheritClass( bs.interwiki.search.ErrorInterwikiItemWidget, OO.ui.ButtonWidget );

	bs.interwiki.search.ErrorInterwikiItemWidget.prototype.setUpAction = function () {
		this.setIcon( 'alert' );
		this.setLabel( this.name );

		this.connect( this, {
			click: function () {
				const errorDialog = new bs.interwiki.search.ErrorDialog( {
						size: 'medium',
						name: this.name,
						error: this.errorCode,
						suggestLogin: this.shouldSuggestLogin(),
						targetURL: this.fullURL.replace( '$1', this.query )
					} ),

					windowManager = OO.ui.getWindowManager();
				windowManager.addWindows( [ errorDialog ] );
				windowManager.openWindow( errorDialog );
			}
		} );
	};

	bs.interwiki.search.ErrorInterwikiItemWidget.prototype.shouldSuggestLogin = function () {
		// Only this error can be resolved by logging in.
		// It is not certain that it will resolve it, but its likely
		return this.errorCode === 'readapidenied';
	};
}( blueSpice ) );
