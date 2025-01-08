( function( d, mw, $, bs, undefined ) {

	var activated = function( ns ) {
		var namespaces = mw.config.get( 'bsgReadConfirmationActivatedNamespaces', [] );
		for( var i = 0; i < namespaces.length; i++ ) {
			if ( parseInt( ns ) !== parseInt( namespaces[i] ) ) {
				continue;
			}
			return true;
		}
		return false;
	};

	function __showDialog( pageId, pageTitle ) {
		var dialog = new OOJSPlus.ui.dialog.BookletDialog( {
			id: 'bs-readconfirmation-user-list',
			pages: function() {
				var dfd = $.Deferred();
				mw.loader.using( "ext.readconfirmation.dialog.pages", function() {
					dfd.resolve( [ new bs.readconfirmation.ui.ReadConfirmationPage( {
						data: {
							page: pageTitle,
							pageId: pageId
						}
					} ) ] );
				}, function( e ) {
					dfd.reject( e );
				} );
					return dfd.promise();
				}
		} );
		dialog.show();
	}

	$( d ).on( 'BSPageAssignmentsManagerPanelInit', function( e, sender, cols, fields, actions ){
		fields.push( 'all_assignees_have_read' );

		cols.push({
			text: mw.message('bs-readconfirmation-column-read').plain(),
			dataIndex: 'all_assignees_have_read',
			flex: 0,
			width: 70,
			align: 'center',
			sortable: true,
			filter:{
				type: 'boolean'
			},
			renderer: function( value, metaData, record, rowIndex, colIndex, store, view ) {
				if( activated( record.get( 'page_namespace' ) ) ) {
					return mw.html.element( 'span', {
						class: 'bs-rc-col ' + ( value ? 'bs-icon-checkmark-circle yes' : 'bs-icon-cancel-circle no' )
					});
				}
				return '<em>' + mw.message( 'bs-readconfirmation-disabled-ns-short' ).plain() +'</em>';
			}
		});

		actions.push({
			tooltip: mw.message('bs-readconfirmation-action-log').plain(),
			glyph: true, //Needed to have the "BS.override.grid.column.Action" render an <span> instead of an <img>
			scope: this,
			handler: function( view, rowIndex, colIndex,item, e, record, row ) {
				window.open(
					bs.util.wikiGetlink( {
						page: record.get( 'page_prefixedtext' ),
						type: 'bs-readconfirmation'
					}, 'Special:Log' )
				);
			},
			getClass: function( value, meta, record ) {
				return "bs-icon-text bs-extjs-actioncolumn-icon bs-readconfirmation-action-log";
			},
			isDisabled: function( view, rowIndex, colIndex, item, record  ) {
				return !activated( record.get( 'page_namespace' ) );
			}
		});

		actions.push({
			tooltip: mw.message('bs-readconfirmation-action-remind').plain(),
			glyph: true, //Needed to have the "BS.override.grid.column.Action" render an <span> instead of an <img>
			scope: this,
			handler: function( view, rowIndex, colIndex,item, e, record, row ) {
				bs.util.confirm( 'bs-rc', {
					textMsg: 'bs-readconfirmation-action-remind-confirm'
				}, {
					ok: function() {
						bs.api.tasks.exec( 'readconfirmation', 'remind', {
							pageId: record.get( 'page_id' )
						} );
					}
				});
			},
			getClass: function( value, meta, record ) {
				return "bs-icon-bell bs-extjs-actioncolumn-icon bs-readconfirmation-action-remind";
			},
			isDisabled: function( view, rowIndex, colIndex, item, record  ) {
				if( !record.get( 'assignments' ) || record.get( 'assignments' ).length < 1 ) {
					return true;
				}
				return record.get( 'all_assignees_have_read' ) || !activated( record.get( 'page_namespace' ) );
			}
		});

		if ( mw.config.get( 'bsReadConfirmationsViewRight' ) ) {
			actions.push( {
				iconCls: 'bs-icon-eye bs-extjs-actioncolumn-icon',
				glyph: true,
				tooltip: mw.message( 'bs-readconfirmation-view-confirmations' ).plain(),
				handler: function( view, rowIndex, colIndex,item, e, record, row ) {
					var pageId = record.get( 'page_id' );
					var pageTitle = record.get( 'page_title' );
					__showDialog( pageId, pageTitle );
				},
				isDisabled: function( view, rowIndex, colIndex, item, record  ) {
					return !activated( record.get( 'page_namespace' ) );
				},
				scope: this
			} );
		}
	});

	mw.hook( 'BSPageAssignmentsOverviewPanelInit' ).add( ( gridCfg ) => {
		gridCfg.columns.read_confirmation = { // eslint-disable-line camelcase
			headerText: mw.message( 'bs-readconfirmation-column-read-at' ).plain(),
			type: 'text',
			sortable: true,
			filter: { type: 'date' },
			valueParser: ( val ) => {
				if ( !val ) {
					return mw.message( 'bs-readconfirmation-not-read' ).plain();
				}
				if ( val === 'disabled' ) {
					return mw.message( 'bs-readconfirmation-disabled-ns' ).plain();
				}

				const date = Ext.Date.parse( val, 'YmdHis' );
				const dateRenderer = Ext.util.Format.dateRenderer( 'Y-m-d, H:i' );

				return dateRenderer( date );
			}
		};
	} );
} )( document, mediaWiki, jQuery, blueSpice );
