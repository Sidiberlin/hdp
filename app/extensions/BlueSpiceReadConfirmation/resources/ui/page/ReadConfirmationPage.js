bs.util.registerNamespace( 'bs.readconfirmation.ui' );

bs.readconfirmation.ui.ReadConfirmationPage = function ( cfg ) {
	cfg = cfg || {};
	this.page = cfg.data.page || 0;
	this.pageId = cfg.data.pageId;
	bs.readconfirmation.ui.ReadConfirmationPage.parent.call( this, 'page-readconfirmation', cfg );
};

OO.inheritClass( bs.readconfirmation.ui.ReadConfirmationPage, OOJSPlus.ui.booklet.DialogBookletPage );

bs.readconfirmation.ui.ReadConfirmationPage.prototype.getItems = function () {
	const label = new OO.ui.LabelWidget( {
		label: mw.message( 'bs-readconfirmation-dlg-label-page', this.page ).text()
	} );
	this.grid = new OOJSPlus.ui.data.GridWidget( {
		noHeader: true,
		toolbar: null,
		paginator: null,
		columns: {
			user: {
				type: 'text'
			},
			confirmation: {
				type: 'boolean'
			}
		},
		store: new OOJSPlus.ui.data.store.RemoteRestStore( {
			path: 'readconfirmation/' + this.pageId,
			pageSize: 99999
		} )
	} );

	this.grid.connect( this, {
		datasetChange: function () {
			this.dialog.updateSize();
		}
	} );

	return [
		label,
		this.grid
	];
};

bs.readconfirmation.ui.ReadConfirmationPage.prototype.getTitle = function () {
	return mw.message( 'bs-readconfirmation-dlg-title' ).text();
};

bs.readconfirmation.ui.ReadConfirmationPage.prototype.getSize = function () {
	return 'medium';
};

bs.readconfirmation.ui.ReadConfirmationPage.prototype.getActionKeys = function () {
	return [ 'done' ];
};

bs.readconfirmation.ui.ReadConfirmationPage.prototype.getAbilities = function () {
	return { done: true };
};

bs.readconfirmation.ui.ReadConfirmationPage.prototype.onAction = function ( action ) {
	const dfd = $.Deferred();

	if ( action === 'done' ) {
		dfd.resolve( { action: 'close', data: { success: true } } );
	} else {
		return bs.readconfirmation.ui.ReadConfirmationPage.parent.prototype.onAction.call( this, action );
	}

	return dfd.promise();
};
