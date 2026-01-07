ext.atMentions.ui.UserMentionInspector = function ( config ) {
	// Parent constructor
	ext.atMentions.ui.UserMentionInspector.super.call(
		this, ve.extendObject( { padded: true }, config )
	);

	this.$element.addClass( 'ext-atMentions-ui-userMentionInspector' );
};

/* Inheritance */

OO.inheritClass( ext.atMentions.ui.UserMentionInspector, ve.ui.MWLinkAnnotationInspector );

/* Static properties */

ext.atMentions.ui.UserMentionInspector.static.name = 'userMention';
ext.atMentions.ui.UserMentionInspector.static.title = mw.message( 'at-mentions-inspector-title' ).text();

ext.atMentions.ui.UserMentionInspector.static.modelClasses = [
	ext.atMentions.dm.UserMentionAnnotation
];

/* Methods */

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.initialize = function () {
	ext.atMentions.ui.UserMentionInspector.parent.prototype.initialize.call( this );
	this.userPicker = new OOJSPlus.ui.widget.UserPickerWidget( {
		$overlay: true,
		allowSuggestionsWhenEmpty: true
	} );

	this.userPicker.connect( this, { choose: 'onUserChoose' } );

	this.form.$element.empty();
	this.form.$element.append( new OO.ui.FieldLayout( this.userPicker, {
		align: 'top',
		label: 'User'
	} ).$element );
};

ext.atMentions.ui.UserMentionInspector.prototype.getAnnotation = function () {
	return this.makeAnnotation();
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getAnnotationFromFragment = function ( fragment ) {
	const text = fragment.getText();

	return text ? ext.atMentions.dm.UserMentionAnnotation.static.newFromUsername( text ) : null;
};

/* Methods */

/**
 * Handle annotation input change events
 *
 * @param {any} user
 * @return {void}
 */
// eslint-disable-next-line no-unused-vars
ext.atMentions.ui.UserMentionInspector.prototype.onUserChoose = function ( user ) {
	const promise = this.updateActions();
	promise.then( () => {
		this.onFormSubmit();
	} );
};

ext.atMentions.ui.UserMentionInspector.prototype.makeAnnotation = function () {
	const user = this.userPicker.getSelectedUser();
	return user ? ext.atMentions.dm.UserMentionAnnotation.static.newFromUser( user ) : null;
};

/**
 * Update the actions based on the annotation state
 *
 * @return {Promise}
 */
ext.atMentions.ui.UserMentionInspector.prototype.updateActions = function () {
	const inspector = this;
	const annotation = this.makeAnnotation();
	const promise = this.userPicker.getValidity();
	let isValid = false;

	promise.then( () => {
		isValid = true;
	} )
		.always( () => {
			isValid = isValid && !!annotation;
			inspector.actions.forEach( { actions: [ 'done', 'insert' ] }, ( action ) => {
				action.setDisabled( !isValid );
			} );
		} );
	return promise;
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.shouldRemoveAnnotation = function () {
	return !this.makeAnnotation();
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getInsertionText = function () {
	const user = this.userPicker.getSelectedUser();
	return user ? user.getDisplayName() : '';
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.shouldInsertText = function () {
	// Always update the text
	return true;
};

ext.atMentions.ui.UserMentionInspector.prototype.setFromAnnotation = function ( annotation ) {
	const current = this.makeAnnotation();
	if ( ve.compare(
		annotation ? annotation.getComparableObject() : {},
		current ? current.getComparableObject() : {}
	) ) {
		// No change
		return this;
	}
	if ( annotation && annotation.element.attributes.username ) {
		this.userPicker.setValue( annotation.element.attributes.username );
	}
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getSetupProcess = function ( data ) {
	return ext.atMentions.ui.UserMentionInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			const title = ve.msg(
				this.isReadOnly() ?
					'at-mentions-inspector-title' : (
						this.isNew ?
							'at-mentions-inspector-title-add' :
							'at-mentions-inspector-title-edit'
					)
			);
			this.title.setLabel( title ).setTitle( title );
			this.setFromAnnotation( this.initialAnnotation );

			this.updateActions();
		}, this );
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getReadyProcess = function ( data ) {
	return ext.atMentions.ui.UserMentionInspector.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			if ( !OO.ui.isMobile() ) {
				this.userPicker.focus();
			}
			this.userPicker.setValidityFlag( true );
			this.userPicker.focus();
		}, this );
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getHoldProcess = function ( data ) {
	// Intentionally different parent class, we need parent of parent
	return ve.ui.LinkAnnotationInspector.super.prototype.getHoldProcess.call( this, data )
		.next( function () {
			this.userPicker.$input.trigger( 'blur' );
		}, this );
};

/**
 * @inheritdoc
 */
ext.atMentions.ui.UserMentionInspector.prototype.getTeardownProcess = function ( data ) {
	let fragment;
	// Intentionally different parent class, we need parent of parent
	return ve.ui.LinkAnnotationInspector.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			fragment = this.getFragment();
		}, this )
		.next( function () {
			this.userPicker.setValue( '' );
			fragment.adjustLinearSelection( 3, 0 );
		}, this );
};

// #getInsertionText call annotationInput#getHref, which returns the link title,
// so no custmisation is needed.

/* Registration */

ve.ui.windowFactory.register( ext.atMentions.ui.UserMentionInspector );
