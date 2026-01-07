( function ( mw, $, d, bs ) {
	bs.util.registerNamespace( 'bs.expiry.ui' );

	bs.expiry.isReminderEnabled = function () {
		const reminderTypes = mw.config.get( 'bsgReminderRegisteredTypes', null );
		return reminderTypes && reminderTypes.hasOwnProperty( 'expiry' );
	};

	bs.expiry.getDialogPages = function ( cfg, data ) {
		const dfd = $.Deferred();

		cfg = cfg || {};
		data = data || {};

		cfg.reminderEnabled = bs.expiry.isReminderEnabled();

		const pages = [
			new bs.expiry.ui.ExpiryPage( Object.assign( {
				data: data
			}, cfg ) ),
			new bs.expiry.ui.DeleteExpiryPage()
		];

		if ( bs.expiry.isReminderEnabled() && !data.hasOwnProperty( 'id' ) ) {
			mw.loader.using( 'ext.bluespice.reminder.dialog.pages', () => {
				pages.push( new bs.expiry.ui.CreateReminderPromptPage() );
				pages.push( new bs.reminder.ui.CreateReminderForPage() );
				dfd.resolve( pages );
			} );
		} else {
			dfd.resolve( pages );
		}

		return dfd.promise();
	};

	bs.expiry.getExpiryForPage = function ( pageId ) {
		const dfd = $.Deferred();
		blueSpice.api.tasks.exec(
			'expiry',
			'getDetailsForExpiry',
			{
				articleId: pageId
			}, {
				success: function ( response ) {
					dfd.resolve( response.payload );
				},
				failure: function () {
					dfd.reject();
				}
			}
		);

		return dfd.promise();
	};

	$( d ).on( 'click', 'a.bs-expiry-unexpire', function () {
		const exp_id = $( this ).data( 'expid' ); // eslint-disable-line camelcase
		bs.api.tasks.exec(
			'expiry',
			'deleteExpiry',
			{
				expiryId: exp_id || 0, // eslint-disable-line camelcase
				articleId: mw.config.get( 'wgArticleId' ) || 0
			}
		)
			.done( () => {
				window.location.reload();
			} );
	} );

	$( d ).on( 'click', 'a.bs-expiry-updateexpirydate', function () {
		const expiry = $( this ).data( 'expiry' ) || {};
		const changeDateDialog = new OOJSPlus.ui.dialog.BookletDialog( {
			id: 'bs-expiry-dlg-change-date',
			pages: function () {
				const dfd = $.Deferred();
				mw.loader.using( 'ext.bluespice.expiry.dialog.pages', () => {
					dfd.resolve( [ new bs.expiry.ui.ChangeDatePage( { ids: [ expiry.id ] } ) ] );

				}, ( e ) => {
					dfd.reject( e );
				} );
				return dfd.promise();
			}
		} );

		changeDateDialog.show().closed.then( ( data ) => {
			if ( data.success ) {
				window.location.reload();
			}
		} );
	} );

	$( d ).on( 'click', '#ca-expiryCreate, .ca-expiryCreate', ( e ) => {
		e.preventDefault();
		mw.loader.using( [ 'ext.bluespice.expiry.dialog.pages' ] ).done( () => {
			const dialog = new OOJSPlus.ui.dialog.BookletDialog( {
				id: 'bs-expiry-dialog-set',
				pages: function () {
					const dfd = $.Deferred();
					bs.expiry.getExpiryForPage( mw.config.get( 'wgArticleId' ) )
						.done( ( data ) => {
							bs.expiry.getDialogPages(
								{ forcePage: true }, Object.assign( { page: mw.config.get( 'wgPageName' ) }, data )
							).done( ( pages ) => {
								dfd.resolve( pages );
							} ).fail( ( e ) => { // eslint-disable-line no-shadow
								dfd.reject( e );
							} );
						} ).fail( () => {
							bs.expiry.getDialogPages(
								{ forcePage: true }, { page: mw.config.get( 'wgPageName' ) }
							).done( ( pages ) => {
								dfd.resolve( pages );
							} ).fail( ( e ) => { // eslint-disable-line no-shadow
								dfd.reject( e );
							} );
						} );
					return dfd.promise();
				}
			} );

			dialog.show().closed.then( ( data ) => {
				if ( data.success ) {
					window.location.reload();
				}
			} );
		} );
	} );
}( mediaWiki, jQuery, document, blueSpice ) );
