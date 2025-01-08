officeimport.ui.ConfigurationPage = function ( name, cfg ) {
	officeimport.ui.ConfigurationPage.parent.call( this, name, cfg );
	this.$overlay = cfg.$overlay;
	this.layout = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	const formElements = this.getElements();
	this.layout.$element.append(
		formElements.map( function ( item ) {
			return item.$element;
		} )
	);

	this.$element.append( this.layout.$element );
};

OO.inheritClass( officeimport.ui.ConfigurationPage, OO.ui.PageLayout );

officeimport.ui.ConfigurationPage.prototype.getElements = function () {
	this.titleInput = new mw.widgets.TitleInputWidget( {
		value: this.title,
		required: true
	} );
	this.titleInput.connect( this, {
		change: function ( value ) {
			this.emit( 'configSet', value );
		}
	} );

	this.fileStructureCheckbox = new OO.ui.CheckboxInputWidget();

	this.fileStructure = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay,
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'h1',
					label: mw.message( 'importofficefiles-ui-dialog-settings-split-level-1' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'h2',
					label: mw.message( 'importofficefiles-ui-dialog-settings-split-level-2' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'h3',
					label: mw.message( 'importofficefiles-ui-dialog-settings-split-level-3' ).text()
				} )
			]
		}
	} );
	this.fileStructure.getMenu().selectItemByData( 'h1' );

	this.fieldStructureLayout = new OO.ui.FieldLayout( this.fileStructure, {
		label: mw.message( 'importofficefiles-ui-dialog-settings-choose-split-level-label' ).text(),
		align: 'top'
	} );
	this.fieldStructureLayout.toggle( false );

	this.fileStructureCheckbox.on( 'change', this.toggleSplitProperty, [], this );

	this.titleConflicts = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay,
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'rename',
					label: mw.message( 'importofficefiles-ui-dialog-settings-conflicts-rename' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'override',
					label: mw.message( 'importofficefiles-ui-dialog-settings-conflicts-override' ).text()
				} )
			]
		}
	} );
	this.titleConflicts.getMenu().selectItemByData( 'rename' );

	return [
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.LabelWidget( {
					label: mw.message( 'importofficefiles-ui-dialog-configuration-settings-title' ).text(),
					classes: [ 'label-heading-page' ]
				} )
			]
		} ),
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.LabelWidget( {
					label: mw.message( 'importofficefiles-ui-dialog-settings-page-title' ).text(),
					classes: [ 'label-bold' ]
				} ),
				new OO.ui.FieldLayout( this.titleInput, {
					label: mw.message( 'importofficefiles-ui-dialog-settings-pagetitle-label' ).text(),
					align: 'top'
				} )
			]
		} ),
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.LabelWidget( {
					label: mw.message( 'importofficefiles-ui-dialog-settings-structure-label' ).text(),
					classes: [ 'label-bold' ]
				} ),
				new OO.ui.FieldLayout( this.fileStructureCheckbox, {
					label: mw.message( 'importofficefiles-ui-dialog-settings-structure-label-checkbox' ).text(),
					align: 'inline'
				} ),
				this.fieldStructureLayout
			]
		} ),
		new OO.ui.FieldsetLayout( {
			items: [
				new OO.ui.LabelWidget( {
					label: mw.message( 'importofficefiles-ui-dialog-settings-conflicts-title' ).text(),
					classes: [ 'label-bold' ]
				} ),
				new OO.ui.FieldLayout( this.titleConflicts, {
					label: mw.message( 'importofficefiles-ui-dialog-settings-conflicts-behavior-label' ).text(),
					align: 'top'
				} )
			]
		} )
	];
};

officeimport.ui.ConfigurationPage.prototype.toggleSplitProperty = function () {
	this.fieldStructureLayout.toggle( this.fileStructureCheckbox.isSelected() );
};

officeimport.ui.ConfigurationPage.prototype.getData = function () {
	this.title = this.titleInput.getValue();
	let structureConfig = false;
	if ( this.fileStructureCheckbox.isSelected() ) {
		structureConfig = this.fileStructure.getMenu().findSelectedItem().getData();
	}
	const conflictsConfig = this.titleConflicts.getMenu().findSelectedItem().getData();

	return {
		title: this.title,
		structure: structureConfig,
		conflict: conflictsConfig
	};

};

officeimport.ui.ConfigurationPage.prototype.analyzeFile = function ( uploadId, fileName, data ) {
	const dfd = $.Deferred();

	mw.loader.using( [ 'ext.importofficefiles.api' ], function () {
		const api = new officeimport.api.Api();
		api.startAnalyze( uploadId, fileName, data ).done( function ( response ) {
			if ( response.processId ) {
				const timer = setInterval( function () {
					this.checkAnalyzeStatus( response.processId, timer, dfd );
				}.bind( this ), 1000 );
			} else {
				dfd.reject( 'Analyze process did not start correctly' );
			}
		}.bind( this ) ).fail( function ( error ) {
			this.emit( 'analyzeFailed', error );
			dfd.reject( error );
		}.bind( this ) );
	}.bind( this ) );

	return dfd.promise();
};

officeimport.ui.ConfigurationPage.prototype.checkAnalyzeStatus =
	function ( processId, timer, dfd ) {
		mw.loader.using( [ 'ext.importofficefiles.api' ], function () {
			const api = new officeimport.api.Api();
			api.getAnalyzeStatus( processId ).done( function ( response ) {
				if ( response.state === 'terminated' ) {
					if ( response.exitCode === 0 ) {
						this.emit( 'analyzeDone', response.pid, timer );
						dfd.resolve( response.pid, timer );
					} else {
						clearInterval( timer );
						this.emit( 'analyzeFailed', response.exitStatus );
						dfd.reject( response.exitStatus );
					}
				}
			}.bind( this ) ).fail( function ( error ) {
				clearInterval( timer );
				dfd.reject( error );
			} );
		}.bind( this ) );
	};

officeimport.ui.ConfigurationPage.prototype.setDefaultValue = function ( filename ) {
	filename = filename.replace( /\..*/, '' );
	this.title = this.title || filename;
	this.titleInput.setValue( this.title );
};
