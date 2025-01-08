// eslint-disable-next-line no-global-assign
ext = ext || {};
ext.proDistribution = ext.proDistribution || {};
ext.proDistribution.objects = ext.proDistribution.objects || {};
ext.proDistribution.objects.ModalDialogDroplet = function( cfg ) {
	ext.proDistribution.objects.ModalDialogDroplet.parent.call( this, cfg );
};
OO.inheritClass( ext.proDistribution.objects.ModalDialogDroplet, ext.contentdroplets.object.TransclusionDroplet );
ext.proDistribution.objects.ModalDialogDroplet.prototype.templateMatches = function( templateData ) {
	if ( !templateData ) {
		return false;
	}
	var target = templateData.target.wt;
	return target.trim( '\n' ) === 'ModalDialog' && 'modal-dialog' === this.getKey();
};
ext.proDistribution.objects.ModalDialogDroplet.prototype.toDataElement = function( domElements, converter  ) {
	return false;
};
ext.proDistribution.objects.ModalDialogDroplet.prototype.getFormItems = function() {
	return [
		{
			name: 'btnLabel',
			label: mw.message( 'droplets-modal-dialog-btn-label' ).plain(),
			type: 'text'
		},
		{
			name: 'bg-color',
			label: mw.message( 'droplets-modal-dialog-bg-color-label' ).plain(),
			type: 'dropdown',
			options: [
				{
					data: 'blue',
					label: mw.message( 'droplets-modal-dialog-bg-color-blue' ).plain()
				},
				{
					data: 'neutral',
					label: mw.message( 'droplets-modal-dialog-bg-color-neutral' ).plain()
				},
				{
					data: 'red',
					label: mw.message( 'droplets-modal-dialog-bg-color-red' ).plain()
				}
			]
		},
		{
			name: 'title',
			label: mw.message( 'droplets-modal-dialog-title-label' ).plain(),
			type: 'text'
		},
		{
			name: 'body',
			label: mw.message( 'droplets-modal-dialog-body-label' ).plain(),
			type: 'textarea',
			row: 5
		},
		{
			name: 'footer',
			label: mw.message( 'droplets-modal-dialog-footer-label' ).plain(),
			type: 'textarea',
			row: 2
		}
	];
};
ext.contentdroplets.registry.register( 'modal-dialog', ext.proDistribution.objects.ModalDialogDroplet );
