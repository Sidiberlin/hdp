( ( mw, bs ) => {

	const showDialog = async ( pageId, pageTitle ) => {
		await mw.loader.using( 'ext.readconfirmation.dialog.pages' );
		const assignmentPages = new bs.readconfirmation.ui.ReadConfirmationPage( {
			data: {
				page: pageTitle,
				pageId: pageId
			}
		} );

		const dialog = new OOJSPlus.ui.dialog.BookletDialog( {
			id: 'bs-readconfirmation-user-list',
			pages: [ assignmentPages ]
		} );
		dialog.show();
	};

	mw.hook( 'BSPageAssignmentsManagerPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.all_assignees_have_read = { // eslint-disable-line camelcase
			headerText: mw.message( 'bs-readconfirmation-column-read' ).text(),
			type: 'text',
			sortable: true,
			filter: {
				type: 'list',
				list: [
					{ data: true, label: mw.message( 'oojsplus-data-grid-filter-boolean-true' ).text() },
					{ data: false, label: mw.message( 'oojsplus-data-grid-filter-boolean-false' ).text() },
					{ data: 'disabled', label: mw.message( 'bs-readconfirmation-disabled-ns-short' ).text() }
				]
			},
			valueParser: ( val ) => {
				let icon;
				let iconClass;
				let disabled = false;
				switch ( val ) {
					case true:
						icon = 'check';
						iconClass = 'oo-ui-icon-color-check';
						break;
					case false:
						icon = 'close';
						iconClass = 'oo-ui-icon-color-cross';
						break;
					case 'disabled':
						icon = 'subtract';
						iconClass = 'oo-ui-widget-disabled';
						disabled = true;
						break;
				}

				const iconWidget = new OO.ui.IconWidget( { // eslint-disable-line mediawiki/class-doc
					icon: icon,
					classes: [ iconClass ],
					disabled: disabled
				} );

				return new OO.ui.HtmlSnippet( iconWidget.$element );
			}
		};
		gridCfg.actions.secondaryActions.actions.push( {
			label: mw.message( 'bs-readconfirmation-action-log' ).text(),
			title: mw.message( 'bs-readconfirmation-action-log' ).text(),
			data: 'readConfirmationLog',
			icon: 'article',
			doActionOnRow: ( row ) => {
				window.location.href = mw.util.getUrl(
					'Special:Log', {
						page: row.page_prefixedtext,
						type: 'bs-readconfirmation'
					}
				);
			}
		} );
		gridCfg.actions.secondaryActions.actions.push( {
			label: mw.message( 'bs-readconfirmation-action-remind' ).text(),
			title: mw.message( 'bs-readconfirmation-action-remind' ).text(),
			data: 'readConfirmationRemind',
			icon: 'bell',
			doActionOnRow: ( row ) => {
				bs.util.confirm( 'bs-rc', {
					textMsg: 'bs-readconfirmation-action-remind-confirm'
				}, {
					ok: () => {
						bs.api.tasks.execSilent( 'readconfirmation', 'remind', {
							pageId: row.page_id
						} );
					}
				} );
			}
		} );
		if ( mw.config.get( 'bsReadConfirmationsViewRight' ) ) {
			gridCfg.actions.secondaryActions.actions.push( {
				label: mw.message( 'bs-readconfirmation-view-confirmations' ).text(),
				title: mw.message( 'bs-readconfirmation-view-confirmations' ).text(),
				data: 'readConfirmationView',
				icon: 'eye',
				doActionOnRow: ( row ) => {
					const pageId = row.page_id;
					const pageTitle = row.page_title;
					showDialog( pageId, pageTitle );
				}
			} );
		}
	} );

	mw.hook( 'BSPageAssignmentsOverviewPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.read_confirmation_display = { // eslint-disable-line camelcase
			headerText: mw.message( 'bs-readconfirmation-column-read-at' ).text(),
			type: 'text',
			sortable: true,
			filter: { type: 'date' },
			valueParser: ( val ) => {
				if ( !val ) {
					return mw.message( 'bs-readconfirmation-not-read' ).text();
				}
				if ( val === 'disabled' ) {
					return mw.message( 'bs-readconfirmation-disabled-ns' ).text();
				}

				return bs.util.convertMWTimestampToISO( val );
			}
		};
	} );
} )( mediaWiki, blueSpice );
