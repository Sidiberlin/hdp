ext.atMentions.ui.MentionContextItem = function () {
	// Parent constructor
	ext.atMentions.ui.MentionContextItem.super.apply( this, arguments );

	this.$element.addClass( 'at-mentions-context-item' );
};

/* Inheritance */
OO.inheritClass( ext.atMentions.ui.MentionContextItem, ve.ui.MWInternalLinkContextItem );

/* Static Properties */

ext.atMentions.ui.MentionContextItem.static.name = 'link/userMention';

ext.atMentions.ui.MentionContextItem.static.modelClasses = [
	ext.atMentions.dm.UserMentionAnnotation
];

ext.atMentions.ui.MentionContextItem.static.icon = 'userAvatar';

ext.atMentions.ui.MentionContextItem.static.label = mw.message( 'at-mentions-ci-title' ).text();

ext.atMentions.ui.MentionContextItem.static.embeddable = false;

ext.atMentions.ui.MentionContextItem.static.commandName = 'userMention';

ext.atMentions.ui.MentionContextItem.static.clearable = true;

ext.atMentions.ui.MentionContextItem.static.clearIcon = 'unLink';

ext.atMentions.ui.MentionContextItem.prototype.renderBody = function () {
	const widget = new OOJSPlus.ui.widget.UserWidget( {
		// eslint-disable-next-line camelcase
		user_name: this.model.getAttribute( 'username' )
	} );
	this.$body.empty().append( widget.$element );
};

/* Registration */
ve.ui.contextItemFactory.register( ext.atMentions.ui.MentionContextItem );
