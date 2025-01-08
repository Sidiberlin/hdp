(function( mw, $, d, bs, undefined ){
	bs.util.registerNamespace( 'bs.expiry.ui' );

	bs.expiry.isReminderEnabled = function() {
		var reminderTypes = mw.config.get( 'bsgReminderRegisteredTypes', null );
		return reminderTypes && reminderTypes.hasOwnProperty( 'expiry' );
	};

	bs.expiry.getDialogPages = function( cfg, data ) {
		var dfd = $.Deferred();

		cfg = cfg || {};
		data = data || {};

		cfg.reminderEnabled = bs.expiry.isReminderEnabled();

		var pages = [
			new bs.expiry.ui.ExpiryPage( $.extend( {
				data: data
			}, cfg ) ),
			new bs.expiry.ui.DeleteExpiryPage()
		];

		if ( bs.expiry.isReminderEnabled() && !data.hasOwnProperty( 'id' ) ) {
			mw.loader.using( "ext.bluespice.reminder.dialog.pages", function() {
				pages.push( new bs.expiry.ui.CreateReminderPromptPage() );
				pages.push( new bs.reminder.ui.CreateReminderForPage() );
				dfd.resolve( pages );
			} );
		} else {
			dfd.resolve( pages );
		}



		return dfd.promise();
	};

	bs.expiry.getExpiryForPage = function( pageId ) {
		var dfd = $.Deferred();
		blueSpice.api.tasks.exec(
			'expiry',
			'getDetailsForExpiry',
			{
				articleId: pageId
			}, {
				success: function( response ) {
					dfd.resolve( response.payload );
				},
				failure: function() {
					dfd.reject();
				}
			}
		);

		return dfd.promise();
	};

	$( d ).on( 'click', 'a.bs-expiry-unexpire', function() {
		var exp_id = $( this ).data( 'expid' );
		bs.api.tasks.exec(
			'expiry',
			'deleteExpiry',
			{
				expiryId: exp_id || 0,
				articleId: mw.config.get( 'wgArticleId' ) || 0
			}
		)
		.done( function(){
			window.location.reload();
		} );
	});

	$( d ).on( 'click', 'a.bs-expiry-updateexpirydate', function() {
		var expiry = $(this).data( 'expiry' ) || {};
		var changeDateDialog = new OOJSPlus.ui.dialog.BookletDialog( {
			id: 'bs-expiry-dlg-change-date',
			pages: function() {
				var dfd = $.Deferred();
				mw.loader.using( "ext.bluespice.expiry.dialog.pages", function() {
					dfd.resolve( [ new bs.expiry.ui.ChangeDatePage( { ids: [ expiry.id ] } ) ] );

				}, function( e ) {
					dfd.reject( e );
				} );
				return dfd.promise();
			}
		} );

		changeDateDialog.show().closed.then( function( data ) {
			if ( data.success ) {
				window.location.reload();
			}
		}.bind( this ) );
	});

	$( d ).on( 'click', "#ca-expiryCreate, .ca-expiryCreate", function ( e ) {
		e.preventDefault();
		mw.loader.using( [ "ext.bluespice.expiry.dialog.pages" ] ).done( function () {
			var dialog = new OOJSPlus.ui.dialog.BookletDialog( {
				id: 'bs-expiry-dialog-set',
				pages: function() {
					var dfd = $.Deferred();
					bs.expiry.getExpiryForPage( mw.config.get( 'wgArticleId' ) )
						.done( function( data ) {
							bs.expiry.getDialogPages(
								{ forcePage: true }, $.extend( { page: mw.config.get( 'wgPageName' ) }, data )
							).done( function( pages ) {
								dfd.resolve( pages );
							} ).fail( function( e ) {
								dfd.reject( e );
							} );
						} ).fail( function() {
							bs.expiry.getDialogPages(
								{ forcePage: true }, { page: mw.config.get( 'wgPageName' ) }
							).done( function( pages ) {
								dfd.resolve( pages );
							} ).fail( function( e ) {
								dfd.reject( e );
							} );
						} );
					return dfd.promise();
				}
			} );

			dialog.show().closed.then( function( data ) {
				if( data.success ) {
					window.location.reload();
				}
			} );
		} );
	} );
})( mediaWiki, jQuery, document, blueSpice );
