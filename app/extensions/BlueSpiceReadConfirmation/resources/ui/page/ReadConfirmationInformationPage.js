( function ( mw, $, bs ) {

	bs.util.registerNamespace( 'bs.readconfirmation.ui' );

	bs.readconfirmation.ui.ReadConfirmationInformationPage = function ( name, cfg ) {
		this.readConfirmationGrid = null;
		bs.readconfirmation.ui.ReadConfirmationInformationPage.parent.call( this, name, cfg );
	};

	OO.inheritClass( bs.readconfirmation.ui.ReadConfirmationInformationPage, StandardDialogs.ui.BasePage );

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.onInfoPanelSelect = function () {
		if ( this.readConfirmationGrid === null ) {
			mw.loader.using( [ 'ext.oOJSPlus.data', 'oojs-ui.styles.icons-user' ] ).done( () => {
				this.readConfirmationGrid = new OOJSPlus.ui.data.GridWidget( {
					toolbar: null,
					paginator: null,
					columns: {
						user: {
							headerText: mw.message( 'bs-readconfirmation-page-info-user' ).text(),
							type: 'text'
						},
						confirmation: {
							headerText: mw.message( 'bs-readconfirmation-page-info-confirmation' ).text(),
							type: 'boolean'
						}
					},
					store: new OOJSPlus.ui.data.store.RemoteRestStore( {
						path: 'readconfirmation/' + mw.config.get( 'wgArticleId' ),
						pageSize: 99999
					} )
				} );
				this.$element.append( this.readConfirmationGrid.$element );
			} );
		}
	};

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.setupOutlineItem = function () {
		bs.readconfirmation.ui.ReadConfirmationInformationPage.super.prototype.setupOutlineItem.apply( this, arguments );

		if ( this.outlineItem ) {
			this.outlineItem.setLabel( mw.message( 'bs-readconfirmation-page-info-read-confirmations' ).text() );
		}
	};

	bs.readconfirmation.ui.ReadConfirmationInformationPage.prototype.setup = function () {
		return;
	};

	if ( mw.config.get( 'bsReadConfirmationsViewRight' ) ) {
		registryPageInformation.register( 'read_confirmation_infos', bs.readconfirmation.ui.ReadConfirmationInformationPage );
	}

}( mediaWiki, jQuery, blueSpice ) );
