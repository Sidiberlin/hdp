// eslint-disable-next-line no-global-assign
ext = ext || {};
ext.proDistribution = ext.proDistribution || {};
ext.proDistribution.objects = ext.proDistribution.objects || {};

ext.proDistribution.objects.ProConListDroplet = function( cfg ) {
	ext.proDistribution.objects.ProConListDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.proDistribution.objects.ProConListDroplet, ext.contentdroplets.object.TransclusionDroplet );

ext.proDistribution.objects.ProConListDroplet.prototype.templateMatches = function( templateData ) {
	if ( !templateData ) {
		return false;
	}
	var target = templateData.target.wt;
	return target.trim( '\n' ) === 'ProConList' && 'pro-con-list' === this.getKey();
};

ext.proDistribution.objects.ProConListDroplet.prototype.toDataElement = function( domElements, converter  ) {
	return false;
};

ext.proDistribution.objects.ProConListDroplet.prototype.getFormItems = function() {
	return [
		{
			name: 'title-advantages',
			label: mw.message( 'droplets-pro-con-advantages-title-label' ).plain(),
			type: 'text'
		},
		{
			name: 'title-disadvantages',
			label: mw.message( 'droplets-pro-con-disadvantages-title-label' ).plain(),
			type: 'text'
		},
		{
			name: 'advantages',
			label: mw.message( 'droplets-pro-con-advantages-label' ).plain(),
			type: 'textarea',
			row: 5
		},
		{
			name: 'disadvantages',
			label: mw.message( 'droplets-pro-con-disadvantages-label' ).plain(),
			type: 'textarea',
			row: 5
		}
	];
};

ext.contentdroplets.registry.register( 'pro-con-list', ext.proDistribution.objects.ProConListDroplet );
