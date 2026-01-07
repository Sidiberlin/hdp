bs.util.registerNamespace( 'ext.proDistribution.objects' );

ext.proDistribution.objects.ProConListDroplet = function ( cfg ) {
	ext.proDistribution.objects.ProConListDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.proDistribution.objects.ProConListDroplet, ext.contentdroplets.object.TransclusionDroplet );

ext.proDistribution.objects.ProConListDroplet.prototype.templateMatches = function ( templateData ) {
	if ( !templateData ) {
		return false;
	}
	const target = templateData.target.wt;
	return target.trim( '\n' ) === 'ProConList' && this.getKey() === 'pro-con-list';
};

ext.proDistribution.objects.ProConListDroplet.prototype.toDataElement = function ( domElements, converter ) { // eslint-disable-line no-unused-vars
	return false;
};

ext.proDistribution.objects.ProConListDroplet.prototype.getFormItems = function () {
	return [
		{
			name: 'title-advantages',
			label: mw.message( 'droplets-pro-con-advantages-title-label' ).text(),
			type: 'text'
		},
		{
			name: 'title-disadvantages',
			label: mw.message( 'droplets-pro-con-disadvantages-title-label' ).text(),
			type: 'text'
		},
		{
			name: 'advantages',
			label: mw.message( 'droplets-pro-con-advantages-label' ).text(),
			type: 'textarea',
			row: 5
		},
		{
			name: 'disadvantages',
			label: mw.message( 'droplets-pro-con-disadvantages-label' ).text(),
			type: 'textarea',
			row: 5
		}
	];
};

ext.contentdroplets.registry.register( 'pro-con-list', ext.proDistribution.objects.ProConListDroplet );
