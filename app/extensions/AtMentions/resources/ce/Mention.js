ext.atMentions.ce.UserMentionAnnotation = function () {
	ext.atMentions.ce.UserMentionAnnotation.super.apply( this, arguments );
	this.$element.addClass( 'ext-atMentions-ce-UserMentionAnnotation' );
	this.$element.css( {
		'background-color': '#e5e4ff',
		color: '#3733F8FF',
		border: '1px solid #acaeff',
		padding: '0 2px',
		'border-radius': '2px'
	} );
	// this.$element.attr( 'rel', 'mw:WikiLink' );
};

/* Inheritance */

OO.inheritClass( ext.atMentions.ce.UserMentionAnnotation, ve.ce.MWInternalLinkAnnotation );

/* Static Properties */

ext.atMentions.ce.UserMentionAnnotation.static.name = 'link/userMention';

/* Registration */
ve.ce.annotationFactory.register( ext.atMentions.ce.UserMentionAnnotation );
