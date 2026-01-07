bs.expiry.ui.ChangeDatePage = function ( cfg ) {
	cfg = cfg || {};
	this.ids = cfg.ids || [];
	bs.expiry.ui.ChangeDatePage.parent.call( this, 'change-date-expiry', cfg );
};

OO.inheritClass( bs.expiry.ui.ChangeDatePage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.expiry.ui.ChangeDatePage.prototype.getItems = function () {
	this.datePicker = new mw.widgets.DateInputWidget( {
		$overlay: this.dialog.$overlay,
		required: true
	} );
	this.datePicker.$element.css( 'width', '250px' );

	return [
		new OO.ui.FieldLayout( this.datePicker, {
			label: mw.message( 'bs-expiry-date-label' ).text(),
			align: 'top'
		} )
	];
};

bs.expiry.ui.ChangeDatePage.prototype.getTitle = function () {
	return mw.message( 'bs-expiry-dialog-title' ).text();
};

bs.expiry.ui.ChangeDatePage.prototype.getSize = function () {
	return 'medium';
};

bs.expiry.ui.ChangeDatePage.prototype.getActionKeys = function () {
	return [ 'cancel', 'done' ];
};

bs.expiry.ui.ChangeDatePage.prototype.getAbilities = function () {
	return { cancel: true, done: true };
};

bs.expiry.ui.ChangeDatePage.prototype.onAction = function ( action ) {
	const dfd = $.Deferred();

	if ( action === 'done' ) {
		this.checkValidity( [
			this.datePicker
		] ).done( () => {
			this.changeDates().done( () => {
				dfd.resolve( { action: 'close', data: { success: true } } );
			} ).fail( ( error ) => {
				dfd.reject( error );
			} );
		} ).fail( () => {
			// Do nothing
			dfd.resolve( {} );
		} );
	} else {
		return bs.expiry.ui.ChangeDatePage.parent.prototype.onAction.call( this, action );
	}

	return dfd.promise();
};

bs.expiry.ui.ChangeDatePage.prototype.changeDates = function () {
	const dfd = $.Deferred();

	blueSpice.api.tasks.exec(
		'expiry',
		'changeDate',
		{
			ids: this.ids,
			date: this.datePicker.getValue()
		}, {
			success: function () {
				dfd.resolve();
			},
			failure: function ( response ) {
				dfd.reject( response.message );
			}
		}
	);

	return dfd.promise();
};
