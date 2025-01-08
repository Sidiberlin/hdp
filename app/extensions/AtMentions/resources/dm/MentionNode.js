ext.atMentions.dm.UserMentionAnnotation = function () {
	// Parent constructor
	ext.atMentions.dm.UserMentionAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ext.atMentions.dm.UserMentionAnnotation, ve.dm.MWInternalLinkAnnotation );

/* Static Properties */

ext.atMentions.dm.UserMentionAnnotation.static.name = 'link/userMention';

ext.atMentions.dm.UserMentionAnnotation.static.matchTagNames = [ 'a' ];
ext.atMentions.dm.UserMentionAnnotation.static.matchRdfaTypes = [ 'mw:WikiLink', 'mw:MediaLink' ];
ext.atMentions.dm.UserMentionAnnotation.static.allowedRdfaTypes = [ 'mw:Error' ];
ext.atMentions.dm.UserMentionAnnotation.static.matchFunction = function ( domElement ) {
	var title = domElement.getAttribute( 'title' ),
		namespaceIds = mw.config.get( 'wgNamespaceIds' ),
		titleObject;
	if ( !title ) {
		return false;
	}
	titleObject = mw.Title.newFromText( title );
	if ( !titleObject ) {
		// media links may have their file names url decoded
		titleObject = mw.Title.newFromText( decodeURIComponent( title ) );
	}
	if ( !titleObject ) {
		// broken title. may have invalid chars etc.
		return false;
	}
	return titleObject.getNamespaceId() === namespaceIds.user;
};

ext.atMentions.dm.UserMentionAnnotation.static.toDataElement = function ( domElements, converter ) {
	var targetData = mw.libs.ve.getTargetDataFromHref(
			domElements[ 0 ].getAttribute( 'href' ),
			converter.getTargetHtmlDocument()
		),
		label = domElements[ 0 ].textContent;

	if ( label === domElements[ 0 ].title ) {
		label = null;
	}

	return {
		type: 'link/userMention',
		attributes: {
			label: label,
			username: this.getUsername( this.normalizeTitle( targetData.title ) ),
			hrefPrefix: targetData.hrefPrefix,
			title: targetData.title,
			normalizedTitle: this.normalizeTitle( targetData.title ),
			lookupTitle: this.getLookupTitle( targetData.title ),
			origTitle: targetData.rawTitle
		}
	};
};

ext.atMentions.dm.UserMentionAnnotation.static.dataElementFromUsername = function (
	username, display
) {
	return {
		type: 'link/userMention',
		attributes: {
			label: display || username,
			username: username,
			hrefPrefix: '',
			title: 'User:' + username,
			normalizedTitle: 'User:' + username,
			lookupTitle: 'User:' + username,
			origTitle: 'User:' + username
		}
	};
};

ext.atMentions.dm.UserMentionAnnotation.static.newFromUsername = function ( username ) {
	var element = this.dataElementFromUsername( username );

	return new ext.atMentions.dm.UserMentionAnnotation( element );
};

ext.atMentions.dm.UserMentionAnnotation.static.newFromUser = function ( user ) {
	var element = this.dataElementFromUsername( user.getUsername(), user.getDisplayName() );

	return new ext.atMentions.dm.UserMentionAnnotation( element );
};

ext.atMentions.dm.UserMentionAnnotation.static.getUsername = function ( title ) {
	var t = mw.Title.newFromText( title );
	if ( t ) {
		return t.getMainText();
	}
	return '';
};

/* Registration */

ve.dm.modelRegistry.register( ext.atMentions.dm.UserMentionAnnotation );
