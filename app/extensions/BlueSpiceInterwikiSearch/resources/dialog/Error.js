( function ( mw, bs ) {
	bs.interwiki.search.ErrorDialog = function ( cfg ) {
		cfg = cfg || {};

		bs.interwiki.search.ErrorDialog.super.call( this, cfg );

		this.targetURL = cfg.targetURL;
		this.suggestLogin = cfg.suggestLogin || false;
		this.error = cfg.error || false;
		this.name = cfg.name;

		this.$element.addClass( 'interwikisearch-error-dialog' );
	};

	OO.inheritClass( bs.interwiki.search.ErrorDialog, OO.ui.ProcessDialog );

	bs.interwiki.search.ErrorDialog.static.name = 'InterwikiSearchErrorDialog';
	bs.interwiki.search.ErrorDialog.static.title = mw.message( 'bs-interwikisearch-error-generic-dlg-title' ).text();
	bs.interwiki.search.ErrorDialog.static.size = 'medium';

	bs.interwiki.search.ErrorDialog.static.actions = [
		{
			action: 'open',
			label: '',
			flags: [ 'primary', 'progressive' ]
		},
		{
			label: mw.message( 'cancel' ).text(),
			flags: 'safe'
		}
	];

	bs.interwiki.search.ErrorDialog.prototype.initialize = function () {
		bs.interwiki.search.ErrorDialog.parent.prototype.initialize.call( this );
		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		this.content.$element.append(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: this.getErrorMessage()
			} ).$element
		);
		this.content.$element.append( new OO.ui.LabelWidget( {
			padded: true,
			label: this.suggestLogin ?
				mw.message( 'bs-interwikisearch-login-suggestion-label', this.name ).text() :
				mw.message( 'bs-interwikisearch-redirect-suggestion-label' ).text()
		} ).$element );

		this.$body.append( this.content.$element );
	};

	bs.interwiki.search.ErrorDialog.prototype.getSetupProcess = function ( data ) {
		return bs.interwiki.search.ErrorDialog.parent.prototype.getSetupProcess.call( this, data )
			.next( () => {
				this.saveAction = this.actions.getSpecial().primary;
				if ( this.suggestLogin ) {
					this.title.setLabel( mw.message( 'bs-interwikisearch-error-login-dlg-title' ).text() );
					this.saveAction.setLabel( mw.message( 'bs-interwikisearch-error-open-login-action', this.name ).text() );
				} else {
					this.saveAction.setLabel( mw.message( 'bs-interwikisearch-error-open-target-action', this.name ).text() );
				}
			} );
	};

	bs.interwiki.search.ErrorDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	bs.interwiki.search.ErrorDialog.prototype.getErrorMessage = function () {
		return this.error !== false ?
			mw.message( 'bs-interwikisearch-error-generic-with-message', this.name, this.error ).text() :
			mw.message( 'bs-interwikisearch-error-generic', this.name ).text();
	};

	bs.interwiki.search.ErrorDialog.prototype.getActionProcess = function ( action ) {
		if ( action ) {
			return new OO.ui.Process( () => {
				this.close( { action: 'open' } );
				window.open( this.targetURL, '_blank' );
			} );
		}
		return bs.interwiki.search.ErrorDialog.super.prototype.getActionProcess.call( this, action );
	};

}( mediaWiki, blueSpice ) );
