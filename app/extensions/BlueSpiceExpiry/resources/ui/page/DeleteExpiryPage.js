bs.expiry.ui.DeleteExpiryPage = function( cfg ) {
	bs.expiry.ui.DeleteExpiryPage.parent.call( this, 'delete-expiry', cfg );
	this.reminderEnabled = false;
};

OO.inheritClass( bs.expiry.ui.DeleteExpiryPage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.expiry.ui.DeleteExpiryPage.prototype.getItems = function() {
	return [
		new OO.ui.LabelWidget( {
			label: mw.message( 'bs-expiry-dialog-delete-prompt' ).text()
		} )
	];
};

bs.expiry.ui.DeleteExpiryPage.prototype.getTitle = function() {
	return mw.message( 'bs-expiry-dialog-title' ).plain();
};

bs.expiry.ui.DeleteExpiryPage.prototype.getSize = function() {
	return 'medium';
};

bs.expiry.ui.DeleteExpiryPage.prototype.setData = function( data ) {
	this.id = data.id;
	this.page = data.page;
	this.reminderEnabled = data.reminderEnabled;
};

bs.expiry.ui.DeleteExpiryPage.prototype.getActionKeys = function() {
	return [ 'cancel', 'delete' ];
};

bs.expiry.ui.DeleteExpiryPage.prototype.getAbilities = function() {
	return { cancel: true, delete: true };
};

bs.expiry.ui.DeleteExpiryPage.prototype.onAction = function( action ) {
	var dfd = $.Deferred();

	if ( action === 'delete' ) {
		this.deleteExpiry().done( function() {
			dfd.resolve( { action: 'close', data: { success: true } } );
		}.bind( this ) ).fail( function( error ) {
			dfd.reject( error );
		} );
	} else {
		return bs.expiry.ui.DeleteExpiryPage.parent.prototype.onAction.call( this, action );
	}

	return dfd.promise();
};


bs.expiry.ui.DeleteExpiryPage.prototype.deleteExpiry = function() {
	var dfd = $.Deferred();

	blueSpice.api.tasks.exec(
		'expiry',
		'deleteExpiry',
		{
			expiryId: this.id || 0,
			pageName: this.page
		},
		{
			success: function() {
				if ( this.reminderEnabled ) {
					this.deleteReminder().done( function() {
						dfd.resolve();
					} ).fail( function( e ) {
						dfd.reject( e );
					} );
				} else {
					dfd.resolve();
				}
			}.bind( this ),
			failure: function( response ) {
				dfd.reject( response.message );
			}
		}
	);

	return dfd.promise();
};

/**
 * This is horrible, not a good way to integrate other extensions -.-
 *
 */
bs.expiry.ui.DeleteExpiryPage.prototype.deleteReminder = function() {
	var dfd = $.Deferred();

	blueSpice.api.tasks.exec(
		'reminder',
		'deleteReminder',
		{
			type: 'expiry',
			page: this.page
		},
		{
			success: function() {
				dfd.resolve();
			},
			failure: function() {
				dfd.resolve();
			}
		}
	);

	return dfd.promise();
};
