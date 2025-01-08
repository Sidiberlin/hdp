bs.expiry.ui.ExpiryPage = function( cfg ) {
	cfg = cfg || {};
	this.reminderEnabled = cfg.reminderEnabled || false;
	this.page = '';
	this.forcePage = cfg.forcePage || false;
	bs.expiry.ui.ExpiryPage.parent.call( this, 'set-expiry', cfg );
};

OO.inheritClass( bs.expiry.ui.ExpiryPage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.expiry.ui.ExpiryPage.prototype.getItems = function() {
	this.datePicker = new mw.widgets.DateInputWidget( {
		$overlay: this.dialog.$overlay,
		required: true
	} );
	this.datePicker.$element.css( 'width', '250px' );
	this.comment = new OO.ui.MultilineTextInputWidget();
	this.pagePicker = new OOJSPlus.ui.widget.TitleInputWidget( { required: true, mustExist: true } );

	var layouts = [
		new OO.ui.FieldLayout( this.datePicker, {
			label: mw.message( 'bs-expiry-date-label' ).plain(),
			align: 'top'
		} ),
		new OO.ui.FieldLayout( this.comment, {
			label: mw.message( 'bs-expiry-comment-label' ).plain(),
			align: 'top'
		} )
	];
	if ( !this.forcePage ) {
		layouts.unshift( new OO.ui.FieldLayout( this.pagePicker, {
			label: mw.message( 'bs-expiry-article-label' ).plain(),
			align: 'top'
		} ) );
	}

	return layouts;
};

bs.expiry.ui.ExpiryPage.prototype.getTitle = function() {
	return mw.message( 'bs-expiry-dialog-title' ).plain();
};

bs.expiry.ui.ExpiryPage.prototype.getSize = function() {
	return 'medium';
};

bs.expiry.ui.ExpiryPage.prototype.setData = function( value ) {
	value = value || {};
	if ( value.hasOwnProperty( 'date' ) ) {
		this.datePicker.setValue( this.formatDateForInput( this.dateFromValue( value.date ) ) );
	}
	if ( value.hasOwnProperty( 'comment' ) ) {
		this.comment.setValue( value.comment );
	}
	if ( value.hasOwnProperty( 'id' ) ) {
		this.id = value.id;
	}
	if ( value.hasOwnProperty( 'page' ) ) {
		this.page = value.page;
		this.pagePicker.setValue( value.page );
	}

	this.updateDialogSize();
};

bs.expiry.ui.ExpiryPage.prototype.getActionKeys = function() {
	var actions = [ 'cancel', 'done' ];
	if ( this.id ) {
		actions.push( 'delete' );
	}

	return actions;
};

bs.expiry.ui.ExpiryPage.prototype.getAbilities = function() {
	return { cancel: true, done: true, delete: true };
};

bs.expiry.ui.ExpiryPage.prototype.onAction = function( action ) {
	var dfd = $.Deferred();

	if ( action === 'done' ) {
		this.checkValidity( [
			this.pagePicker,
			this.datePicker
		] ).done( function() {
			this.saveExpiry().done( function() {
				if ( this.reminderEnabled && !this.id ) {
					// Only on setting expiry
					dfd.resolve( {
						action: 'switchPanel', page: 'create-reminder-prompt',
						data: $.extend( { type: 'expiry', user: mw.user.getName() }, this.getValue() )
					} );
				} else {
					dfd.resolve( { action: 'close', data: { success: true } } );
				}
			}.bind( this ) ).fail( function( error ) {
				dfd.reject( error );
			} );
		}.bind( this ) ).fail( function() {
			// Do nothing
			dfd.resolve( {} );
		} );
	} else if ( action === 'delete' ) {
		dfd.resolve( {
			action: 'switchPanel', page: 'delete-expiry',
			data: { id: this.id, page: this.page, reminderEnabled: this.reminderEnabled }
		} );
	}  else {
		return bs.expiry.ui.ExpiryPage.parent.prototype.onAction.call( this, action );
	}

	return dfd.promise();
};

bs.expiry.ui.ExpiryPage.prototype.saveExpiry = function() {
	var dfd = $.Deferred();

	blueSpice.api.tasks.exec(
		'expiry',
		'saveExpiry',
		this.getValue(), {
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

bs.expiry.ui.ExpiryPage.prototype.formatDateForInput = function( date ) {
	if ( !date ) {
		return '';
	}

	const year = date.getFullYear();
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );

	return `${year}-${month}-${day}`;
};

bs.expiry.ui.ExpiryPage.prototype.dateFromValue = function( value ) {
	return new Date( Date.parse( value ) );
};

bs.expiry.ui.ExpiryPage.prototype.getValue = function() {
	return {
		comment: this.comment.getValue(),
		page: this.pagePicker.getValue(),
		id: this.id || 0,
		date: this.datePicker.getValue()
	};
};
