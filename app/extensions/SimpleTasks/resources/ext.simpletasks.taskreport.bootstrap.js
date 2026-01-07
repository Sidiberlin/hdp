( function ( mw, $ ) {

	function getGridConfig() {
		const gridCfg = {
			pageSize: 10,
			columns: {
				taskdescription: {
					headerText: mw.message( 'simple-tasks-taskreport-header-task' ).text(),
					type: 'text',
					sortable: true,
					autoClosePopup: true,
					valueParser: function ( val ) {
						return new OO.ui.HtmlSnippet( val );
					},
					filter: {
						type: 'text'
					}
				},
				assignee: {
					headerText: mw.message( 'simple-tasks-taskreport-header-assignee' ).text(),
					type: 'user',
					urlExternal: false,
					autoClosePopup: true,
					filter: {
						type: 'user',
						closePopupOnChange: true
					}
				},
				page: {
					headerText: mw.message( 'simple-tasks-taskreport-header-page' ).text(),
					type: 'url',
					urlExternal: false,
					autoClosePopup: true,
					urlProperty: 'pageUrl',
					filter: {
						type: 'text'
					},
					valueParser: function ( val ) {
						// Truncate long titles
						return val.length > 35 ? val.slice( 0, 34 ) + '...' : val;
					}
				},
				duedate: {
					headerText: mw.message( 'simple-tasks-taskreport-header-due-to' ).text(),
					type: 'text',
					sortable: true,
					autoClosePopup: true,
					filter: {
						type: 'text'
					}
				},
				state: {
					headerText: mw.message( 'simple-tasks-taskreport-header-state' ).text(),
					/* eslint-disable-next-line no-unused-vars */
					valueParser: function ( value, row ) {
						let state = 'open',
							iconName = 'color-cross';
						if ( value === 'done' ) {
							state = 'done';
							iconName = 'color-check';
						}
						return new OO.ui.IconWidget( {
							icon: iconName,
							// The following messages are used here:
							// * simple-tasks-taskreport-state-title-open
							// * simple-tasks-taskreport-state-title-done
							title: mw.message( 'simple-tasks-taskreport-state-title-' + state ).text()
						} ).$element;
					},
					sortable: true,
					autoClosePopup: true,
					filter: {
						type: 'list',
						list: [
							{ data: 'done', label: mw.message( 'simple-tasks-taskreport-state-filter-done' ).text() },
							{ data: 'open', label: mw.message( 'simple-tasks-taskreport-state-filter-open' ).text() }
						]
					}
				}
			}
		};
		return gridCfg;
	}

	function getReport( counter, $taskreport ) {
		const api = new ext.simpletasks.api.Api();
		api.getTasksFromFilter( counter, $taskreport.data( 'filter' ) )
			.done( ( response ) => {
				const reports = response.tasks;

				if ( reports.length < 1 ) {
					const labelWidget = new OO.ui.LabelWidget( {
						classes: [ 'taskreport-no-result' ],
						label: mw.message( 'simple-tasks-taskreport-no-results' ).text()
					} );
					$taskreport.append( labelWidget.$element );
					$( $taskreport ).removeClass( 'load' );
					return;
				}

				const gridCfg = getGridConfig();
				const tasks = [];
				reports.forEach( ( task ) => {
					tasks.push( {
						taskdescription: task.text,
						assignee: task.assignee,
						duedate: task.dueDate,
						state: task.completed,
						page: task.page_title,
						pageUrl: task.page_url
					} );
				} );

				gridCfg.data = tasks;
				const grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
				$taskreport.append( grid.$element );
				$( $taskreport ).removeClass( 'load' );
			} );
	}

	$( () => {
		/* eslint-disable-next-line no-jquery/no-global-selector */
		if ( $( '.ve-activated' ).length > 0 ) {
			return;
		}
		/* eslint-disable-next-line no-jquery/no-global-selector */
		const $taskreports = $(
			'div.task-report[data-filter]'
		);

		mw.loader.using( [ 'ext.simpletasks.api' ] ).done( () => {
			for ( let counter = 0; counter < $taskreports.length; counter++ ) {
				getReport( counter, $( $taskreports[ counter ] ) );
			}
		} );
	} );
}( mediaWiki, jQuery ) );
