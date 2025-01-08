bs.ue.ui.plugin.Html = function ( config ) {
	bs.ue.ui.plugin.Html.parent.call( this, config );
};

OO.inheritClass( bs.ue.ui.plugin.Html, bs.ue.ui.plugin.Plugin );

bs.ue.registry.Plugin.register( 'html', bs.ue.ui.plugin.Html );

bs.ue.ui.plugin.Html.prototype.getName = function () {
	return 'html';
};

bs.ue.ui.plugin.Html.prototype.getFavoritePosition = function () {
	return 30;
};

bs.ue.ui.plugin.Html.prototype.getLabel = function () {
	return mw.message( 'bs-uemodulehtml-export-dialog-label-module-name' ).text();
};

bs.ue.ui.plugin.Html.prototype.getPanel = function () {
	var modulePanel = new OO.ui.PanelLayout( {
		expanded: false,
		framed: false,
		padded: false,
		$content: ''
	} );

	return modulePanel;
};

bs.ue.ui.plugin.Html.prototype.getParams = function () {
	var params = {
		module: 'html'
	};

	return params;
};
