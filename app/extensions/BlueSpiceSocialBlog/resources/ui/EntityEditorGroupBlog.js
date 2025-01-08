bs.social.EntityEditorGroupBlog = function ( config, entity ) {
	bs.social.EntityEditorBlog.call( this, config, entity );
};
OO.initClass( bs.social.EntityEditorGroupBlog );
OO.inheritClass( bs.social.EntityEditorGroupBlog, bs.social.EntityEditorBlog);

bs.social.EntityEditorGroupBlog.prototype.makeFields = function() {
	var fields = bs.social.EntityEditorGroupBlog.super.prototype.makeFields.apply(
		this
	);

	this.editgroups = new OO.ui.HiddenInputWidget( {
		data: this.getEntity().data.get( 'editgroups', [] )
	});
	fields.editgroups = this.editgroups;

	this.readgroups = new OO.ui.HiddenInputWidget( {
		data: this.getEntity().data.get( 'readgroups', [] )
	});
	fields.readgroups = this.readgroups;

	this.commentgroups = new OO.ui.HiddenInputWidget( {
		data: this.getEntity().data.get( 'commentgroups', [] )
	});
	fields.commentgroups = this.commentgroups;

	this.deletegroups = new OO.ui.HiddenInputWidget( {
		data: this.getEntity().data.get( 'deletegroups', [] )
	});
	fields.deletegroups = this.deletegroups;

	return fields;
};