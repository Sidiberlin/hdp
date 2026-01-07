bs.expiry.ui.CreateReminderPromptPage = function ( cfg ) {
	cfg = cfg || {};
	bs.expiry.ui.CreateReminderPromptPage.parent.call( this, 'create-reminder-prompt', cfg );
	this.data = {};
};

OO.inheritClass( bs.expiry.ui.CreateReminderPromptPage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.expiry.ui.CreateReminderPromptPage.prototype.getItems = function () {
	return [
		new OO.ui.LabelWidget( {
			label: mw.message( 'bs-expiry-dialog-create-reminder-prompt' ).text()
		} )
	];
};

bs.expiry.ui.CreateReminderPromptPage.prototype.getTitle = function () {
	return mw.message( 'bs-expiry-dialog-create-reminder-title' ).text();
};

bs.expiry.ui.CreateReminderPromptPage.prototype.getSize = function () {
	return 'medium';
};

bs.expiry.ui.CreateReminderPromptPage.prototype.setData = function ( data ) {
	this.data = data;
};

bs.expiry.ui.CreateReminderPromptPage.prototype.getActionKeys = function () {
	return [ 'no', 'create' ];
};

bs.expiry.ui.CreateReminderPromptPage.prototype.getAbilities = function () {
	return { no: true, create: true };
};

bs.expiry.ui.CreateReminderPromptPage.prototype.getActionDefinitions = function () {
	return {
		no: {
			action: 'no', label: mw.message( 'bs-expiry-dialog-action-no' ).text(), flags: 'safe'
		}
	};
};

bs.expiry.ui.CreateReminderPromptPage.prototype.onAction = function ( action ) {
	const dfd = $.Deferred();

	if ( action === 'create' ) {
		dfd.resolve( {
			action: 'switchPanel', page: 'create-reminder',
			data: this.data
		} );
	} else {
		// Return success true, since expiry is still created
		return $.Deferred().resolve( { action: 'close', data: { success: true } } ).promise();
	}

	return dfd.promise();
};
