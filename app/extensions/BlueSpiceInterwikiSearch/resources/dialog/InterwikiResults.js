( function ( mw, $, bs ) {
	bs.interwiki.search.ResultsDialog = function ( cfg ) {
		cfg = cfg || {};

		this.resultsConfig = cfg.resultsConfig;
		this.name = cfg.name;
		this.fullURL = cfg.fullURL;
		this.query = cfg.query;

		bs.interwiki.search.ResultsDialog.super.call( this, cfg );
	};

	OO.inheritClass( bs.interwiki.search.ResultsDialog, OO.ui.ProcessDialog );

	bs.interwiki.search.ResultsDialog.static.name = 'interwikiResultsDialog';

	bs.interwiki.search.ResultsDialog.static.title = mw.message( 'bs-interwikisearch-results-dialog-title' ).text();

	bs.interwiki.search.ResultsDialog.static.actions = [
		{
			action: 'openFull',
			label: mw.message( 'bs-interwikisearch-results-dialog-button-go-full' ).text(),
			flags: [ 'primary', 'progressive' ],
			disabled: false
		},
		{
			label: mw.message( 'bs-interwikisearch-results-dialog-button-close' ).text(),
			flags: 'safe'
		}

	];

	bs.interwiki.search.ResultsDialog.prototype.initialize = function () {
		bs.interwiki.search.ResultsDialog.super.prototype.initialize.call( this );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		const titleLabel = new OO.ui.LabelWidget( {
				label: mw.message( 'bs-interwikisearch-results-desc-label', this.name ).text()
			} ),
			$title = $( '<div>' )
				.addClass( 'bs-interwikisearch-results-title' )
				.append( titleLabel.$element ),

			disposableConfig = Object.assign( {}, this.resultsConfig );
		disposableConfig.results = this.resultsConfig.caller.applyResultsToStructure(
			this.resultsConfig.results
		);

		this.resultsPanel = new bs.extendedSearch.ResultsPanel( $.extend( disposableConfig, { // eslint-disable-line no-jquery/no-extend
			mobile: bs.extendedSearch.utils.isMobile(),
			externalResults: true
		} ) );

		this.resultsPanel.$element.find( '.bs-extendedsearch-loadmore' ).remove();
		this.resultsPanel.$element.find( '.bs-extendedsearch-result-relevance-cnt' ).remove();

		this.content.$element.append( $title, this.resultsPanel.$element );
		this.$body.append( this.content.$element );
	};

	bs.interwiki.search.ResultsDialog.prototype.getBodyHeight = function () {
		return $( window ).height() * 0.75;
	};

	bs.interwiki.search.ResultsDialog.prototype.getActionProcess = function ( action ) {
		if ( action === 'openFull' ) {
			return new OO.ui.Process( () => {
				const finalURL = this.fullURL.replace( '$1', this.query );
				window.open( finalURL, '_blank' );

				return this.close( { action: action } );
			} );
		}

		return bs.interwiki.search.ResultsDialog.super.prototype.getActionProcess.call(
			this, action
		);
	};
}( mediaWiki, jQuery, blueSpice ) );
