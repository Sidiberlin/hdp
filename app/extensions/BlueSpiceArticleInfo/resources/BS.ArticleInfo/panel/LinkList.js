Ext.define( 'BS.ArticleInfo.panel.LinkList', {
	extend: 'Ext.Panel',
	cls: 'bs-articleinfo-flyout-linklist-cnt',
	title: '',
	storeField: '',
	emptyText: '',
	apiAction: null,
	linkList: {},
	listType: 'ul',
	initComponent: function () {
		if ( this.apiAction !== null ) {
			this.store = Ext.create( 'BS.store.BSApi', {
				apiAction: this.apiAction,
				fields: [ this.storeField ],
				filters: [ {
					"type": "numeric",
					"operator": "eq",
					"property": "page_id",
					"value": mw.config.get( 'wgArticleId' )
				} ],
				proxy: {
					extraParams: {
						limit: -1,
					}
				},
				autoLoad: false
			} );
		} else {
			this.store = new Ext.data.Store( {
				fields: [ this.storeField ],
				data: this.linkList,
				autoLoad: false
			} );
		}

		this.cls = 'bs-articleinfo-flyout-linklist';
		if ( this.listType === 'pills' ) {
			this.cls += ' pills';
		}


		this.store.on( 'load', this.onStoreLoad, this );
		this.store.load();

		this.callParent( arguments );
	},

	onStoreLoad: function ( store, records, successful, eOpts ) {
		var links = [];
		for ( var i = 0; i < records.length; i++ ) {
			var record = records[i];
			links.push( record.get( this.storeField ) );
		}

		var html = '<div class="' + this.cls + '">' + links.join( '' ) + '</div>';

		this.update( html );
	}
});
