bs.util.registerNamespace( 'bs.categoryManager.ui.dialog' );

require( '../../api/CategoryActions.js' );

bs.categoryManager.ui.dialog.CreateCategory = function ( cfg ) {
	cfg = cfg || {};
	bs.categoryManager.ui.dialog.CreateCategory.super.call( this, cfg );
	this.selectedCategory = cfg.selectedCategory;
	this.namespace = 14;
};

OO.inheritClass( bs.categoryManager.ui.dialog.CreateCategory, OO.ui.ProcessDialog );

bs.categoryManager.ui.dialog.CreateCategory.static.name = 'CreateCategoryDialog';
bs.categoryManager.ui.dialog.CreateCategory.static.title = mw.message( 'bs-categorymanager-dlg-add-title' ).text();

bs.categoryManager.ui.dialog.CreateCategory.static.size = 'medium';

bs.categoryManager.ui.dialog.CreateCategory.static.actions = [
	{
		action: 'save',
		label: mw.message( 'bs-categorymanager-dialog-action-add-label' ).text(),
		flags: [ 'primary', 'progressive' ]
	},
	{
		label: mw.message( 'cancel' ).text(),
		flags: [ 'safe', 'close' ]
	}
];

bs.categoryManager.ui.dialog.CreateCategory.prototype.getSetupProcess = function ( data ) {
	return bs.categoryManager.ui.dialog.CreateCategory.parent.prototype.getSetupProcess.call( this, data )
		.next( () => {
			this.saveAction = this.actions.getSpecial().primary;
			this.saveAction.setDisabled( true );
		} );
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.initialize = function () {
	bs.categoryManager.ui.dialog.CreateCategory.super.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.categoryInput = new OO.ui.TextInputWidget();
	this.categoryInput.connect( this, {
		change: function ( value ) {
			if ( value.length > 0 ) {
				this.saveAction.setDisabled( false );
				if ( this.typeTimeout ) {
					clearTimeout( this.typeTimeout );
				}
				this.typeTimeout = setTimeout( () => {
					this.validateTitleNotExist( value );
				}, 500 );
				return;
			}
			this.saveAction.setDisabled( true );
		}
	} );
	this.categoryInputLayout = new OO.ui.FieldLayout( this.categoryInput, {
		align: 'top',
		label: mw.message( 'bs-categorymanager-add-category-label' ).text()
	} );
	this.panel.$element.append( this.categoryInputLayout.$element );
	this.$body.append( this.panel.$element );
	this.updateSize();
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.validateTitleNotExist = function ( value ) {
	this.clearError();
	if ( !value ) {
		this.actions.setAbilities( { save: false } );
		return;
	}

	const categoryAction = new bs.categoryManager.api.CategoryActions();
	categoryAction.getCategoryNames().then( ( allCategories ) => {
		if ( allCategories.indexOf( value.toLowerCase() ) !== -1 ) {
			this.actions.setAbilities( { save: false } );
			this.setExistWarning();
		} else {
			this.actions.setAbilities( { save: true } );
		}
	} ).fail( () => {
		// Something went wrong, let user go to the page and deal with it there
		this.actions.setAbilities( { save: true } );
	} );
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.getActionProcess = function ( action ) {
	if ( action ) {
		return new OO.ui.Process( function () {
			this.pushPending();
			const newCategoryValue = this.categoryInput.getValue();
			const categoryAction = new bs.categoryManager.api.CategoryActions();

			categoryAction.addCategory( newCategoryValue, this.selectedCategory )
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
					this.showErrors( new OO.ui.Error( error, { recoverable: false } ) );
					this.updateSize();
					this.popPending();
					return;
				} );
		}, this );
	}
	return bs.categoryManager.ui.dialog.CreateCategory.super.prototype.getActionProcess.call( this, action );
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.setError = function ( error ) {
	this.categoryInputLayout.setErrors( [ error ] );
	if ( this.categoryInput.lookupMenu ) {
		this.categoryInput.lookupMenu.toggle( false );
	}
	this.updateSize();
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.clearError = function () {
	this.categoryInputLayout.setWarnings( [] );
	this.categoryInputLayout.setErrors( [] );
	this.updateSize();
};

bs.categoryManager.ui.dialog.CreateCategory.prototype.setExistWarning = function () {
	if ( this.categoryInputLayout ) {
		this.categoryInputLayout.setWarnings( [ mw.message( 'bs-categorymanager-dlg-create-category-exists-label' ).text() ] );
	}
	this.updateSize();
};
