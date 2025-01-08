bs.util.registerNamespace( 'bs.vec.ui' );

bs.vec.ui.TextStyleInspector = function ( inspector, config ) {
	config.padded = false;
	bs.vec.ui.TextStyleInspector.super.call( this, inspector, config );
	this.buttons = {};
	this.addedTools = {};
	this.$element.addClass( 'bs-vec-ui-TextStyleInspector' );
};

OO.inheritClass( bs.vec.ui.TextStyleInspector, ext.visualEditorPlus.ui.InlineTextInspectorElement );

bs.vec.ui.TextStyleInspector.prototype.inspect = function ( range, selectedText ) {
	var fragment = this.inspector.target.getSurface().getModel().getLinearFragment( range );
	for ( var name in this.addedTools ) {
		this.addedTools[name].setFragment( fragment );
	}
};

bs.vec.ui.TextStyleInspector.prototype.init = function () {
	let registry = bs.vec.registry.TextStyleTool.registry;
	for( let name in registry ) {
		if ( registry[name].hasOwnProperty( 'constructor' ) === false ) {
			continue;
		}
		let tool = registry[name].constructor;
		let toolInstance = new tool( {
			surface: this.inspector.target.getSurface(),
		} );
		this.addedTools[name] = toolInstance;
		this.$element.append( toolInstance.$element );
	}
};

bs.vec.ui.TextStyleInspector.prototype.getTool = function ( name ) {
	if ( this.addedTools.hasOwnProperty( name ) ) {
		return this.addedTools[name];
	}
	return null;
};

bs.vec.ui.TextStyleInspector.prototype.getPriority = function () {
	return 1;
};

ext.visualEditorPlus.registry.inlineTextInspectors.register( 'VECTextStyleInspector', bs.vec.ui.TextStyleInspector );
