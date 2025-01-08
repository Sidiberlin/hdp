bs.social.EntityGroupBlog = function( $el, type, data ) {
	bs.social.EntityBlog.call( this, $el, type, data );
};
OO.initClass( bs.social.EntityGroupBlog );
OO.inheritClass( bs.social.EntityGroupBlog, bs.social.EntityBlog );

bs.social.EntityGroupBlog.prototype.makeEditor = function() {
	return new bs.social.EntityEditorGroupBlog(
		this.getEditorConfig(),
		this
	);
};

bs.social.EntityGroupBlog.static.name = "\\BlueSpice\\Social\\Blog\\Entity\\GroupBlog";
bs.social.factory.register( bs.social.EntityGroupBlog );