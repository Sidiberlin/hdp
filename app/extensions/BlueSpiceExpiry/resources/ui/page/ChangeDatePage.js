bs.expiry.ui.ChangeDatePage = function( cfg ) {
	cfg = cfg || {};
	this.ids = cfg.ids || [];
	bs.expiry.ui.ChangeDatePage.parent.call( this, 'change-date-expiry', cfg );
};

OO.inheritClass( bs.expiry.ui.ChangeDatePage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.expiry.ui.ChangeDatePage.prototype.getItems = function() {
	this.datePicker = new mw.widgets.DateInputWidget( {
		$overlay: this.dialog.$overlay,
		required: true
	} );
	this.datePicker.$element.css( 'width', '250px' );

	return [
		new OO.ui.FieldLayout( this.datePicker, {
			label: mw.message( 'bs-expiry-date-label' ).plain(),
			align: 'top'
		} ),
	];
};

bs.expiry.ui.ChangeDatePage.prototype.getTitle = function() {
	return mw.message( 'bs-expiry-dialog-title' ).plain();
};

bs.expiry.ui.ChangeDatePage.prototype.getSize = function() {
	return 'medium';
};

bs.expiry.ui.ChangeDatePage.prototype.getActionKeys = function() {
	return [ 'cancel', 'done' ];
};

bs.expiry.ui.ChangeDatePage.prototype.getAbilities = function() {
	return { cancel: true, done: true };
};

bs.expiry.ui.ChangeDatePage.prototype.onAction = function( action ) {
	var dfd = $.Deferred();

	if ( action === 'done' ) {
		this.checkValidity( [
			this.datePicker
		] ).done( function() {
			this.changeDates().done( function() {
				dfd.resolve( { action: 'close', data: { success: true } } );
			}.bind( this ) ).fail( function( error ) {
				dfd.reject( error );
			} );
		}.bind( this ) ).fail( function() {
			// Do nothing
			dfd.resolve( {} );
		} );
	} else {
		return bs.expiry.ui.ChangeDatePage.parent.prototype.onAction.call( this, action );
	}

	return dfd.promise();
};

bs.expiry.ui.ChangeDatePage.prototype.changeDates = function() {
	var dfd = $.Deferred();

	blueSpice.api.tasks.exec(
		'expiry',
		'changeDate',
		{
			ids: this.ids,
			date: this.datePicker.getValue()
		}, {
			success: function() {
				dfd.resolve();
			},
			failure: function( response ) {
				dfd.reject( response.message );
			}
		}
	);

	return dfd.promise();
};
