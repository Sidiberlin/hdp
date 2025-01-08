// eslint-disable-next-line no-global-assign
ext = ext || {};
ext.chatbot = ext.chatbot || {};
ext.chatbot.objects = ext.chatbot.objects || {};

ext.chatbot.objects.ChatbotMetaDroplet = function( cfg ) {
	ext.chatbot.objects.ChatbotMetaDroplet.parent.call( this, cfg );
};

OO.inheritClass( ext.chatbot.objects.ChatbotMetaDroplet, ext.contentdroplets.object.TransclusionDroplet );

ext.chatbot.objects.ChatbotMetaDroplet.prototype.templateMatches = function( templateData ) {
	if ( !templateData ) {
		return false;
	}
	var target = templateData.target.wt;
	return target.trim( '\n' ) === 'ChatbotMeta' && 'chatbotmeta' === this.getKey();
};

ext.chatbot.objects.ChatbotMetaDroplet.prototype.toDataElement = function( domElements, converter  ) {
	return false;
};

ext.chatbot.objects.ChatbotMetaDroplet.prototype.getFormItems = function() {
	return [
		{
			name: 'meta',
			label: mw.message( 'chatbot-droplet-meta-label' ).plain(),
			type: 'textarea',
			row: 3
		},
	];
};

ext.contentdroplets.registry.register( 'chatbotmeta', ext.chatbot.objects.ChatbotMetaDroplet );
