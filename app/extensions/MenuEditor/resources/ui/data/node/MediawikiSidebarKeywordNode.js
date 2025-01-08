ext.menueditor.ui.data.node.MediawikiSidebarKeywordNode = function ( cfg ) {
	ext.menueditor.ui.data.node.MediawikiSidebarKeywordNode.parent.call( this, cfg );

	var config = require( './config.json' );
	this.keywords = config.allowedMediawikiSidebarKeywords;

	this.options = [];
	for ( var i = 0; i < this.keywords.length; i++ ) {
		var object = {
			data: this.keywords[ i ]
		};
		this.options.push( object );
	}
};

OO.inheritClass( ext.menueditor.ui.data.node.MediawikiSidebarKeywordNode,
	ext.menueditor.ui.data.node.KeywordNode );

ext.menueditor.ui.data.node.MediawikiSidebarKeywordNode.prototype.getFormFields = function ( dialog ) { // eslint-disable-line max-len
	return [
		{
			name: 'keyword',
			type: 'dropdown',
			options: this.options,
			// eslint-disable-next-line camelcase
			widget_$overlay: dialog.$overlay,
			required: true,
			label: mw.message( 'menueditor-ui-form-field-keyword' ).text(),
			help: mw.message( 'menueditor-ui-menu-keyword-help' ).text()
		}
	];
};

ext.menueditor.ui.data.node.MediawikiSidebarKeywordNode.prototype.shouldRender = function () {
	return this.keywords.length > 0;
};
