// eslint-disable-next-line no-global-assign
ext = ext || {};
ext.proDistribution = ext.proDistribution || {};
ext.proDistribution.objects = ext.proDistribution.objects || {};

ext.proDistribution.objects.DecisionDroplet = function( cfg ) {
	ext.proDistribution.objects.DecisionDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.proDistribution.objects.DecisionDroplet, ext.contentdroplets.object.TransclusionDroplet );

ext.proDistribution.objects.DecisionDroplet.prototype.templateMatches = function( templateData ) {
	if ( !templateData ) {
		return false;
	}
	var target = templateData.target.wt;
	return target.trim( '\n' ) === 'Decision' && 'decision' === this.getKey();
};

ext.proDistribution.objects.DecisionDroplet.prototype.toDataElement = function( domElements, converter  ) {
	return false;
};

ext.proDistribution.objects.DecisionDroplet.prototype.getFormItems = function() {
	return [
		{
			name: 'decision',
			label: mw.message( 'droplets-decision-label' ).plain(),
			type: 'textarea',
			row: 2
		},
	];
};

ext.contentdroplets.registry.register( 'decision', ext.proDistribution.objects.DecisionDroplet );
