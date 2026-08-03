( ( mw, bs ) => {
	bs.util.registerNamespace( 'bs.expiry.info' );

	bs.expiry.info.ExpiryInformationPage = function ExpiryInformationPage( name, config ) {
		this.expiryLabel = null;
		bs.expiry.info.ExpiryInformationPage.super.call( this, name, config );
	};

	OO.inheritClass( bs.expiry.info.ExpiryInformationPage, StandardDialogs.ui.BasePage ); // eslint-disable-line no-undef

	bs.expiry.info.ExpiryInformationPage.prototype.setupOutlineItem = function () {
		bs.expiry.info.ExpiryInformationPage.super.prototype.setupOutlineItem.apply( this, arguments );

		if ( this.outlineItem ) {
			this.outlineItem.setLabel( mw.message( 'bs-expiry-info-dialog' ).text() );
		}
	};

	bs.expiry.info.ExpiryInformationPage.prototype.setup = function () {
		return;
	};

	bs.expiry.info.ExpiryInformationPage.prototype.onInfoPanelSelect = async function () {
		if ( !this.expiryLabel ) {
			let data;
			let pageData;
			let message;
			let type = 'notice';

			try {
				data = await bs.api.store.getData( 'expiry' );
				const title = mw.Title.newFromText( this.pageName );
				pageData = data.results.find( ( item ) => item.page_title === title.getPrefixedText() );
				if ( pageData ) {
					message = this.getExpiryMessage( pageData.expiry_date );
					type = 'warning';
				} else {
					message = mw.message( 'bs-expiry-info-dialog-expiry-not-set' ).text();
				}
			} catch ( error ) {
				message = mw.message( 'bs-expiry-info-dialog-expiry-not-set' ).text();
			}

			this.expiryLabel = new OO.ui.MessageWidget( {
				type: type,
				label: message
			} );
			const expiryDataLayout = new OO.ui.FieldLayout( this.expiryLabel );

			if ( pageData && pageData.exp_comment ) {
				const expiryCommentLabel = new OO.ui.LabelWidget( {
					label: mw.message( 'bs-expiry-info-dialog-comment', pageData.exp_comment ).text()
				} );
				expiryDataLayout.$element.append( expiryCommentLabel.$element );
			}

			this.$element.append( expiryDataLayout.$element );

			const rights = await mw.user.getRights();
			if ( rights.includes( 'edit' ) ) {
				const specialPageButton = new OO.ui.ButtonWidget( {
					label: mw.message( 'bs-expiry-info-dialog-button-label' ).text(),
					href: mw.util.getUrl( 'Special:Expiry' )
				} );

				const buttonFieldLayout = new OO.ui.FieldLayout( specialPageButton );
				this.$element.append( buttonFieldLayout.$element );
			}
		}
	};

	bs.expiry.info.ExpiryInformationPage.prototype.getExpiryMessage = function ( expiryDate ) {
		const daysRemaining = this.getRemainingDays( expiryDate );
		if ( daysRemaining <= 0 ) {
			return mw.message( 'bs-expiry-info-dialog-expired' ).text();
		}
		return mw.message(
			'bs-expiry-info-dialog-current',
			daysRemaining,
			expiryDate
		).parse();
	};

	bs.expiry.info.ExpiryInformationPage.prototype.getRemainingDays = function ( expiryDate ) {
		const today = new Date();
		const remDate = new Date( expiryDate );
		const msTime = remDate.getTime() - today.getTime();
		const daysRemaining = Math.ceil( msTime / ( 1000 * 60 * 60 * 24 ) );
		return daysRemaining;
	};

	registryPageInformation.register( 'expiry_infos', bs.expiry.info.ExpiryInformationPage ); // eslint-disable-line no-undef

} )( mediaWiki, blueSpice );
