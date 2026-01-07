bs.util.registerNamespace( 'ext.bookshelf.ui.dialog' );

ext.bookshelf.ui.dialog.MassAddDialog = function ( config ) {
	config = config || {};
	ext.bookshelf.ui.dialog.MassAddDialog.super.call( this, config );

	this.type = '';
	this.fields = [];
};
OO.inheritClass( ext.bookshelf.ui.dialog.MassAddDialog, OO.ui.ProcessDialog );

ext.bookshelf.ui.dialog.MassAddDialog.static.name = 'MassAddDialog';
ext.bookshelf.ui.dialog.MassAddDialog.static.title = mw.message( 'bs-bookshelfui-dlg-addmass-title' ).text();
ext.bookshelf.ui.dialog.MassAddDialog.static.size = 'large';

ext.bookshelf.ui.dialog.MassAddDialog.static.actions = [
	{
		action: 'save',
		label: mw.message( 'bs-bookshelf-massadd-dlg-save-action-label' ).text(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		title: mw.message( 'cancel' ).text(),
		flags: [ 'safe', 'close' ]
	}
];

ext.bookshelf.ui.dialog.MassAddDialog.prototype.initialize = function () {
	ext.bookshelf.ui.dialog.MassAddDialog.super.prototype.initialize.apply( this, arguments );

	this.content = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.pageCollectionsData = require( './collections.json' );
	this.pageCollections = this.pageCollectionsData.length > 0;

	this.sourceInput = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay,
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'category',
					label: mw.message( 'bs-bookshelfui-type-category' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'subpages',
					label: mw.message( 'bs-bookshelfui-type-subpages' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'pagecollection',
					label: mw.message( 'bs-bookshelfui-type-pagecollection' ).text(),
					disabled: !this.pageCollections
				} )
			]
		}
	} );

	this.sourceInput.getMenu().connect( this, {
		select: 'changeUI'
	} );

	const sourceField = new OO.ui.FieldLayout( this.sourceInput, {
		label: mw.message( 'bs-bookshelfui-dlg-type-label' ).text(),
		align: 'left'
	} );
	this.content.$element.append( sourceField.$element );

	this.subpageTitleInput = new mw.widgets.TitleInputWidget( {
		$overlay: this.$overlay
	} );

	this.subpageTitleInput.connect( this, {
		change: 'valueChanged'
	} );

	this.subpageTitleField = new OO.ui.FieldLayout( this.subpageTitleInput, {
		label: mw.message( 'bs-bookshelfui-dlg-choosewikipage-cbxArticleLabel' ).text(),
		align: 'left',
		data: 'subpages'
	} );
	this.subpageTitleField.toggle( false );
	this.fields.push( this.subpageTitleField );
	this.content.$element.append( this.subpageTitleField.$element );

	this.categoryInput = new OOJSPlus.ui.widget.CategoryInputWidget( {
		$overlay: this.$overlay,
		allowArbitrary: true
	} );

	this.categoryInput.connect( this, {
		change: 'valueChanged'
	} );

	this.categoryTitleField = new OO.ui.FieldLayout( this.categoryInput, {
		label: mw.message( 'bs-bookshelfui-dlg-choosecategory-label' ).text(),
		align: 'left',
		data: 'category'
	} );
	this.categoryTitleField.toggle( false );
	this.fields.push( this.categoryTitleField );
	this.content.$element.append( this.categoryTitleField.$element );

	const options = this.getOptions();
	this.pageCollecionInput = new OO.ui.DropdownInputWidget( {
		$overlay: this.$overlay
	} );

	this.pageCollecionInput.connect( this, {
		change: 'valueChanged'
	} );
	this.pageCollecionInput.setOptions( options );

	this.pageCollecionField = new OO.ui.FieldLayout( this.pageCollecionInput, {
		label: mw.message( 'bs-bookshelfui-dlg-choosepc-label' ).text(),
		align: 'left',
		data: 'pagecollection'
	} );
	this.pageCollecionField.toggle( false );
	this.fields.push( this.pageCollecionField );
	this.content.$element.append( this.pageCollecionField.$element );

	mw.hook( 'ext.bookshelf.addmass.create' ).fire( this.content );

	this.$body.append( this.content.$element );
	this.updateSize();
};

ext.bookshelf.ui.dialog.MassAddDialog.prototype.getSetupProcess = function ( data ) {
	return ext.bookshelf.ui.dialog.MassAddDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.saveAction = this.actions.getSpecial().primary;
			this.saveAction.setDisabled( true );
		} );
};

ext.bookshelf.ui.dialog.MassAddDialog.prototype.changeUI = function ( item ) {
	this.type = item.data;

	this.fields.forEach( ( field ) => {
		field.toggle( this.type === field.data );
	} );

	this.updateSize();

	this.valueChanged( '' );
};

ext.bookshelf.ui.dialog.MassAddDialog.prototype.getOptions = function () {
	const options = [];
	this.pageCollectionsData.forEach( ( page ) => {
		options.push( {
			data: page.pc_title,
			label: page.pc_title
		} );
	} );
	return options;
};

ext.bookshelf.ui.dialog.MassAddDialog.prototype.valueChanged = function ( value ) {
	if ( value === '' && this.type !== 'pagecollection' ) {
		this.saveAction.setDisabled( true );
		return;
	}
	if ( this.type !== '' ) {
		this.saveAction.setDisabled( false );
	}
};

ext.bookshelf.ui.dialog.MassAddDialog.prototype.getActionProcess = function ( action ) {
	const dialog = this;
	if ( action ) {
		return new OO.ui.Process( () => {
			dialog.pushPending();
			const field = dialog.fields.filter( ( field ) => field.data === dialog.type )[ 0 ]; // eslint-disable-line no-shadow
			let value = field.getField().getValue();
			if ( dialog.type === 'category' ) {
				value = field.getField().getMWTitle().getMain();
			}
			const api = new mw.Api();
			api.get( {
				action: 'bs-bookshelf-mass-add-page-store',
				root: value,
				type: dialog.type
			} )
				.done( ( response ) => {
					const pages = response.results;
					dialog.popPending();
					dialog.close( { action: 'done' } );
					dialog.emit( 'mass_add_pages', pages );
				} );
		} );
	}
	return ext.bookshelf.ui.dialog.MassAddDialog.super.prototype.getActionProcess.call( this, action );
};
