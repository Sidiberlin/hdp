/*!
 * VisualEditor UserInterface CollabAuthorItemWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * UserInterface CollabAuthorItemWidget
 *
 * @class
 * @extends OO.ui.Widget
 * @mixes OO.ui.mixin.IconElement
 * @mixes OO.ui.mixin.LabelElement
 *
 * @constructor
 * @param {ve.dm.SurfaceSynchronizer} synchronizer Surface synchronizer
 * @param {jQuery} $overlay Overlay in which to attach popups (e.g. color picker)
 * @param {Object} [config] Configuration options
 */
collabpad.ui.CollabAuthorItemWidget = function VeUiCollabAuthorItemWidget(
	synchronizer, $overlay, config
) {
	const item = this;

	// Parent constructor
	collabpad.ui.CollabAuthorItemWidget.super.call( this, config );
	// Mixin constructors
	OO.ui.mixin.LabelElement.call( this, config );
	this.synchronizer = synchronizer;
	this.authorId = config.authorId;
	this.editable = !!config.editable;
	this.name = null;
	this.color = null;

	this.$color = $( '<div>' ).addClass( 'collabpad-ui-authorItemWidget-color' );
	if ( this.editable ) {
		this.colorPicker = new OOJSPlus.ui.widget.ColorPickerPopupCustomColor( { icon: '' } );
		this.colorPicker.on( 'colorSelected', ( color ) => {
			if ( color.indexOf( '#' ) === 0 ) {
				item.color = color.slice( 1 );
			} else {
				item.color = color;
			}
			item.$color.css( 'background-color', color );
			item.emit( 'changeColor', item.color );
		} );

		this.$color.append( this.colorPicker.$element );
	}

	this.$element.append( this.$color );
	this.$element.append( this.$label );
	this.update();
	this.$element.addClass( 'collabpad-ui-authorItemWidget' );
};

/* Inheritance */

OO.inheritClass( collabpad.ui.CollabAuthorItemWidget, OO.ui.Widget );

OO.mixinClass( collabpad.ui.CollabAuthorItemWidget, OO.ui.mixin.IconElement );

OO.mixinClass( collabpad.ui.CollabAuthorItemWidget, OO.ui.mixin.LabelElement );

/* Methods */

/**
 * Get the user's name
 *
 * @return {string} User's name
 */
collabpad.ui.CollabAuthorItemWidget.prototype.getName = function () {
	return this.name;
};

/**
 * Set author ID
 *
 * @param {number} authorId Author ID
 */
collabpad.ui.CollabAuthorItemWidget.prototype.setAuthorId = function ( authorId ) {
	this.authorId = authorId;
};

/**
 * Update name and color from synchronizer
 */
collabpad.ui.CollabAuthorItemWidget.prototype.update = function () {
	const authorData = this.synchronizer.getAuthorData( this.authorId );
	this.name = authorData.realName || authorData.name;
	this.color = authorData.color;
	if ( this.color.indexOf( '#' ) === 0 ) {
		this.$color.css( 'background-color', this.color );
	} else {
		this.$color.css( 'background-color', '#' + this.color );
	}
	this.setLabel( this.name );
};
