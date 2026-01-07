bs.util.registerNamespace( 'ext.bluespice.expiry.ui.panel' );

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel = function ( cfg ) {
	cfg = cfg || {};

	this.store = new OOJSPlus.ui.data.store.RemoteStore( {
		action: 'bs-expiry-store',
		pageSize: 25
	} );

	cfg.grid = this.setupGridConfig();

	ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.parent.call( this, cfg );
};

OO.inheritClass( ext.bluespice.expiry.ui.panel.SpecialExpiryPanel, OOJSPlus.ui.panel.ManagerGrid );

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.prototype.setupGridConfig = function () {
	const gridCfg = {
		multiSelect: false,
		exportable: true,
		style: 'differentiate-rows',
		columns: {
			page_title: { // eslint-disable-line camelcase
				headerText: mw.message( 'bs-expiry-header-pagename' ).text(),
				type: 'text',
				sortable: true,
				filter: { type: 'text' },
				valueParser: ( value ) => new OO.ui.HtmlSnippet( mw.html.element(
					'a',
					{
						href: mw.util.getUrl( value )
					},
					value
				) )
			},
			expiry_date: { // eslint-disable-line camelcase
				headerText: mw.message( 'bs-expiry-header-date' ).text(),
				type: 'text',
				sortable: true,
				filter: { type: 'date' },
				valueParser: ( value ) => {
					const today = new Date();
					const expiryDate = new Date( value );
					if ( today > expiryDate ) {
						return new OO.ui.HtmlSnippet( mw.html.element(
							'span',
							{
								class: 'bs-expiry-date-column-expired'
							},
							value
						) );
					}
					return new OO.ui.HtmlSnippet( mw.html.element( 'span', {}, value ) );
				}
			},
			exp_comment: { // eslint-disable-line camelcase
				headerText: mw.message( 'bs-expiry-header-comment' ).text(),
				type: 'text',
				sortable: true,
				filter: { type: 'text' }
			},
			edit: {
				headerText: mw.message( 'bs-expiry-header-action-edit' ).text(),
				title: mw.message( 'bs-expiry-title-edit' ).text(),
				type: 'action',
				actionId: 'edit',
				icon: 'edit',
				invisibleHeader: true,
				visibleOnHover: true,
				width: 30
			},
			delete: {
				headerText: mw.message( 'bs-expiry-header-action-delete' ).text(),
				title: mw.message( 'bs-expiry-title-delete' ).text(),
				type: 'action',
				actionId: 'delete',
				icon: 'trash',
				invisibleHeader: true,
				visibleOnHover: true,
				width: 30
			}
		},
		store: this.store,
		provideExportData: () => {
			const deferred = $.Deferred();

			( async () => {
				try {
					this.store.setPageSize( 99999 );
					const response = await this.store.reload();
					const $table = $( '<table>' );

					const $thead = $( '<thead>' )
						.append( $( '<tr>' )
							.append( $( '<th>' ).text( mw.message( 'bs-expiry-header-pagename' ).text() ) )
							.append( $( '<th>' ).text( mw.message( 'bs-expiry-header-date' ).text() ) )
							.append( $( '<th>' ).text( mw.message( 'bs-expiry-header-comment' ).text() ) )
						);

					const $tbody = $( '<tbody>' );
					for ( const id in response ) {
						if ( response.hasOwnProperty( id ) ) {
							const record = response[ id ];
							$tbody.append( $( '<tr>' )
								.append( $( '<td>' ).text( record.page_title ) )
								.append( $( '<td>' ).text( record.expiry_date ) )
								.append( $( '<td>' ).text( record.exp_comment ) )
							);
						}
					}

					$table.append( $thead, $tbody );

					deferred.resolve( `<table>${ $table.html() }</table>` );
				} catch ( error ) {
					deferred.reject( 'Failed to load data' );
				}
			} )();

			return deferred.promise();
		}
	};

	return gridCfg;
};

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			icon: 'add',
			title: mw.message( 'bs-expiry-title-add' ).text(),
			displayBothIconAndLabel: true
		} )
	];
};

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		this.showExpiryDialog();
	}
	if ( action === 'edit' ) {
		const dialogData = {
			id: row.id,
			page: row.page_title,
			date: row.expiry_date,
			comment: row.exp_comment
		};

		this.showExpiryDialog( dialogData );
	}
	if ( action === 'delete' ) {
		bs.util.confirm(
			'REremove',
			{
				title: mw.message( 'bs-expiry-title-delete' ).text(),
				text: mw.message( 'bs-expiry-text-delete', 1 ).text()
			},
			{
				ok: () => {
					this.onRemoveExpiryOk( row.id );
				}
			}
		);
	}
};

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.prototype.onRemoveExpiryOk = async function ( id ) {
	await bs.api.tasks.exec(
		'expiry',
		'deleteExpiry',
		{
			expiryId: id
		}
	);

	this.store.reload();
};

ext.bluespice.expiry.ui.panel.SpecialExpiryPanel.prototype.showExpiryDialog = async function ( data = {} ) {
	const expiryPages = await bs.expiry.getDialogPages( {}, data );

	const dialogAdd = new OOJSPlus.ui.dialog.BookletDialog( {
		id: 'bs-expiry-dlg-add',
		pages: expiryPages
	} );

	const result = await dialogAdd.show().closed;
	if ( result.success ) {
		this.store.reload();
	}
};
