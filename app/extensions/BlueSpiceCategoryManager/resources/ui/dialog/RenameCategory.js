bs.util.registerNamespace( 'bs.categoryManager.ui.dialog' );

require( './../../api/CategoryActions.js' );

bs.categoryManager.ui.dialog.RenameCategory = function ( cfg ) {
	cfg = cfg || {};
	bs.categoryManager.ui.dialog.RenameCategory.super.call( this, cfg );
	this.oldCategoryName = cfg.category;
};

OO.inheritClass( bs.categoryManager.ui.dialog.RenameCategory, OO.ui.ProcessDialog );

bs.categoryManager.ui.dialog.RenameCategory.static.name = 'RenameCategoryDialog';
bs.categoryManager.ui.dialog.RenameCategory.static.title = mw.message( 'bs-categorymanager-rename-category' ).text();

bs.categoryManager.ui.dialog.RenameCategory.static.size = 'medium';

bs.categoryManager.ui.dialog.RenameCategory.static.actions = [
	{
		action: 'save',
		label: mw.message( 'bs-categorymanager-dialog-action-rename-label' ).text(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		label: mw.message( 'cancel' ).text(),
		flags: [ 'safe', 'close' ]
	}
];

bs.categoryManager.ui.dialog.RenameCategory.prototype.getSetupProcess = function ( data ) {
	return bs.categoryManager.ui.dialog.RenameCategory.parent.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.saveAction = this.actions.getSpecial().primary;
			this.saveAction.setDisabled( true );
		} );
};

bs.categoryManager.ui.dialog.RenameCategory.prototype.initialize = function () {
	bs.categoryManager.ui.dialog.RenameCategory.super.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.categoryInput = new OO.ui.TextInputWidget( {
		value: this.oldCategoryName
	} );
	this.categoryInput.connect( this, {
		change: function () {
			if ( this.categoryInput.getValue().length > 0 &&
				this.categoryInput.getValue() !== this.oldCategoryName ) {
				this.saveAction.setDisabled( false );
				return;
			}
			this.saveAction.setDisabled( true );
		}
	} );
	const categoryInputLayout = new OO.ui.FieldLayout( this.categoryInput, {
		label: mw.message( 'bs-categorymanager-rename-category-new-name' ).text(),
		align: 'top'
	} );
	this.panel.$element.append( categoryInputLayout.$element );
	this.$body.append( this.panel.$element );
	this.updateSize();
};

bs.categoryManager.ui.dialog.RenameCategory.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.pushPending();
			const newCategoryValue = this.categoryInput.getValue();
			const categoryAction = new bs.categoryManager.api.CategoryActions();
			categoryAction.renameCategory( this.oldCategoryName, newCategoryValue )
				.then( () => {
					this.close( { action: action } );
					this.emit( 'close' );
				} )
				.catch( ( error ) => {
					if ( error === 'duplicate' ) {
						this.saveAction.setDisabled( true );
						this.popPending();
						return;
					}
					console.error( error ); // eslint-disable-line no-console
					this.showErrors( new OO.ui.Error( error, { recoverable: false } ) );
					return;
				} );
		}, this );
	}
	return bs.categoryManager.ui.dialog.RenameCategory.super.prototype.getActionProcess.call( this, action );
};
