ext.atMentions.tools.MentionTool = function MentionTool() {
	ext.atMentions.tools.MentionTool.super.apply( this, arguments );
};

OO.inheritClass( ext.atMentions.tools.MentionTool, ve.ui.Tool );

ext.atMentions.tools.MentionTool.static.name = 'mention';
ext.atMentions.tools.MentionTool.static.group = 'dialog';
ext.atMentions.tools.MentionTool.static.icon = 'at-mention';
ext.atMentions.tools.MentionTool.static.title = mw.message( 'at-mentions-toolbar-tool-title' ).plain();
ext.atMentions.tools.MentionTool.static.commandName = 'userMention';
ext.atMentions.tools.MentionTool.static.autoAddToCatchall = false;
ext.atMentions.tools.MentionTool.static.autoAddToGroup = false;

/* Registration */

ve.ui.toolFactory.register( ext.atMentions.tools.MentionTool );

ve.init.mw.Target.static.toolbarGroups.push( {
	include: [ 'mention' ],
	classes: [ 've-ui-toolbar-group-at-mention' ],
} );
