Ext.define( 'BS.BlueSpiceCategoryManager.action.MoveCategoryPage', {
	extend: 'BS.action.Base',
	oldCategory: '',
	newCategory: '',

	execute: function() {
		var dfd = $.Deferred(),
			oldCategory = mw.Title.makeTitle(bs.ns.NS_CATEGORY, this.oldCategory),
			newCategory = mw.Title.makeTitle(bs.ns.NS_CATEGORY, this.newCategory),
			params = {
				'action': 'move',
				'from': oldCategory.getPrefixedDb(),
				'to': newCategory.getPrefixedDb(),
				'movetalk': true,
				'ignorewarnings': true
			};

		this.actionStatus = BS.action.Base.STATUS_RUNNING;

		new mw.Api().postWithEditToken(params).done(function() {
			this.actionStatus = BS.action.Base.STATUS_DONE;
			dfd.resolve( this );
		}.bind(this))
		.fail(function() {
			this.actionStatus = BS.action.Base.STATUS_ERROR;
			dfd.reject( this );
		}.bind(this));

		return dfd.promise();
	},

	getDescription: function() {
		return mw.message('bs-categorymanager-move-new-name').plain() + this.newCategory;
	}
} );
