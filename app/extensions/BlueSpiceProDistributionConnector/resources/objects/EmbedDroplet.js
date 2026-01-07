bs.util.registerNamespace( 'ext.proDistribution.objects' );

ext.proDistribution.objects.EmbedDroplet = function ( cfg ) {
	ext.proDistribution.objects.EmbedDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.proDistribution.objects.EmbedDroplet, ext.contentdroplets.object.ParserFunctionDroplet );

ext.proDistribution.objects.EmbedDroplet.prototype.functionMatches = function ( data ) {
	if ( !data ) {
		return false;
	}
	return data.target.function === 'embed';
};

ext.proDistribution.objects.EmbedDroplet.prototype.toDataElement = function ( domElements, converter ) { // eslint-disable-line no-unused-vars
	return false;
};

ext.proDistribution.objects.EmbedDroplet.prototype.getFormItems = function () {
	return [
		{
			name: 'url',
			label: mw.message( 'bs-pro-distribution-droplet-ec-embed-url-label' ).text(),
			type: 'text',
			required: true
		},
		{
			name: 'render',
			label: mw.message( 'bs-pro-distribution-droplets-ec-embed-render-label' ).text(),
			type: 'checkbox'
		},
		{
			name: 'lang',
			label: mw.message( 'bs-pro-distribution-droplets-ec-embed-lang-label' ).text(),
			help: new OO.ui.HtmlSnippet(
				mw.message( 'bs-pro-distribution-droplets-ec-embed-lang-help' ).parse()
			),
			type: 'text'
		},
		{
			name: 'showLines',
			label: mw.message( 'bs-pro-distribution-droplets-ec-embed-show-lines-label' ).text(),
			help: mw.message( 'bs-pro-distribution-droplets-ec-embed-show-lines-help' ).text(),
			type: 'text'
		},
		{
			name: 'lineNumbers',
			label: mw.message( 'bs-pro-distribution-droplets-ec-embed-show-line-numbers-label' ).text(),
			type: 'checkbox',
			value: false
		}
	];
};

ext.proDistribution.objects.EmbedDroplet.prototype.getMainParam = function () {
	return 'url';
};

ext.proDistribution.objects.EmbedDroplet.prototype.convertDataForForm = function ( data ) {
	let key;
	for ( key in data ) {
		// If key is numeric
		if ( !isNaN( key ) && key.toString() === parseInt( key, 10 ).toString() ) {
			const value = data[ key ];
			data[ value ] = true;
			delete data[ key ];
		}
	}
	return data;
};

ext.contentdroplets.registry.register( 'ec-embed', ext.proDistribution.objects.EmbedDroplet );
