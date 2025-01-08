Ext.define( 'BS.BlueSpiceCategoryManager.action.ReplaceCategoryInPage', {
	extend: 'BS.action.Base',
	pageTitle: '',
	oldCategory: '',
	newCategory: '',

	execute: function() {
		var dfd = $.Deferred();

		new mw.Api().edit( this.pageTitle, function ( revision ) {
			const categorymembers = this.getCategoryNames();
			const oldCategoryNames = this.prepareNewCategoryName(this.oldCategory);
			var regex = new RegExp("\\[\\[(" + categorymembers + "):(" + oldCategoryNames + ")\\]\\]", "gmi");
			const newtext = revision.content.replace(regex, "[[$1:" + this.newCategory + "]]");
			return {
				text: newtext,
				summary: 'Replace "' + this.oldCategory + '" with "' + this.newCategory + '".',
				minor: true
			};
		}.bind(this))
		.done( function () {
			this.actionStatus = BS.action.Base.STATUS_DONE;
			dfd.resolve( this );
		}.bind(this))
		.fail(function(){
			this.actionStatus = BS.action.Base.STATUS_ERROR;
			dfd.reject( this );
		}.bind(this));

		return dfd.promise();
	},

	getCategoryNames: function() {
		const namespaceIds = mw.config.get("wgNamespaceIds");
		var categorynames = "";

		for (const key in namespaceIds) {
			if(namespaceIds[key] == 14){
				categorynames = categorynames + key + "|";
			}
		}

		// Remove last '|'
		if(categorynames.length > 0) {
			categorynames = categorynames.substring(0, categorynames.length - 1);
		}
		return categorynames;
	},


	/**
	* Build the category string for a regex.
	* For the mediawiki is "_" and " " the same.
	* So we need to bild both versions separated with an "|".
	*
	* Example: Tag name is "sth_sth sth" result is "sth sth sth|sth_sth_sth"
	*
	* @param {
	* newCategory: The category name.
	*
	*/
	prepareNewCategoryName: function(category) {
		let result = "";
		if(category.includes(" ") && category.includes("_")){
			const withUnderscore = category.replace(" ", "_");
			const withWhitespaces = withUnderscore.replace("_", " ");
			result = withUnderscore + "|" + withWhitespaces;
		} else if(category.includes("_")){
			result = category + "|" + category.replace("_", " ");
		} else if(category.includes(" ")){
			result = category + "|" + category.replace(" ", "_");
		} else {
			result = category;
		}
		return result;
	},

	getDescription: function() {
		return mw.message('bs-categorymanager-replace-in-pages').plain() + this.pageTitle;
	}
} );
