ext.datetimetools.ui.DateTimeToolsInspector = function ( config ) {
	// Parent constructor
	ext.datetimetools.ui.DateTimeToolsInspector.super.call(
		this, ve.extendObject( { padded: true }, config )
	);
};

/* Inheritance */

OO.inheritClass( ext.datetimetools.ui.DateTimeToolsInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ext.datetimetools.ui.DateTimeToolsInspector.static.name = 'datetimeInspector';

ext.datetimetools.ui.DateTimeToolsInspector.static.title = mw.message( 'datetimetools-ve-inspector-title' ).plain();

ext.datetimetools.ui.DateTimeToolsInspector.static.modelClasses =
	[ ext.datetimetools.dm.DateTimeToolsNode ];

ext.datetimetools.ui.DateTimeToolsInspector.static.dir = 'ltr';

// This tag does not have any content
ext.datetimetools.ui.DateTimeToolsInspector.static.allowedEmpty = true;
ext.datetimetools.ui.DateTimeToolsInspector.static.selfCloseEmptyBody = false;

/**
 * @inheritdoc
 */
ext.datetimetools.ui.DateTimeToolsInspector.prototype.initialize = function () {
	ext.datetimetools.ui.DateTimeToolsInspector.super.prototype.initialize.call( this );

	// set to invisible: have to keep to be able to set content of node
	// but content should be selected from file search
	this.input.toggle( false );

	this.indexLayout = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.createFields();

	this.setLayouts();

	// Initialization
	this.$content.addClass( 'datetime-inspector-body' );

	this.indexLayout.$element.append(
		this.dateLayout.$element
	);
	this.form.$element.append(
		this.indexLayout.$element
	);
};

ext.datetimetools.ui.DateTimeToolsInspector.prototype.createFields = function () {
	this.dateInput = new mw.widgets.DateInputWidget( {
		$overlay: true
	} );
	this.dateInput.on( 'change', function () {
		var value = this.dateInput.getValue();
		this.input.setValue( value );
	}.bind( this ) );
};

ext.datetimetools.ui.DateTimeToolsInspector.prototype.setLayouts = function () {
	this.dateLayout = new OO.ui.FieldLayout( this.dateInput, {
		align: 'right',
		label: mw.message( 'datetimetools-ve-inspector-date-picker-label' ).plain()
	} );
};

/**
 * @inheritdoc
 */
ext.datetimetools.ui.DateTimeToolsInspector.prototype.getSetupProcess = function ( data ) {
	return ext.datetimetools.ui.DateTimeToolsInspector.super.prototype.getSetupProcess.call(
		this, data
	).next( function () {
		this.dateInput.setValue( this.input.getValue() );

		this.actions.setAbilities( { done: true } );
	}, this );
};

ext.datetimetools.ui.DateTimeToolsInspector.prototype.getReadyProcess = function ( data ) {
	return ext.datetimetools.ui.DateTimeToolsInspector.super.prototype.getReadyProcess.call(
		this, data
	).next( function () {
		this.dateInput.focus();
	}, this );
};

ext.datetimetools.ui.DateTimeToolsInspector.prototype.wireEvents = function () {
	this.dateInput.on( 'change', this.onChangeHandler );
};

/**
 * @inheritdoc
 */
ext.datetimetools.ui.DateTimeToolsInspector.prototype.formatGeneratedContentsError =
	function ( $element ) {
		return $element.text().trim();
	};

/* Registration */

ve.ui.windowFactory.register( ext.datetimetools.ui.DateTimeToolsInspector );
