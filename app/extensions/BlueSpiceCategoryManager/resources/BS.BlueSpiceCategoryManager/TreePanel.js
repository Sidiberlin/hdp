Ext.define( "BS.BlueSpiceCategoryManager.TreePanel", {
	extend: 'BS.CRUDPanel',
	requires: [
		'BS.action.APIAddCategories',
		'BS.action.APIRemoveCategories',
		'BS.action.APIDeletePage',
		'BS.BlueSpiceCategoryManager.Model',
		'BS.dialog.BatchActions',
		'Ext.data.TreeStore',
		'BS.BlueSpiceCategoryManager.dialog.RenameCategory',
		'BS.BlueSpiceCategoryManager.action.ReplaceCategoryInPage',
		'BS.BlueSpiceCategoryManager.action.MoveCategoryPage'
	],
	originalParent: undefined,
	afterInitComponent: function () {
		this.store = new Ext.data.TreeStore({
			proxy: {
				type: 'ajax',
				url: mw.util.wikiScript( 'api' ),
				reader: {
					type: 'json',
					rootProperty: 'results',
					totalProperty: 'total'
				},
				extraParams: {
					action: 'bs-category-treestore',
					format: 'json'
				}
			},
			root: {
				text: 'Categories',
				id: 'src',
				expanded: true
			},
			model: 'BS.BlueSpiceCategoryManager.Model'
		});

		var me = this;

		this.selectedRow =  null;

		this.treePanel = new Ext.tree.Panel({
			useArrows: true,
			height: 500,
			rootVisible: false,
			displayField: 'text',
			store: this.store,
			rowLines: true,
			viewConfig: {
				stripeRows : true,
				plugins: {
					ptype: 'treeviewdragdrop',
					dragText: mw.message( 'bs-categorymanager-draganddrop-text' ).plain(),
					enableDrop: true,
					appendOnly: false,
					sortOnDrop: true,
					expandDelay: 250,
					allowParentInserts: true
				}
			},
			columns : [{
				header: mw.message('bs-categorymanager-category-column-name').plain(),
				xtype: 'treecolumn', //this is so we know which column will show the tree
				flex: 2,
				dataIndex: 'text',
				sortable: false
			},
			new Ext.grid.column.Action({
				header: mw.message('bs-extjs-actions-column-header').plain(),
				flex: 0,
				items: [
					{
						tooltip: mw.message('bs-extjs-delete').plain(),
						iconCls: 'bs-extjs-actioncolumn-icon bs-icon-cross destructive',
						glyph: true,
						handler: function( object, index, col, object2, object3, store) {
							me.onBtnRemoveClick( me.btnRemove, store, null);
						},
						isDisabled: function( view, rowIndex, colIndex, item, record ) {
							return record.get( 'tracking' ) ? true : false;
						}
					}, {
						tooltip: mw.message('bs-categorymanager-action-show-category').plain(),
						iconCls: 'bs-extjs-actioncolumn-icon bs-icon-eye',
						dataIndex: 'link',
						glyph: true,
						handler: function( object, index, col, object2, object3, store) {
							window.open( store.data.link, '_blank' );
						}
					}, {
						tooltip: mw.message('bs-categorymanager-rename-category').plain(),
						iconCls: 'bs-extjs-actioncolumn-icon bs-icon-wrench',
						dataIndex: 'link',
						glyph: true,
						handler: function( object, index, col, object2, object3, store) {
							var oldCategory = store.data.categoryName,
								newCategory = '',
								dialog = new BS.BlueSpiceCategoryManager.dialog.RenameCategory();

							dialog.setData(oldCategory);
							dialog.on( 'ok', function (){
								newCategory = dialog.getData();
								this.getCategoryNames().done( function(allCategories) {
									if ( allCategories.indexOf( newCategory.toLowerCase()) !== -1 ) {
										bs.util.alert(
											"bs-categorymanager",
											{
												titleMsg: 'bs-categorymanager-addcategory-dialog-error-duplicate-title',
												text: mw.message( 'bs-categorymanager-addcategory-dialog-error-duplicate-text' ).plain()
											}
										);
										return false;
									} else {
										this.treePanel.setLoading( true );
										this.getPages(oldCategory).done(function (pages){
											var dlgBatchActions = new BS.dialog.BatchActions( {
												id: this.makeId( 'dialog-batchactions-save' )
											} );
											dlgBatchActions.on( 'ok', function() {
												this.store.reload();
												var recordFullName = 'Category:' + oldCategory;
												var me = this;
												$.when(
													me.removeCategories( recordFullName, [oldCategory] )
												).always( function() {
													$.when(
														me.addCategories( recordFullName, [newCategory] )
													).always( function() {
														me.removeCategoryPage(recordFullName);
														me.treePanel.setLoading( false );
														me.treePanel.getStore().load({
															callback: function(records, operation, success) {
																me.treePanel.expandAll();
															}
														});
													});
												});
											}, this );

											var actions = [];
											var moveCategory = new BS.BlueSpiceCategoryManager.action.MoveCategoryPage({
												oldCategory: oldCategory,
												newCategory: newCategory
											});
											actions.push(moveCategory);
											if(pages.length > 0) {
												var action;
												for(var i = 0; i < pages.length; i++) {
													action = new BS.BlueSpiceCategoryManager.action.ReplaceCategoryInPage({
														pageTitle: pages[i].title,
														oldCategory: oldCategory,
														newCategory: newCategory,

													});
													actions.push(action);
												}
											}
											this.treePanel.setLoading( false );
											dlgBatchActions.setData( actions );
											dlgBatchActions.show();
											dlgBatchActions.startProcessing();
										}.bind(this))
									}
								}.bind(this) );
							}.bind(this));
							dialog.on('close', function() {
								Ext.destroy(dialog);
							});
							dialog.show();
						}.bind(this)
					}
				],
				menuDisabled: true,
				hideable: false,
				sortable: false
			})
			]
		} );

		this.treePanel.expandAll();
		this.treePanel.getView().on( 'drop', this.onDrop, this );
		this.treePanel.getView().on( 'beforedrop', this.onBeforedrop, this );
		this.treePanel.getView().on( 'itemclick', this.onItemclick, this );
		this.treePanel.on( 'itemexpand', this.onItemexpand, this );

		// Make sure that the tabGuard elements are not focusable, WCAG
		this.treePanel.on( 'afterrender', function() {
			if ( this.treePanel.el ) {
				this.treePanel.el.set( { tabIndex: -1 } );
			}
		}.bind( this ) );

		this.items = [
			this.treePanel
		];
		this.callParent();
	},
	onItemexpand: function  ( p, eOpts )   {
		if (!p.hasChildNodes()){
			p.collapse();
		}
	},
	onBeforedrop: function( node, data, overModel, dropPosition, dropHandler, eOpts ){
		Ext.Array.each( data.records, function ( record ) {
			this.originalParent = record.parentNode.get( 'categoryName' );
		});
	},
	onItemclick: function ( obj, record, item, index, e, eOpts ) {
		if((this.selectedRow === this.treePanel.getSelection()[0])) {
			this.btnRemove.disable();
			this.treePanel.setSelection(null);
			this.selectedRow = null;
		} else {
			this.btnRemove.enable();
			this.selectedRow = record;
		}
	},
	onDrop: function ( node, data, overModel, dropPosition, dropHandler, eOpts ) {
		var me = this;

		Ext.Array.each( data.records, function ( record ) {
			var recordFullName = 'Category:' + record.get( 'categoryName' );

			// append to category
			if( dropPosition === 'append' ) {
				$.when(
					me.removeCategories( recordFullName, [this.originalParent] )
				).always( function(){
					$.when(
						me.addCategories( recordFullName, [overModel.get( 'categoryName' )] )
					).always( function() {
						me.treePanel.setLoading( false );
						me.treePanel.getStore().load({
							callback: function(records, operation, success) {
								me.treePanel.expandAll();
							}
						});
					});
				});
			} else {
				// moved before or after
				if( overModel.parentNode.isRoot() ) {
					// moved to root, only remove category
					$.when(
						me.removeCategories( recordFullName, [this.originalParent] )
					).always( function(){
						me.treePanel.setLoading( false );
						me.treePanel.getStore().load({
							callback: function(records, operation, success) {
								me.treePanel.expandAll();
							}
						});
					});
				} else {
					// set category to parent of overModel
					$.when(
						me.removeCategories( recordFullName, [this.originalParent] )
					).always( function() {
						$.when(
							me.addCategories( recordFullName, [overModel.parentNode.get( 'categoryName' )] )
						).always( function() {
							me.treePanel.setLoading( false );
							me.treePanel.getStore().load({
								callback: function(records, operation, success) {
									me.treePanel.expandAll();
								}
							});
						});
					});
				}
			}

			this.originalParent = undefined;
		});
	},
	addCategories: function( page, categories ) {
		this.treePanel.setLoading( true );

		return new BS.action.APIAddCategories({
			pageTitle: page,
			categories: categories
		}).execute();
	},

	removeCategories: function( page, categories ) {
		this.treePanel.setLoading( true );
		return new BS.action.APIRemoveCategories({
			pageTitle: page,
			categories: categories
		}).execute();
	},

	getCategoryNames: function() {
		var dfd = $.Deferred();
		var categoryData = [];
		this.getCategoryNamesFromAPI( categoryData, dfd );
		return dfd.promise();
	},

	getCategoryNamesFromAPI: function( categoryData, dfd, accontinueValue ) {
		accontinueValue = accontinueValue || '';
		var params = {
			action: 'query',
			format: 'json',
			list: 'allcategories',
			accontinue: accontinueValue
		};
		api = new mw.Api();
		api.get( params ).done( function ( data ) {
			if( data.query.allcategories ) {
				for( var i = 0; i < data.query.allcategories.length; i++ ) {
					categoryData.push( data.query.allcategories[i][ '*' ].toLowerCase() );
				}
			}
			if(
				data.hasOwnProperty( 'continue' ) && data.continue.hasOwnProperty( 'accontinue' ) &&
				data.continue.accontinue !== ""
			) {
				accontinueValue = data.continue.accontinue;
				this.getCategoryNamesFromAPI( categoryData, dfd, accontinueValue );
			}
			else{
				accontinueValue = "";
				dfd.resolve( categoryData );
			}
		}.bind( this ));
	},

	removeCategoryPage: function( category ) {
		this.treePanel.setLoading( true );

		return new BS.action.APIDeletePage({
			pageTitle: category
		}).execute();
	},

	onBtnAddClick: function ( oButton, oEvent ) {
		var me = this;
		bs.util.prompt(
			"bs-categorymanager",
			{
				titleMsg: 'bs-categorymanager-dlg-new-title',
				textMsg: 'bs-categorymanager-dlg-new-prompt'
			},
			{
				ok: function( input ) {
					this.getCategoryNames().done(function ( categoryData ) {
						if ( categoryData.indexOf( input.value.toLowerCase()) !== -1 ) {
							return bs.util.alert(
								"bs-categorymanager",
								{
									titleMsg: 'bs-categorymanager-addcategory-dialog-error-duplicate-title',
									text: mw.message( 'bs-categorymanager-addcategory-dialog-error-duplicate-text' ).plain()
								}
							);
						}
						else {
							me.treePanel.setLoading( true );
							var titleToAdd = mw.Title.makeTitle( bs.ns.NS_CATEGORY, input.value );
							var parentCat = me.getSelectedCategoryTitle();
							var addCategoryAction = new BS.action.APIAddCategories({
								pageTitle: titleToAdd.getPrefixedDb(),
								categories: parentCat ? [ parentCat.getPrefixedDb() ] : []
							});
							addCategoryAction.execute()
							.done( function( resp ) {
								me.treePanel.setLoading( false );
								me.treePanel.getStore().load({
									callback: function(records, operation, success) {
										me.treePanel.expandAll();
									}
								});
							})
							.fail( function( jqXHR, textStatus, response ) {
								bs.util.alert(
									"bs-categorymanager",
									{
										titleMsg: 'bs-categorymanager-addcategory-dialog-error-title',
										text: response.message
									}
								);
								me.treePanel.setLoading( false );

							});
						}
					});
				},
				scope: this
			}
		);
	},

	getSelectedCategoryTitle: function() {
		var selectedNode,
			selectedCategory = '',
			selection = this.treePanel.getSelection();
		if ( selection.length === 0 ) {
			return null;
		}
		selectedNode = selection[0];
		selectedCategory = selectedNode.getData().categoryName;

		return mw.Title.makeTitle( bs.ns.NS_CATEGORY, selectedCategory );
	},

	onBtnRemoveClick: function ( oButton, oStore, oEvent ) {
		var me = this;
		bs.util.confirm( 'RemoveCategory', {
				text: mw.message( 'bs-categorymanager-removecategoryconfirm-text' ).plain(),
				title: mw.message( 'bs-categorymanager-removecategoryconfirm-title' ).plain(),
			},
			{
				ok: function() {
					var category;
					if ( oStore.data && oStore.data.text ) {
						category = oStore.data.text;
					} else {
						category = this.selectedRow.data.text;
					}

					var categoryEntire = 'Category:' + category;

					me.treePanel.setLoading( true );
					me.getRemoveCategoryActions( categoryEntire )
						.done( function( categoryActions ) {
							me.getRemoveCategoryPageAction( categoryEntire ).done( function( action ) {
								if ( action ) {
									categoryActions.push( action );
								}
								var batchDialog = new BS.dialog.BatchActions( {} );
								batchDialog.setData( categoryActions );
								batchDialog.show();
								batchDialog.startProcessing();
								batchDialog.on( 'ok', function() {
									me.treePanel.setLoading( false );
									me.treePanel.getStore().load( {
										callback: function( records, operation, success ) {
											me.treePanel.expandAll();
										}
									} );
								}, this );
							} ).fail( function() {
								me.treePanel.setLoading( false );
							} );
						} )
						.fail( function() {
							me.treePanel.setLoading( false );
						} );
				},
				scope: this
			}
		);
	},

	getRemoveCategoryActions: function( category ) {
		var dfd = $.Deferred(),
			queryData = {
				cmtitle: category,
				titles: category,
			},
			categoryActions = [];
		this.getRemoveCategoryActionsRecursive( dfd, queryData, categoryActions );
		return dfd.promise();
	},

	getRemoveCategoryActionsRecursive: function( dfd, queryData, categoryActions ) {
		var api = new mw.Api();
		queryData = $.extend( {
			action: 'query',
			list: 'categorymembers',
			prop: 'pageprops'
		}, queryData );
		api.get( queryData ).done( function ( response ) {
			if ( !response.query.hasOwnProperty( 'categorymembers' ) ||
				response.query.categorymembers.length === 0 ){
				dfd.resolve( categoryActions );
			}
			for ( var i = 0; i < response.query.categorymembers.length; i++ ) {
				var action = new BS.action.APIRemoveCategories( {
					pageTitle: response.query.categorymembers[i].title,
					categories: [ queryData.cmtitle ]
				} );
				categoryActions.push( action );
			}

			if ( response.hasOwnProperty( 'continue' ) ) {
				queryData = $.extend( queryData, response.continue );
				this.getRemoveCategoryActionsRecursive( dfd, queryData, categoryActions );
			} else {
				dfd.resolve( categoryActions );
			}
		}.bind( this ) ).fail( function() {
			dfd.reject();
		} );
	},

	getRemoveCategoryPageAction: function( category ) {
		var dfd = $.Deferred();

		new mw.Api().get( {
			action: 'query',
			prop: 'pageprops',
			titles: category
		} ).done( function( response ) {
			for ( var pageId in response.query.pages ) {
				if ( pageId > 0 ) {
					dfd.resolve( new BS.action.APIDeletePage( {
						pageTitle: category
					} ) );
				}
			}
			dfd.resolve( null );
		} ).fail( function() {
			dfd.reject();
		} );

		return dfd.promise();
	},

	opPermitted: function( operation ) {
		//Edit functionality is not yet implemented. For the time being this
		//is the simplest way to hide the "edit" button from the UI.
		if( operation === 'update' ) {
			return false;
		}
		return this.callParent( arguments );
	},

	getPages: function (category) {
		var dfd = $.Deferred();

		var pages = [];

		this.getPagesFromAPI(category, pages, dfd);

		return dfd.promise();
	},

	getPagesFromAPI: function (category, pages, dfd, cmcontinueValue) {
		var params = {
			action: "query",
			list: "categorymembers",
			cmtitle: "category:" + category,
			format: "json",
			cmcontinue: cmcontinueValue
		};

		new mw.Api().get(params).done(function (json) {
			if(json.query.categorymembers){
				for(var i = 0; i < json.query.categorymembers.length; i++) {
					pages.push(json.query.categorymembers[i]);
				}
			}
			if(json.continue && json.continue.cmcontinue != "") {
				cmcontinueValue = json.continue.cmcontinue;
				this.getPagesFromAPI(category, pages, dfd, cmcontinueValue);
			}
			else{
				cmcontinueValue = "";
				dfd.resolve(pages);
			}
		}.bind(this));
	}
} );
