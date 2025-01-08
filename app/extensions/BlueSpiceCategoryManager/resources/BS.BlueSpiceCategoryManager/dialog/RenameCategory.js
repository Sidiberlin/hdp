Ext.define( 'BS.BlueSpiceCategoryManager.dialog.RenameCategory', {
	extend: 'MWExt.Dialog',

	title: mw.message('bs-categorymanager-rename-category').plain(),

	makeItems: function() {
		this.categoryNameInput = new Ext.form.field.Text({
			id: 'categoryName',
			fieldLabel: mw.message('bs-categorymanager-rename-category-new-name').plain(),
		});

		return [
			this.categoryNameInput
		];
	},

	setData: function( name ) {
		this.categoryNameInput.setValue( name );
	},

	getData: function() {
		return this.categoryNameInput.getValue();
	}
});
