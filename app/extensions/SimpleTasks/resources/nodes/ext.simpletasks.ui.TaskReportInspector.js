ext.simpletasks.ui.TaskReportInspector = function ( config ) {
	// Parent constructor
	ext.simpletasks.ui.TaskReportInspector.super.call(
		this, ve.extendObject( { padded: true }, config )
	);
};

/* Inheritance */

OO.inheritClass( ext.simpletasks.ui.TaskReportInspector, ve.ui.MWLiveExtensionInspector );

/* Static properties */

ext.simpletasks.ui.TaskReportInspector.static.name = 'taskReportInspector';

ext.simpletasks.ui.TaskReportInspector.static.title = mw.message( 'simple-tasks-taskreport-inspector-title' ).text();

ext.simpletasks.ui.TaskReportInspector.static.modelClasses = [ ext.simpletasks.dm.TaskReportNode ];

ext.simpletasks.ui.TaskReportInspector.static.dir = 'ltr';

// This tag does not have any content
ext.simpletasks.ui.TaskReportInspector.static.allowedEmpty = true;
ext.simpletasks.ui.TaskReportInspector.static.selfCloseEmptyBody = false;

/**
 * @inheritdoc
 */
ext.simpletasks.ui.TaskReportInspector.prototype.initialize = function () {
	ext.simpletasks.ui.TaskReportInspector.super.prototype.initialize.call( this );

	// remove input field with links in it
	this.input.$element.remove();

	this.indexLayout = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	this.createFields();

	this.setLayouts();

	// Initialization
	this.$content.addClass( 'simple-tasks-inspector-content' );

	this.indexLayout.$element.append(
		this.userLayout.$element,
		this.namespaceLayout.$element,
		this.dateLayout.$element,
		this.stateLayout.$element
	);
	this.form.$element.append(
		this.indexLayout.$element
	);
};

ext.simpletasks.ui.TaskReportInspector.prototype.createFields = function () {
	this.userMultiSelect = new mw.widgets.UsersMultiselectWidget( {
		tagName: 'div',
		$overlay: true
	} );
	this.namespacesMultiSelect = new mw.widgets.NamespacesMultiselectWidget( {
		$overlay: true
	} );

	this.dateInput = new mw.widgets.DateInputWidget( {
		$overlay: true
	} );
	this.stateInput = new OO.ui.MenuTagMultiselectWidget( {
		$overlay: true,
		options: [
			{
				data: 'checked',
				label: mw.message( 'simple-tasks-taskreport-inspector-state-done' ).text()
			},
			{
				data: 'unchecked',
				label: mw.message( 'simple-tasks-taskreport-inspector-state-open' ).text()
			}
		]
	} );
};

ext.simpletasks.ui.TaskReportInspector.prototype.setLayouts = function () {
	this.userLayout = new OO.ui.FieldLayout( this.userMultiSelect, {
		align: 'right',
		label: mw.message( 'simple-tasks-taskreport-inspector-label-user' ).text()
	} );
	this.namespaceLayout = new OO.ui.FieldLayout( this.namespacesMultiSelect, {
		align: 'right',
		label: mw.message( 'simple-tasks-taskreport-inspector-label-namespaces' ).text()
	} );

	this.dateLayout = new OO.ui.FieldLayout( this.dateInput, {
		align: 'right',
		label: mw.message( 'simple-tasks-taskreport-inspector-label-due-date' ).text()
	} );

	this.stateLayout = new OO.ui.FieldLayout( this.stateInput, {
		align: 'right',
		label: mw.message( 'simple-tasks-taskreport-inspector-label-state' ).text()
	} );
};

/**
 * @inheritdoc
 */
ext.simpletasks.ui.TaskReportInspector.prototype.getSetupProcess = function ( data ) {
	return ext.simpletasks.ui.TaskReportInspector.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode.getAttribute( 'mw' ).attrs;
			if ( attributes.user ) {
				this.setSelectedUserTags( attributes.user );
			} else {
				this.userMultiSelect.clearItems();
			}

			if ( attributes.namespaces ) {
				var namespaceData = attributes.namespaces.split( '|' );
				this.namespacesMultiSelect.clearItems();
				for ( var namespace in namespaceData ) {
					var namespaceItem = this.namespacesMultiSelect.menu.findItemFromData(
						namespaceData[ namespace ]
					);
					this.namespacesMultiSelect.addTag(
						namespaceItem.getData(), namespaceItem.getLabel()
					);
				}
			} else {
				this.namespacesMultiSelect.clearItems();
			}

			if ( attributes.date ) {
				var date = attributes.date;
				this.dateInput.setValue( date );
			} else {
				this.dateInput.setValue( '' );
			}

			if ( attributes.status ) {
				var stateData = attributes.status.split( '|' );
				this.stateInput.clearItems();
				for ( var state in stateData ) {
					var stateItem = this.stateInput.menu.findItemFromData( stateData[ state ] );
					this.stateInput.addTag( stateItem.getData(), stateItem.getLabel() );
				}
			} else {
				this.stateInput.clearItems();
			}
			this.actions.setAbilities( { done: true } );

			// Add event handlers
			this.userMultiSelect.on( 'change', this.onChangeHandler );
			this.namespacesMultiSelect.on( 'change', this.onChangeHandler );
			this.dateInput.on( 'change', this.onChangeHandler );
			this.stateInput.on( 'change', this.onChangeHandler );
		}, this );
};

ext.simpletasks.ui.TaskReportInspector.prototype.updateMwData = function ( mwData ) {
	ext.simpletasks.ui.TaskReportInspector.super.prototype.updateMwData.call( this, mwData );

	if ( this.userMultiSelect.getValue() !== '' ) {
		mwData.attrs.user = this.userMultiSelect.getValue().join( '|' );
	} else {
		delete ( mwData.attrs.user );
	}

	if ( this.namespacesMultiSelect.getValue() !== '' ) {
		mwData.attrs.namespaces = this.namespacesMultiSelect.getValue().join( '|' );
	} else {
		delete ( mwData.attrs.namespaces );
	}
	if ( this.stateInput.getValue() !== '' ) {
		mwData.attrs.status = this.stateInput.getValue().join( '|' );
	} else {
		delete ( mwData.attrs.status );
	}
	if ( this.dateInput.getValue() !== '' ) {
		mwData.attrs.date = this.dateInput.getValue();
	} else {
		delete ( mwData.attrs.date );
	}
};

/**
 * @inheritdoc
 */
ext.simpletasks.ui.TaskReportInspector.prototype.formatGeneratedContentsError =
	function ( $element ) {
		return $element.text().trim();
	};

/**
 * Append the error to the current tab panel.
 */
ext.simpletasks.ui.TaskReportInspector.prototype.onTabPanelSet = function () {
	this.indexLayout.getCurrentTabPanel().$element.append( this.generatedContentsError.$element );
};

ext.simpletasks.ui.TaskReportInspector.prototype.setSelectedUserTags = function ( userNames ) {
	var user = userNames.split( '|' );
	var userTags = [];
	this.userMultiSelect.clearItems();

	for ( var userName in user ) {
		userTags.push( this.userMultiSelect.createTagItemWidget(
			user[ userName ], user[ userName ]
		) );
	}
	this.userMultiSelect.addItems( userTags );
};

/* Registration */

ve.ui.windowFactory.register( ext.simpletasks.ui.TaskReportInspector );
