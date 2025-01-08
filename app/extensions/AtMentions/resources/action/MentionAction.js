ext.atMentions.action.MentionAction = function () {
	ext.atMentions.action.MentionAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.atMentions.action.MentionAction, ve.ui.MWLinkAction );

/* Static Properties */

ext.atMentions.action.MentionAction.static.name = 'mention';

ext.atMentions.action.MentionAction.static.methods = [ 'open' ];

ext.atMentions.action.MentionAction.prototype.open = function () {
	this.surface.execute( 'window', 'open', 'userMention' );
	return true;
};

ve.ui.actionFactory.register( ext.atMentions.action.MentionAction );

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'userMention', 'mention', 'open', { supportedSelections: [ 'linear' ] }
	)
);

/* Sequence */

// Any {space}@ combination
ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'userMention', 'userMention', [ ' ', '@' ], 1 )
);

// Special case for when @ is at the beginning of a line (no space before)
ve.ui.sequenceRegistry.register(
	new ve.ui.Sequence( 'userMentionNewline', 'userMention', [ { type: 'paragraph' }, '@' ], 1 )
);
