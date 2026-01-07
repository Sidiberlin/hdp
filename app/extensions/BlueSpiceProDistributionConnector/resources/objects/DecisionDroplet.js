bs.util.registerNamespace( 'ext.proDistribution.objects' );

ext.proDistribution.objects.DecisionDroplet = function ( cfg ) {
	ext.proDistribution.objects.DecisionDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.proDistribution.objects.DecisionDroplet, ext.contentdroplets.object.TransclusionDroplet );

ext.proDistribution.objects.DecisionDroplet.prototype.templateMatches = function ( templateData ) {
	if ( !templateData ) {
		return false;
	}
	const target = templateData.target.wt;
	return target.trim( '\n' ) === 'Decision' && this.getKey() === 'decision';
};

ext.proDistribution.objects.DecisionDroplet.prototype.toDataElement = function ( domElements, converter ) { // eslint-disable-line no-unused-vars
	return false;
};

ext.proDistribution.objects.DecisionDroplet.prototype.getFormItems = function () {
	return [
		{
			name: 'decision',
			label: mw.message( 'droplets-decision-label' ).text(),
			type: 'textarea',
			row: 2
		}
	];
};

ext.contentdroplets.registry.register( 'decision', ext.proDistribution.objects.DecisionDroplet );
